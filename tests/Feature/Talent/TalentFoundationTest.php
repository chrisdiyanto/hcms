<?php

namespace Tests\Feature\Talent;

use App\Domain\Talent\PotentialInstruments;
use App\Support\Security\Roles;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/** Prasyarat P3 CBHRM: aspirasi karier (ESS, berriwayat) dan kamus instrumen potensi berversi. */
class TalentFoundationTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $company;

    private int $family;

    private int $job;

    private User $hr;

    private User $staff;

    private int $employee;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->tenant = $this->makeTenant('TA');
        $this->company = $this->makeCompany($this->tenant, 'A1');
        $unit = $this->makeUnit($this->tenant, 'Divisi Risiko', null, $this->company);
        $this->family = $this->insert('job_families', ['code' => 'RSK', 'name' => 'Manajemen Risiko']);
        $this->job = $this->insert('jobs_catalog', ['code' => 'CRO', 'title' => 'Chief Risk Officer', 'job_family_id' => $this->family]);
        $position = $this->insert('positions', ['code' => 'ANL', 'title' => 'Analis', 'company_id' => $this->company, 'unit_id' => $unit,
            'job_id' => $this->job, 'status' => 'active', 'headcount' => 1, 'effective_from' => '2020-01-01']);

        $this->hr = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $this->staff = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'rina');
        $this->employee = $this->insert('employees', ['company_id' => $this->company, 'user_id' => $this->staff->id, 'employee_number' => 'E001',
            'full_name' => 'Rina Analis', 'employment_status' => 'pkwtt', 'join_date' => '2021-01-04', 'status' => 'active']);
        $this->insert('employee_assignments', ['employee_id' => $this->employee, 'position_id' => $position, 'type' => 'primary', 'effective_from' => '2021-01-04']);
    }

    private function insert(string $table, array $data): int
    {
        return DB::table($table)->insertGetId($data + ['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'created_at' => now()]);
    }

    public function test_aspirasi_karier_diisi_pegawai_berriwayat_dan_terlihat_hr(): void
    {
        $this->actingAsSximo($this->staff, Roles::id(Roles::EMPLOYEE));
        $this->get('/ess/career')->assertOk()->assertSee('Belum ada aspirasi karier');
        $this->post('/ess/career', ['horizon' => '3_5', 'mobility' => 'yes'])->assertSessionHasErrors('target_role');
        $this->post('/ess/career', ['horizon' => '9', 'mobility' => 'yes', 'target_role' => 'x'])->assertSessionHasErrors('horizon');

        $this->post('/ess/career', ['target_job_family_id' => $this->family, 'target_job_id' => $this->job, 'horizon' => '3_5', 'mobility' => 'discuss',
            'interests' => 'Tata kelola risiko'])->assertSessionHasNoErrors()->assertRedirect('ess/career');
        $this->travel(2)->months();
        $this->post('/ess/career', ['target_role' => 'Data Scientist', 'horizon' => '1_2', 'mobility' => 'no'])->assertSessionHasNoErrors();

        $rows = DB::table('employee_career_aspirations')->where('employee_id', $this->employee)->orderBy('id')->get();
        $this->assertSame([[0, $this->job, '3_5'], [1, null, '1_2']], $rows->map(fn ($r) => [(int) $r->is_current, $r->target_job_id ? (int) $r->target_job_id : null, $r->horizon])->all());
        $this->assertSame($this->tenant, (int) $rows[0]->tenant_id);
        $this->assertSame(2, DB::table('audit_logs')->where('auditable_type', 'employee_career_aspirations')->count());
        $this->get('/ess/career')->assertOk()->assertSee('Data Scientist')->assertSee('Chief Risk Officer')->assertSee('Terkini');
        $this->assertCount(2, json_decode($this->get('/ess/my-data')->streamedContent(), true)['aspirasi_karier']);

        // HR melihat tab aspirasi di profil pegawai; tenant lain tidak dapat membuka pegawai ini
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN))->get('/employees/'.$this->employee)->assertOk()
            ->assertSee('Aspirasi karier')->assertSee('Data Scientist')->assertSee('Tata kelola risiko');
        $other = $this->makeUser($this->makeTenant('TB'), Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($other, Roles::id(Roles::TENANT_ADMIN))->get('/employees/'.$this->employee)->assertNotFound();
    }

    public function test_instrumen_potensi_berversi_draft_terbit_pensiun(): void
    {
        $this->actingAsSximo($this->staff, Roles::id(Roles::EMPLOYEE))->get('/talent/potential-instruments')->assertRedirect('dashboard');

        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN));
        $this->get('/talent/potential-instruments')->assertOk()->assertSee('Belum ada instrumen');
        $this->post('/talent/potential-instruments/draft')->assertSessionHasNoErrors();
        $v1 = (int) DB::table('potential_instruments')->where('tenant_id', $this->tenant)->value('id');
        $this->assertSame(['ABILITY' => 40, 'ASPIRATION' => 30, 'ENGAGEMENT' => 30], DB::table('potential_criteria')->where('instrument_id', $v1)->orderBy('sort_order')->pluck('weight', 'code')->map(fn ($w) => (int) $w)->all());
        $this->post('/talent/potential-instruments/draft')->assertSessionHasErrors('version');

        // Bobot ≠ 100 dan indikator < 2 → tersimpan sebagai draft tetapi tidak dapat diterbitkan
        $criteria = DB::table('potential_criteria')->where('instrument_id', $v1)->orderBy('sort_order')->get()
            ->map(fn ($c) => ['code' => $c->code, 'name' => $c->name, 'description' => $c->description, 'indicators' => $c->indicators, 'weight' => $c->weight])->all();
        $criteria[0]['weight'] = 50;
        $criteria[1]['indicators'] = 'Satu indikator saja';
        $header = ['name' => 'Potensi 2027', 'scale_max' => 5, 'threshold_medium' => 2.5, 'threshold_high' => 3.75, 'validity_months' => 24];
        $this->put('/talent/potential-instruments/'.$v1, $header + ['criteria' => $criteria])->assertSessionHasNoErrors();
        $this->get('/talent/potential-instruments')->assertOk()->assertSee('Total bobot kriteria harus 100% (saat ini 110%)')->assertSee('ASPIRATION membutuhkan minimal 2');
        $this->post('/talent/potential-instruments/'.$v1.'/publish')->assertSessionHasErrors('publish');
        $this->put('/talent/potential-instruments/'.$v1, ['threshold_medium' => 4, 'threshold_high' => 3] + $header + ['criteria' => $criteria])
            ->assertSessionHasErrors('threshold_medium');

        $criteria[0]['weight'] = 40;
        $criteria[1]['indicators'] = "Mencari tantangan\nTujuan karier jelas";
        $this->put('/talent/potential-instruments/'.$v1, $header + ['criteria' => $criteria])->assertSessionHasNoErrors();
        $this->post('/talent/potential-instruments/'.$v1.'/publish')->assertSessionHasNoErrors();
        $this->assertSame('published', DB::table('potential_instruments')->where('id', $v1)->value('status'));
        // Versi terbit tidak dapat diubah
        $this->put('/talent/potential-instruments/'.$v1, $header + ['criteria' => $criteria])->assertSessionHasErrors('status');

        // Draft baru menyalin versi terbit; terbit memensiunkan versi lama
        $this->post('/talent/potential-instruments/draft')->assertSessionHasNoErrors();
        $v2 = (int) DB::table('potential_instruments')->where('tenant_id', $this->tenant)->where('version', 2)->value('id');
        $this->assertSame('Potensi 2027', DB::table('potential_instruments')->where('id', $v1)->value('name'));
        $this->assertSame(3, DB::table('potential_criteria')->where('instrument_id', $v2)->count());
        $this->post('/talent/potential-instruments/'.$v2.'/publish')->assertSessionHasNoErrors();
        $this->assertSame(['retired', 'published'], DB::table('potential_instruments')->whereIn('id', [$v1, $v2])->orderBy('version')->pluck('status')->all());
        $this->get('/talent/potential-instruments?version='.$v1)->assertOk()->assertSee('Pensiun')->assertSee('Mencari tantangan');
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'potential_instruments')->where('description', 'Instrumen potensi diterbitkan')->exists());

        // Tenant lain tidak melihat instrumen ini
        $other = $this->makeUser($this->makeTenant('TB'), Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($other, Roles::id(Roles::TENANT_ADMIN))->get('/talent/potential-instruments')->assertOk()->assertSee('Belum ada instrumen');
        $this->put('/talent/potential-instruments/'.$v2, $header + ['criteria' => $criteria])->assertNotFound();
    }

    public function test_skor_potensi_tertimbang_dan_tingkat(): void
    {
        $instrument = (object) ['threshold_medium' => 2.5, 'threshold_high' => 3.75];
        $criteria = collect(PotentialInstruments::DEFAULT_CRITERIA)->map(fn ($c) => (object) $c);

        // (4×40 + 4×30 + 3×30) / 100 = 3,70 → sedang; (5×40 + 4×30 + 3×30) / 100 = 4,10 → tinggi
        $this->assertSame(['score' => 3.7, 'level' => 'medium', 'complete' => true], PotentialInstruments::evaluate($instrument, $criteria, ['ABILITY' => 4, 'ASPIRATION' => 4, 'ENGAGEMENT' => 3]));
        $this->assertSame('high', PotentialInstruments::evaluate($instrument, $criteria, ['ABILITY' => 5, 'ASPIRATION' => 4, 'ENGAGEMENT' => 3])['level']);
        $this->assertSame(['score' => 2.0, 'level' => 'low', 'complete' => false], PotentialInstruments::evaluate($instrument, $criteria, ['ABILITY' => 2]));
        $this->assertSame(['score' => null, 'level' => null, 'complete' => false], PotentialInstruments::evaluate($instrument, $criteria, []));
    }
}
