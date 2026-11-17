<?php

namespace Tests\Feature\Organization;

use App\Support\Security\Roles;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class JobArchitectureTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $company;

    private int $unit;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->tenant = $this->makeTenant('TA');
        $this->company = $this->makeCompany($this->tenant, 'A1');
        $this->unit = $this->makeUnit($this->tenant, 'Divisi Kredit', null, $this->company);
        $this->admin = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($this->admin, Roles::id(Roles::TENANT_ADMIN));
    }

    /** Buat family → level → grade G1..G3 → job (rentang G1–G2) lewat HTTP. */
    private function seedArchitecture(): array
    {
        $this->post('/organization/job-families', ['code' => 'fin', 'name' => 'Finance', 'is_active' => 1])->assertRedirect('organization/job-families');
        $family = DB::table('job_families')->where('code', 'FIN')->value('id');

        $this->post('/organization/job-levels', ['code' => 'STF', 'name' => 'Staff', 'rank' => 1])->assertRedirect();
        $level = DB::table('job_levels')->where('code', 'STF')->value('id');

        foreach ([1, 2, 3] as $rank) {
            $this->post('/organization/job-grades', ['code' => 'G'.$rank, 'name' => 'Grade '.$rank, 'rank' => $rank, 'job_level_id' => $level])->assertRedirect();
        }
        $grades = DB::table('job_grades')->pluck('id', 'code');

        $this->post('/organization/jobs', [
            'code' => 'RISK-AN', 'title' => 'Risk Analyst', 'job_family_id' => $family,
            'grade_min_id' => $grades['G1'], 'grade_max_id' => $grades['G2'],
            'requirements' => [
                ['type' => 'education', 'description' => 'S1 Akuntansi', 'is_mandatory' => 1],
                ['type' => 'certification', 'description' => 'Sertifikasi Manajemen Risiko'],
            ],
        ])->assertRedirect('organization/jobs')->assertSessionHasNoErrors();

        return ['family' => $family, 'level' => $level, 'grades' => $grades, 'job' => DB::table('jobs_catalog')->where('code', 'RISK-AN')->value('id')];
    }

    private function position(array $arch, string $code, array $extra = [])
    {
        return $this->post('/organization/positions', $extra + [
            'code' => $code, 'title' => 'Posisi '.$code, 'company_id' => $this->company, 'unit_id' => $this->unit,
            'job_id' => $arch['job'], 'grade_id' => $arch['grades']['G1'], 'status' => 'active', 'headcount' => 1,
            'effective_from' => '2026-01-01',
        ]);
    }

    /** Prasyarat P3 CBHRM: posisi kritis wajib punya tingkat dampak & alasan; dicabut = dikosongkan. */
    public function test_posisi_kritis_wajib_dampak_dan_alasan(): void
    {
        $arch = $this->seedArchitecture();
        $this->position($arch, 'CRO', ['is_critical' => 1])->assertSessionHasErrors(['critical_impact', 'critical_reason']);
        $this->position($arch, 'CRO', ['is_critical' => 1, 'critical_impact' => 3, 'critical_reason' => 'Pemegang izin regulator'])->assertSessionHasNoErrors();
        $row = DB::table('positions')->where('code', 'CRO')->first();
        $this->assertSame([1, 3, 'Pemegang izin regulator'], [(int) $row->is_critical, (int) $row->critical_impact, $row->critical_reason]);
        $this->get('/organization/positions')->assertOk()->assertSee('Ya — Sangat tinggi');

        // Status kritis dicabut: dampak & alasan ikut dikosongkan, perubahan tercatat di riwayat posisi
        $this->put('/organization/positions/'.$row->id, ['code' => 'CRO', 'title' => 'Posisi CRO', 'company_id' => $this->company, 'unit_id' => $this->unit,
            'job_id' => $arch['job'], 'grade_id' => $arch['grades']['G1'], 'status' => 'active', 'headcount' => 1, 'effective_from' => '2026-01-01',
            'is_critical' => 0, 'critical_impact' => 3, 'critical_reason' => 'sisa form'])->assertSessionHasNoErrors();
        $row = DB::table('positions')->where('id', $row->id)->first();
        $this->assertSame([0, null, null], [(int) $row->is_critical, $row->critical_impact, $row->critical_reason]);
        $changes = json_decode(DB::table('position_histories')->where('position_id', $row->id)->where('action', 'updated')->value('changes'), true);
        $this->assertSame([3, null], $changes['critical_impact']);
    }

    public function test_modul_organisasi_terdaftar_dengan_hak_default(): void
    {
        $this->assertSame(5, DB::table('tb_module')->whereIn('module_name', ['jobfamilies', 'joblevels', 'jobgrades', 'jobs', 'positions'])->where('module_type', 'core')->count());
        $this->get('/organization/positions')->assertOk();
    }

    public function test_master_data_dan_persyaratan_job_tersimpan_per_tenant(): void
    {
        $arch = $this->seedArchitecture();

        $job = DB::table('jobs_catalog')->where('id', $arch['job'])->first();
        $this->assertSame($this->tenant, (int) $job->tenant_id);
        $this->assertNotEmpty($job->uuid);
        $this->assertSame((int) $this->admin->id, (int) $job->created_by);
        $this->assertSame(['S1 Akuntansi', 'Sertifikasi Manajemen Risiko'], DB::table('job_requirements')->where('job_id', $job->id)->orderBy('sort_order')->pluck('description')->all());

        $this->get('/organization/jobs')->assertOk()->assertSee('Risk Analyst')->assertSee('G1 – G2');
        $this->get('/organization/jobs/'.$job->id.'/edit')->assertOk()->assertSee('Sertifikasi Manajemen Risiko');

        // Kode sama di tenant yang sama ditolak; di tenant lain boleh
        $this->post('/organization/job-families', ['code' => 'FIN', 'name' => 'Lagi'])->assertSessionHasErrors('code');
        $other = $this->makeTenant('TB');
        DB::table('job_families')->insert(['tenant_id' => $other, 'uuid' => (string) Str::uuid7(), 'code' => 'HR', 'name' => 'HR B', 'created_at' => now()]);
        $this->post('/organization/job-families', ['code' => 'HR', 'name' => 'Human Resources'])->assertSessionHasNoErrors();
    }

    public function test_rentang_grade_job_divalidasi(): void
    {
        $arch = $this->seedArchitecture();

        $this->post('/organization/jobs', [
            'code' => 'X', 'title' => 'Terbalik', 'job_family_id' => $arch['family'],
            'grade_min_id' => $arch['grades']['G3'], 'grade_max_id' => $arch['grades']['G1'],
        ])->assertSessionHasErrors('grade_max_id');
    }

    public function test_posisi_validasi_unit_company_dan_grade(): void
    {
        $arch = $this->seedArchitecture();
        $otherCompany = $this->makeCompany($this->tenant, 'A2');
        $otherUnit = $this->makeUnit($this->tenant, 'Unit Company Lain', null, $otherCompany);

        $this->position($arch, 'P-BAD-UNIT', ['unit_id' => $otherUnit])->assertSessionHasErrors('unit_id');
        $this->position($arch, 'P-BAD-GRADE', ['grade_id' => $arch['grades']['G3']])->assertSessionHasErrors('grade_id');
        $this->position($arch, 'P-OK')->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('positions')->count());
    }

    public function test_reporting_line_solid_dotted_siklus_dan_riwayat(): void
    {
        $arch = $this->seedArchitecture();
        $this->position($arch, 'HEAD');
        $this->position($arch, 'LEAD');
        $ids = DB::table('positions')->pluck('id', 'code');

        $this->position($arch, 'STAFF', ['reports_to_solid' => $ids['LEAD'], 'reports_to_dotted' => [$ids['HEAD']]])->assertSessionHasNoErrors();
        $staff = DB::table('positions')->where('code', 'STAFF')->value('id');

        // ENUM diurutkan MySQL menurut urutan definisi, jadi urutkan di PHP
        $active = fn () => DB::table('position_relationships')->where('position_id', $staff)->whereNull('effective_to')->get(['type', 'reports_to_id'])
            ->map(fn ($r) => $r->type.':'.$r->reports_to_id)->sort()->values()->all();
        $this->assertSame(['dotted:'.$ids['HEAD'], 'solid:'.$ids['LEAD']], $active());

        // LEAD melapor ke STAFF → siklus
        $lead = DB::table('positions')->where('id', $ids['LEAD'])->first();
        $this->put('/organization/positions/'.$ids['LEAD'], (array) $lead + ['reports_to_solid' => $staff])->assertSessionHasErrors('reports_to_solid');

        // Ganti atasan langsung STAFF ke HEAD, hapus dotted; relasi lama diakhiri, bukan dihapus
        $row = DB::table('positions')->where('id', $staff)->first();
        $this->put('/organization/positions/'.$staff, ['title' => 'Staff Senior', 'reports_to_solid' => $ids['HEAD']] + (array) $row)->assertSessionHasNoErrors();
        $this->assertSame(['solid:'.$ids['HEAD']], $active());
        $this->assertSame(3, DB::table('position_relationships')->where('position_id', $staff)->count());

        $history = DB::table('position_histories')->where('position_id', $staff)->orderBy('id')->get();
        $this->assertSame(['created', 'updated'], $history->pluck('action')->all());
        $this->assertSame(['Posisi STAFF', 'Staff Senior'], json_decode($history[1]->changes, true)['title']);
    }

    public function test_hapus_diblokir_bila_masih_dipakai_dan_soft_delete(): void
    {
        $arch = $this->seedArchitecture();

        $this->delete('/organization/job-families/'.$arch['family'])->assertSessionHas('status', 'error');
        $this->assertNull(DB::table('job_families')->where('id', $arch['family'])->value('deleted_at'));

        $this->position($arch, 'BOSS');
        $boss = DB::table('positions')->where('code', 'BOSS')->value('id');
        $this->position($arch, 'SUB', ['reports_to_solid' => $boss]);
        $this->delete('/organization/positions/'.$boss)->assertSessionHas('status', 'error');

        $sub = DB::table('positions')->where('code', 'SUB')->value('id');
        $this->delete('/organization/positions/'.$sub)->assertSessionHas('status', 'success');
        $this->assertNotNull(DB::table('positions')->where('id', $sub)->value('deleted_at'));
        $this->get('/organization/positions')->assertDontSee('Posisi SUB');
        $this->delete('/organization/positions/'.$boss)->assertSessionHas('status', 'success');
    }

    public function test_isolasi_tenant_dan_referensi_lintas_tenant(): void
    {
        $arch = $this->seedArchitecture();
        $other = $this->makeTenant('TB');
        $otherFamily = DB::table('job_families')->insertGetId(['tenant_id' => $other, 'uuid' => (string) Str::uuid7(), 'code' => 'SECRET', 'name' => 'Rahasia B', 'created_at' => now()]);

        $this->get('/organization/job-families')->assertDontSee('Rahasia B');
        $this->get('/organization/job-families/'.$otherFamily.'/edit')->assertNotFound();
        $this->put('/organization/job-families/'.$otherFamily, ['code' => 'X', 'name' => 'Bajak'])->assertNotFound();
        $this->post('/organization/jobs', ['code' => 'J2', 'title' => 'Pakai family B', 'job_family_id' => $otherFamily])->assertSessionHasErrors('job_family_id');
        $this->assertSame('Rahasia B', DB::table('job_families')->where('id', $otherFamily)->value('name'));
    }

    public function test_hak_akses_per_peran(): void
    {
        $employee = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE));
        $this->actingAsSximo($employee, Roles::id(Roles::EMPLOYEE))->get('/organization/jobs')->assertRedirect('dashboard');

        $hrbp = $this->makeUser($this->tenant, Roles::id(Roles::HRBP));
        $this->actingAsSximo($hrbp, Roles::id(Roles::HRBP))->get('/organization/jobs')->assertOk()->assertDontSee('Tambah</a>', false);
        $this->get('/organization/jobs/create')->assertRedirect('dashboard');
        $this->post('/organization/job-families', ['code' => 'NOPE', 'name' => 'x'])->assertRedirect('dashboard');
        $this->assertFalse(DB::table('job_families')->where('code', 'NOPE')->exists());
    }

    public function test_riwayat_unit_tercatat_saat_disimpan(): void
    {
        $this->grantModule('unit', Roles::id(Roles::TENANT_ADMIN), ['is_global', 'is_view', 'is_add', 'is_edit', 'is_detail']);
        $unit = DB::table('unit')->where('id_unit', $this->unit)->first();

        $response = $this->post('/unit', [
            'action_task' => 'save', 'id_unit' => $unit->id_unit, 'parent_id' => '',
            'unit_name' => 'Divisi Kredit Konsumer', 'unit_code' => $unit->unit_code, 'slug_unit' => $unit->slug_unit,
            'id_corporate' => $unit->id_corporate, 'id_unit_category' => $unit->id_unit_category, 'id_unit_type' => $unit->id_unit_type,
            'unit_addres' => '-', 'unit_explan' => '-', 'status' => 1,
        ])->json();

        $this->assertSame('success', $response['status'], json_encode($response));
        $history = DB::table('organization_histories')->where('unit_id', $this->unit)->first();
        $this->assertSame('updated', $history->action);
        $this->assertSame(['Divisi Kredit', 'Divisi Kredit Konsumer'], json_decode($history->changes, true)['unit_name']);
    }
}
