<?php

namespace Tests\Feature\Development;

use App\Domain\Development\TrainingNeeds;
use App\Support\Security\Roles;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/** Rilis 2 gelombang 1 — TNA (laporan CBHRM 5): saran otomatis, pengajuan, tinjauan HR, prioritas K5, laporan. */
class TrainingNeedsTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $company;

    /** @var array<string,int> */
    private array $positions = [];

    /** @var array<string,int> */
    private array $competencies = [];

    private User $hr;

    private User $hr2;

    private User $manager;

    private User $analyst;

    private int $analystEmployee;

    private int $managerEmployee;

    private int $year;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->year = now()->year;
        $this->tenant = $this->makeTenant('TA');
        $this->company = $this->makeCompany($this->tenant, 'A1');
        $unit = $this->makeUnit($this->tenant, 'Divisi Risiko', null, $this->company);
        $family = $this->insert('job_families', ['code' => 'RSK', 'name' => 'Risk']);
        $job = $this->insert('jobs_catalog', ['code' => 'RA', 'title' => 'Risk Analyst', 'job_family_id' => $family]);
        foreach (['MGR' => 'Kepala Risiko', 'ANL' => 'Analis Risiko'] as $code => $title) {
            $this->positions[$code] = $this->insert('positions', ['code' => $code, 'title' => $title, 'company_id' => $this->company,
                'unit_id' => $unit, 'job_id' => $job, 'status' => 'active', 'headcount' => 1, 'effective_from' => '2020-01-01']);
        }
        DB::table('position_relationships')->insert(['tenant_id' => $this->tenant, 'position_id' => $this->positions['ANL'],
            'reports_to_id' => $this->positions['MGR'], 'type' => 'solid', 'effective_from' => '2020-01-01']);
        $version = $this->insert('competency_framework_versions', ['version' => 1, 'name' => 'v1', 'status' => 'published']);
        $category = DB::table('competency_categories')->insertGetId(['tenant_id' => $this->tenant, 'code' => 'FUNC', 'name' => 'Functional']);
        foreach (['RISK' => 'Manajemen Risiko', 'DATA' => 'Analisis Data'] as $code => $name) {
            $this->competencies[$code] = $this->insert('competencies', ['framework_version_id' => $version, 'category_id' => $category, 'code' => $code, 'name' => $name]);
        }

        $this->hr = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $this->hr2 = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $this->manager = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'budi');
        $this->analyst = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'rina');
        $this->managerEmployee = $this->employee($this->manager, 'MGR', 'Budi Kepala');
        $this->analystEmployee = $this->employee($this->analyst, 'ANL', 'Rina Analis');

        // Gap Rina: RISK −2 (kritikalitas 3) dan DATA −0,5 (di bawah ambang saran)
        foreach ([['RISK', 2, 4, 3, -2], ['DATA', 2.5, 3, 1, -0.5]] as [$code, $actual, $required, $criticality, $gap]) {
            DB::table('competency_gaps')->insert(['tenant_id' => $this->tenant, 'employee_id' => $this->analystEmployee, 'position_id' => $this->positions['ANL'],
                'competency_id' => $this->competencies[$code], 'competency_code' => $code, 'required_level' => $required, 'criticality' => $criticality,
                'actual' => $actual, 'gap' => $gap, 'computed_at' => now()]);
        }
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

    public function test_prioritas_k5(): void
    {
        $this->assertSame([6.0, 1.5, 6.0, null, 6.0], [TrainingNeeds::score(3, -2, false), TrainingNeeds::score(1, -1, true),
            TrainingNeeds::score(2, -2, true), TrainingNeeds::score(3, 0, false), TrainingNeeds::score(1, -6, true)]);
        $this->assertSame(['high', 'medium', 'medium', 'low'], [TrainingNeeds::level(6), TrainingNeeds::level(5.99), TrainingNeeds::level(3), TrainingNeeds::level(2.99)]);
    }

    public function test_saran_otomatis_dari_gap_dan_pip_idempoten(): void
    {
        $pip = $this->insert('performance_improvement_plans', ['employee_id' => $this->managerEmployee, 'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(), 'objectives' => 'Perbaiki ketepatan laporan', 'owner_user_id' => $this->hr->id,
            'status' => 'active', 'created_by' => $this->hr->id]);

        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN))->post('/development/needs/refresh', ['year' => $this->year])->assertSessionHasNoErrors();
        $rows = DB::table('training_needs')->where('tenant_id', $this->tenant)->orderBy('id')->get();
        $this->assertSame([['gap:RISK', 'gap', 'high', '6.00'], ['pip:'.$pip, 'performance', 'high', null]],
            $rows->map(fn ($r) => [$r->dedupe_key, $r->source, $r->priority, $r->priority_score])->all());
        $this->assertSame(['suggested', 'suggested'], $rows->pluck('status')->all());

        // Idempoten: tidak menggandakan; saran yang sudah disetujui tidak dihapus walau gap tertutup
        $this->artisan('hcms:tna-suggest', ['--tenant' => 'TA'])->assertExitCode(0);
        $this->assertSame(2, DB::table('training_needs')->count());
        $this->actingAsSximo($this->hr2, Roles::id(Roles::TENANT_ADMIN))->post('/development/needs/review', ['ids' => [$rows[0]->id], 'decision' => 'approved'])->assertSessionHasNoErrors();
        DB::table('competency_gaps')->update(['gap' => 0]);
        DB::table('performance_improvement_plans')->update(['status' => 'completed']);
        $this->artisan('hcms:tna-suggest', ['--tenant' => 'TA'])->assertExitCode(0);
        $this->assertSame(['approved'], DB::table('training_needs')->pluck('status')->all(), 'saran PIP usang dihapus, yang disetujui tetap');
    }

    public function test_usulan_pegawai_atasan_dan_tinjauan_hr_dengan_sod(): void
    {
        // Pegawai untuk diri sendiri; atasan untuk bawahan langsung; pegawai tidak boleh untuk atasannya
        $this->actingAsSximo($this->analyst, Roles::id(Roles::EMPLOYEE))->get('/ess/training-requests')->assertOk()->assertSee('Saya sendiri');
        $this->post('/ess/training-requests', ['employee_id' => $this->analystEmployee, 'period_year' => $this->year, 'competency_id' => $this->competencies['RISK'],
            'reason' => 'Butuh pemodelan risiko kredit', 'priority' => 'low'])->assertSessionHasNoErrors();
        $this->post('/ess/training-requests', ['employee_id' => $this->managerEmployee, 'period_year' => $this->year, 'topic' => 'Kepemimpinan',
            'reason' => 'x', 'priority' => 'low'])->assertSessionHasErrors('employee_id');
        $this->actingAsSximo($this->manager, Roles::id(Roles::EMPLOYEE))->get('/ess/training-requests')->assertOk()->assertSee('Rina Analis (bawahan)');
        $this->post('/ess/training-requests', ['employee_id' => $this->analystEmployee, 'period_year' => $this->year, 'topic' => 'Excel lanjutan',
            'reason' => 'Mempercepat laporan', 'priority' => 'medium'])->assertSessionHasNoErrors();

        $own = DB::table('training_needs')->where('competency_code', 'RISK')->first();
        // Ada gap kompetensi → prioritas K5 mengabaikan urgensi pilihan pengaju (3 × 2 = 6 → tinggi)
        $this->assertSame(['request', 'proposed', 'high', '6.00'], [$own->source, $own->status, $own->priority, $own->priority_score]);
        $excel = DB::table('training_needs')->where('topic', 'Excel lanjutan')->first();
        $this->assertSame(['medium', null, $this->manager->id], [$excel->priority, $excel->priority_score, (int) $excel->requested_by]);

        // HR: kebutuhan organisasi untuk unit; pengaju tidak boleh menyetujui pengajuannya sendiri
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN));
        $this->get('/development/needs/create')->assertOk();
        $this->post('/development/needs', ['period_year' => $this->year, 'unit_id' => DB::table('positions')->where('id', $this->positions['ANL'])->value('unit_id'),
            'topic' => 'Anti-fraud', 'reason' => 'Regulasi baru', 'priority' => 'high'])->assertSessionHasNoErrors();
        $org = DB::table('training_needs')->where('source', 'organization')->pluck('id');
        $this->assertCount(2, $org, 'semua pegawai aktif di unit');
        $this->post('/development/needs/review', ['ids' => $org->all(), 'decision' => 'approved'])->assertSessionHasErrors('ids');
        $this->post('/development/needs/review', ['ids' => [$excel->id], 'decision' => 'rejected'])->assertSessionHasErrors('review_note');
        $this->post('/development/needs/review', ['ids' => [$excel->id], 'decision' => 'rejected', 'review_note' => 'Gunakan kursus internal'])->assertSessionHasNoErrors();
        $this->post('/development/needs/review', ['ids' => [$own->id], 'decision' => 'approved'])->assertSessionHasNoErrors();
        $this->actingAsSximo($this->hr2, Roles::id(Roles::TENANT_ADMIN))->post('/development/needs/review', ['ids' => $org->all(), 'decision' => 'approved'])->assertSessionHasNoErrors();

        // Rincian: terkait sasaran strategis menaikkan skor K5 (×1,5)
        $period = $this->insert('performance_periods', ['code' => 'K26', 'name' => 'Kinerja', 'start_date' => $this->year.'-01-01', 'end_date' => $this->year.'-12-31', 'status' => 'planning']);
        $objective = $this->insert('strategic_objectives', ['period_id' => $period, 'level' => 'corporate', 'code' => 'SO1', 'title' => 'Risiko terkendali']);
        $this->put('/development/needs/'.$own->id, ['priority' => 'low', 'strategic_objective_id' => $objective, 'suggested_intervention' => 'Sertifikasi risiko',
            'estimated_cost' => 5000000, 'due_date' => $this->year.'-09-30'])->assertSessionHasNoErrors();
        $this->assertSame(['9.00', 'high'], [DB::table('training_needs')->where('id', $own->id)->value('priority_score'), DB::table('training_needs')->where('id', $own->id)->value('priority')]);

        // Pegawai melihat status & catatan HR
        $this->actingAsSximo($this->analyst, Roles::id(Roles::EMPLOYEE))->get('/ess/training-requests')->assertOk()
            ->assertSee('Disetujui')->assertSee('Ditolak')->assertSee('Gunakan kursus internal');
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'training_needs')->where('description', 'Kebutuhan pelatihan disetujui')->exists());
    }

    public function test_laporan_hanya_menghitung_yang_disetujui_dan_isolasi(): void
    {
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN))->post('/development/needs/refresh', ['year' => $this->year]);
        $this->post('/development/needs', ['period_year' => $this->year, 'employee_ids' => [$this->analystEmployee, $this->managerEmployee],
            'topic' => 'Anti-fraud', 'reason' => 'Regulasi', 'priority' => 'medium', 'estimated_cost' => 1500000]);
        $this->actingAsSximo($this->hr2, Roles::id(Roles::TENANT_ADMIN))->post('/development/needs/review',
            ['ids' => DB::table('training_needs')->where('source', 'organization')->pluck('id')->all(), 'decision' => 'approved'])->assertSessionHasNoErrors();

        $report = $this->get('/development/needs/report?year='.$this->year)->assertOk()->assertSee('Anti-fraud');
        $report->assertViewHas('approvedTotal', 2)->assertViewHas('pendingSuggested', 1)->assertViewHas('openTotal', 2)
            ->assertViewHas('people', 2)->assertViewHas('cost', 3000000.0);
        $this->get('/development/needs?status=suggested&year='.$this->year)->assertOk()->assertSee('Manajemen Risiko')->assertSee('Saran otomatis');

        $this->actingAsSximo($this->analyst, Roles::id(Roles::EMPLOYEE))->get('/development/needs')->assertRedirect('dashboard');
        $other = $this->makeUser($this->makeTenant('TB'), Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($other, Roles::id(Roles::TENANT_ADMIN))->get('/development/needs/report?year='.$this->year)->assertOk()->assertViewHas('approvedTotal', 0);
        $this->get('/development/needs/'.DB::table('training_needs')->value('id').'/edit')->assertNotFound();
    }
}
