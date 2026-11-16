<?php

namespace Tests\Feature\Competency;

use App\Domain\Competency\Services\CompetencyFramework;
use App\Domain\Competency\Services\JobFitCalculator;
use App\Support\Security\Roles;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CompetencyAssessmentTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $company;

    private int $unit;

    private int $job;

    /** @var array<string,int> */
    private array $positions = [];

    /** @var array<string,int> kode => competency_id */
    private array $competencies = [];

    private User $hr;

    private User $analyst;

    private User $manager;

    private int $analystEmployee;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->tenant = $this->makeTenant('TA');
        $this->company = $this->makeCompany($this->tenant, 'A1');
        $this->unit = $this->makeUnit($this->tenant, 'Divisi Risiko', null, $this->company);
        CompetencyFramework::ensureCategories($this->tenant);

        // Kamus terbit + profil job sesuai contoh blueprint 7.4
        $version = $this->insert('competency_framework_versions', ['version' => 1, 'name' => 'Kamus v1', 'status' => 'published', 'published_at' => now()]);
        $category = (int) DB::table('competency_categories')->where('tenant_id', $this->tenant)->where('code', 'FUNC')->value('id');
        $family = $this->insert('job_families', ['code' => 'RSK', 'name' => 'Risk']);
        $this->job = $this->insert('jobs_catalog', ['code' => 'RISK-AN', 'title' => 'Risk Analyst', 'job_family_id' => $family]);
        foreach (['RISK' => [4, 3], 'LEAD' => [4, 3], 'STRAT' => [4, 2], 'DATA' => [3, 1]] as $code => [$level, $weight]) {
            $id = $this->insert('competencies', ['framework_version_id' => $version, 'category_id' => $category, 'code' => $code, 'name' => 'Kompetensi '.$code]);
            unset($this->competencies[$code]);
            $this->competencies[$code] = $id;
            foreach (range(1, 5) as $l) {
                DB::table('competency_levels')->insert(['tenant_id' => $this->tenant, 'competency_id' => $id, 'level' => $l, 'title' => 'L'.$l, 'description' => 'Deskripsi '.$code.' '.$l]);
            }
            DB::table('job_competencies')->insert(['tenant_id' => $this->tenant, 'job_id' => $this->job, 'competency_id' => $id,
                'required_level' => $level, 'criticality' => $weight, 'created_at' => now()]);
        }

        // Struktur: Kepala Divisi ← Analis (solid line)
        foreach (['MGR' => 'Kepala Divisi', 'ANL' => 'Analis Risiko'] as $code => $title) {
            $this->positions[$code] = $this->insert('positions', ['code' => $code, 'title' => $title, 'company_id' => $this->company,
                'unit_id' => $this->unit, 'job_id' => $this->job, 'status' => 'active', 'headcount' => 1, 'effective_from' => '2020-01-01']);
        }
        DB::table('position_relationships')->insert(['tenant_id' => $this->tenant, 'position_id' => $this->positions['ANL'],
            'reports_to_id' => $this->positions['MGR'], 'type' => 'solid', 'effective_from' => '2020-01-01']);

        $this->analyst = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'rina');
        $this->manager = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'budi');
        $this->analystEmployee = $this->employee($this->analyst, 'ANL', 'Rina Analis');
        $this->employee($this->manager, 'MGR', 'Budi Kepala');

        $this->hr = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN));
    }

    private function insert(string $table, array $data): int
    {
        return DB::table($table)->insertGetId($data + ['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'created_at' => now()]);
    }

    private function employee(User $user, string $position, string $name): int
    {
        $id = $this->insert('employees', ['company_id' => $this->company, 'user_id' => $user->id, 'employee_number' => 'E'.Str::upper(Str::random(5)),
            'full_name' => $name, 'employment_status' => 'pkwtt', 'join_date' => '2021-01-04', 'status' => 'active']);
        $this->insert('employee_assignments', ['employee_id' => $id, 'position_id' => $this->positions[$position], 'type' => 'primary', 'effective_from' => '2021-01-04']);

        return $id;
    }

    private function open(array $extra = []): int
    {
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN));
        $this->post('/competency/assessments', $extra + ['employee_id' => $this->analystEmployee, 'include_self' => 1, 'include_manager' => 1])
            ->assertSessionHasNoErrors()->assertRedirect();

        return (int) DB::table('competency_assessments')->max('id');
    }

    private function raterId(int $assessment, User $user, string $type): int
    {
        return (int) DB::table('assessment_raters')->where('assessment_id', $assessment)->where('rater_user_id', $user->id)->where('rater_type', $type)->value('id');
    }

    /** @param  array<string,int>  $scores  kode => skor */
    private function rate(User $user, int $raterId, array $scores)
    {
        $payload = [];
        foreach ($scores as $code => $score) {
            $payload[$this->competencies[$code]] = ['score' => $score, 'evidence' => 'Contoh perilaku '.$code];
        }

        return $this->actingAsSximo($user, Roles::id(Roles::EMPLOYEE))->put('/competency/my-assessments/'.$raterId, ['scores' => $payload]);
    }

    public function test_asesmen_atasan_menghasilkan_jfi_contoh_blueprint_dan_draf_idp(): void
    {
        $assessment = $this->open();
        $this->assertSame(['self:rina', 'manager:budi'], DB::table('assessment_raters')->join('tb_users', 'tb_users.id', '=', 'assessment_raters.rater_user_id')
            ->where('assessment_id', $assessment)->orderBy('rater_type')->get()->map(fn ($r) => $r->rater_type.':'.$r->username)->all());
        $this->assertSame(2, DB::table('tb_notification')->where('url', 'competency/my-assessments')->count());

        // Atasan melihat tugasnya, form menampilkan deskripsi level dari kamus
        $managerRater = $this->raterId($assessment, $this->manager, 'manager');
        $this->actingAsSximo($this->manager, Roles::id(Roles::EMPLOYEE))->get('/competency/my-assessments')->assertOk()->assertSee('Rina Analis');
        $this->get('/competency/my-assessments/'.$managerRater)->assertOk()->assertSee('Deskripsi RISK 3');

        // Skor tidak lengkap ditolak
        $this->rate($this->manager, $managerRater, ['RISK' => 3])->assertSessionHasErrors('scores.'.$this->competencies['LEAD'].'.score');
        $this->rate($this->manager, $managerRater, ['RISK' => 3, 'LEAD' => 2, 'STRAT' => 4, 'DATA' => 2])->assertSessionHasNoErrors();
        $this->assertSame('submitted', DB::table('assessment_raters')->where('id', $managerRater)->value('status'));

        // Penilai lain tidak bisa membuka form milik atasan
        $this->actingAsSximo($this->analyst, Roles::id(Roles::EMPLOYEE))->get('/competency/my-assessments/'.$managerRater)->assertNotFound();

        // Tutup: diri sendiri belum mengirim → dihapus, skor = skor atasan
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN));
        $this->post('/competency/assessments/'.$assessment.'/close')->assertSessionHasNoErrors()->assertSessionHas('status', 'success');

        $row = DB::table('competency_assessments')->find($assessment);
        $this->assertSame('closed', $row->status);
        $this->assertSame(now()->addMonths(24)->toDateString(), $row->valid_until);
        $this->assertSame(0, DB::table('assessment_raters')->where('assessment_id', $assessment)->where('status', 'pending')->count());

        $fit = DB::table('job_fit_results')->where('employee_id', $this->analystEmployee)->first();
        $this->assertSame('71.30', $fit->jfi);
        $this->assertSame(JobFitCalculator::READY_LATER, $fit->band);
        $this->assertSame(1, (int) $fit->complete);

        $this->assertSame(['DATA' => '2.00', 'LEAD' => '2.00', 'RISK' => '3.00', 'STRAT' => '4.00'],
            DB::table('employee_competencies')->where('employee_id', $this->analystEmployee)->orderBy('competency_code')->pluck('score', 'competency_code')->all());
        $this->assertSame(['LEAD' => '-2.00', 'RISK' => '-1.00', 'DATA' => '-1.00', 'STRAT' => '0.00'],
            DB::table('competency_gaps')->where('employee_id', $this->analystEmployee)->orderBy('gap')->orderByDesc('criticality')->pluck('gap', 'competency_code')->all());
        // Draf IDP hanya untuk kompetensi kritis (bobot 3) yang gap-nya negatif
        $this->assertSame(['LEAD:2→4', 'RISK:3→4'], DB::table('competency_idp_suggestions')->where('assessment_id', $assessment)->orderBy('competency_code')
            ->get()->map(fn ($r) => "{$r->competency_code}:{$r->current_level}→{$r->target_level}")->all());
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'competency_assessments')->where('description', 'Asesmen kompetensi ditutup')->exists());

        // Setelah ditutup penilaian tidak bisa diubah; halaman hasil & laporan tampil
        $this->rate($this->manager, $managerRater, ['RISK' => 5, 'LEAD' => 5, 'STRAT' => 5, 'DATA' => 5])->assertSessionHasErrors('scores');
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN));
        $this->get('/competency/assessments/'.$assessment)->assertOk()->assertSee('71,3%')->assertSee('Siap &gt; 2 tahun', false);
        $this->get('/competency/reports')->assertOk()->assertSee('Rina Analis')->assertSee('data-chart', false);
        $this->get('/competency/reports/employees/'.$this->analystEmployee)->assertOk()->assertSee('71,3%');

        // Pegawai melihat profilnya sendiri di Asesmen Saya
        $this->actingAsSximo($this->analyst, Roles::id(Roles::EMPLOYEE))->get('/competency/my-assessments')->assertOk()->assertSee('71,3%');
    }

    public function test_bobot_penilai_anonimitas_rekan_dan_assessment_center(): void
    {
        $peers = [$this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE)), $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE))];
        $assessment = $this->open(['peers' => array_map(fn ($u) => $u->id, $peers)]);

        $all = fn (int $s) => ['RISK' => $s, 'LEAD' => $s, 'STRAT' => $s, 'DATA' => $s];
        $this->rate($this->manager, $this->raterId($assessment, $this->manager, 'manager'), $all(4))->assertSessionHasNoErrors();
        $this->rate($this->analyst, $this->raterId($assessment, $this->analyst, 'self'), $all(2))->assertSessionHasNoErrors();
        foreach ($peers as $peer) {
            $this->rate($peer, $this->raterId($assessment, $peer, 'peer'), $all(1))->assertSessionHasNoErrors();
        }

        // Assessment center untuk RISK menggantikan skor penilai
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN));
        $center = [];
        foreach ($this->competencies as $code => $id) {
            $center[$id] = ['score' => $code === 'RISK' ? 5 : null];
        }
        $this->post('/competency/assessments/'.$assessment.'/assessment-center', ['scores' => $center])->assertSessionHasErrors('scores.'.$this->competencies['LEAD'].'.score');
        // Rekap HR tidak menampilkan skor individual rekan
        $this->get('/competency/assessments/'.$assessment)->assertOk()->assertSee('tidak dihitung');

        $this->post('/competency/assessments/'.$assessment.'/close')->assertSessionHasNoErrors();

        // Rekan (2 penilai < 3) tidak dihitung: (4×50 + 2×10) / 60 = 3,67
        $scores = DB::table('employee_competencies')->where('employee_id', $this->analystEmployee)->get()->keyBy('competency_code');
        $this->assertSame('3.67', $scores['LEAD']->score);
        $this->assertSame('raters', $scores['LEAD']->source);
        $breakdown = json_decode($scores['LEAD']->breakdown, true);
        $this->assertFalse($breakdown['peer']['counted']);
        $this->assertSame(2, $breakdown['peer']['raters']);
    }

    public function test_assessment_center_menggantikan_skor_penilai(): void
    {
        $assessment = $this->open(['include_self' => 0]);
        $this->rate($this->manager, $this->raterId($assessment, $this->manager, 'manager'), ['RISK' => 2, 'LEAD' => 2, 'STRAT' => 2, 'DATA' => 2]);

        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN));
        $center = [];
        foreach ($this->competencies as $code => $id) {
            $center[$id] = ['score' => $code === 'RISK' ? 5 : 3, 'evidence' => 'AC 2026'];
        }
        $this->post('/competency/assessments/'.$assessment.'/assessment-center', ['scores' => $center])->assertSessionHasNoErrors();
        $this->post('/competency/assessments/'.$assessment.'/close')->assertSessionHasNoErrors();

        $risk = DB::table('employee_competencies')->where('employee_id', $this->analystEmployee)->where('competency_code', 'RISK')->first();
        $this->assertSame('5.00', $risk->score);
        $this->assertSame('assessment_center', $risk->source);
    }

    public function test_validasi_pembukaan_hak_akses_dan_isolasi(): void
    {
        // Pegawai tanpa profil kompetensi pada posisinya ditolak
        DB::table('job_competencies')->where('job_id', $this->job)->delete();
        $this->post('/competency/assessments', ['employee_id' => $this->analystEmployee, 'include_self' => 1, 'include_manager' => 1])
            ->assertSessionHasErrors('employee_id');
        DB::table('job_competencies')->insert(['tenant_id' => $this->tenant, 'job_id' => $this->job, 'competency_id' => $this->competencies['RISK'],
            'required_level' => 3, 'criticality' => 3, 'created_at' => now()]);

        // Satu asesmen berjalan per pegawai
        $assessment = $this->open();
        $this->post('/competency/assessments', ['employee_id' => $this->analystEmployee, 'include_self' => 1])->assertSessionHasErrors('employee_id');

        // Belum ada penilaian terkirim → tidak bisa ditutup; pembatalan butuh alasan
        $this->post('/competency/assessments/'.$assessment.'/close')->assertSessionHasErrors('status');
        $this->post('/competency/assessments/'.$assessment.'/cancel', [])->assertSessionHasErrors('reason');
        $this->post('/competency/assessments/'.$assessment.'/cancel', ['reason' => 'Salah pegawai'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', DB::table('competency_assessments')->where('id', $assessment)->value('status'));

        // Pegawai biasa tidak boleh mengelola asesmen
        $this->actingAsSximo($this->analyst, Roles::id(Roles::EMPLOYEE))->get('/competency/assessments')->assertRedirect('dashboard');
        $this->get('/competency/reports')->assertRedirect('dashboard');

        // Tenant lain: tidak melihat asesmen & tidak bisa memilih pegawai tenant ini
        $other = $this->makeTenant('TB');
        $otherAdmin = $this->makeUser($other, Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($otherAdmin, Roles::id(Roles::TENANT_ADMIN))->get('/competency/assessments/'.$assessment)->assertNotFound();
        $this->get('/competency/assessments?status=all')->assertOk()->assertDontSee('Rina Analis');
        $this->post('/competency/assessments', ['employee_id' => $this->analystEmployee, 'include_self' => 1])->assertSessionHasErrors('employee_id');
        $this->post('/competency/assessments', ['employee_id' => $this->analystEmployee, 'peers' => [$this->manager->id]])->assertSessionHasErrors('peers.0');
    }
}
