<?php

namespace Tests\Feature\Privacy;

use App\Support\Privacy\SmallGroups;
use App\Support\Security\Roles;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/** K9 / P5: kelompok 1–4 orang disamarkan hanya bagi pengguna tanpa hak membuka data individual. */
class SmallGroupsTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private User $hr;

    private User $executive;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->tenant = $this->makeTenant('TA');
        $company = $this->makeCompany($this->tenant, 'A1');
        $unit = $this->makeUnit($this->tenant, 'Divisi Kecil', null, $company);
        $family = $this->insert('job_families', ['code' => 'F', 'name' => 'Finance']);
        $job = $this->insert('jobs_catalog', ['code' => 'J', 'title' => 'Analis', 'job_family_id' => $family]);
        $position = $this->insert('positions', ['code' => 'P', 'title' => 'Analis', 'company_id' => $company, 'unit_id' => $unit,
            'job_id' => $job, 'status' => 'active', 'headcount' => 10, 'effective_from' => '2020-01-01']);
        // 3 perempuan, 1 laki-laki: semua kelompok gender < 5
        foreach (['F', 'F', 'F', 'M'] as $i => $gender) {
            $id = $this->insert('employees', ['company_id' => $company, 'employee_number' => 'E'.$i, 'full_name' => 'Pegawai '.$i, 'gender' => $gender,
                'employment_status' => 'pkwtt', 'join_date' => '2021-01-04', 'status' => 'active']);
            $this->insert('employee_assignments', ['employee_id' => $id, 'position_id' => $position, 'type' => 'primary', 'effective_from' => '2021-01-04']);
        }

        $this->hr = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        // Eksekutif tanpa hak membuka data pegawai individual (izin modul Pegawai dicabut admin)
        $this->executive = $this->makeUser($this->tenant, Roles::id(Roles::EXECUTIVE));
        DB::table('tb_groups')->where('group_id', Roles::id(Roles::EXECUTIVE))->update(['data_scope' => 'all']);
        Roles::flushCache();
        $employeesModule = DB::table('tb_module')->where('module_name', 'employees')->value('module_id');
        DB::table('tb_groups_access')->where('group_id', Roles::id(Roles::EXECUTIVE))->where('module_id', $employeesModule)
            ->update(['access_data' => json_encode(['is_view' => '0', 'is_detail' => '0'])]);
    }

    private function insert(string $table, array $data): int
    {
        return DB::table($table)->insertGetId($data + ['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'created_at' => now()]);
    }

    public function test_aturan_penyamaran(): void
    {
        $this->assertSame([false, true, true, false, false], array_map([SmallGroups::class, 'hidden'], [0, 1, 4, 5, null]));
        $this->assertSame(['< 5', '0', '5', '3'], [SmallGroups::count(4, true), SmallGroups::count(0, true), SmallGroups::count(5, true), SmallGroups::count(3, false)]);
        $this->assertSame([null, 0, 12], SmallGroups::chartValues([2, 0, 12], true));
        $this->assertSame(['—', '75,0'], [SmallGroups::derived(3, true, '75,0'), SmallGroups::derived(3, false, '75,0')]);
    }

    public function test_hr_melihat_angka_asli_eksekutif_tanpa_akses_baris_melihat_kurang_dari_5(): void
    {
        $month = now()->format('Y-m');
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN))->get('/executive/analytics/deib?month='.$month)->assertOk()
            ->assertSee('3 perempuan · 1 laki-laki', false)->assertSee('75,0%')->assertDontSee('melindungi privasi');

        $response = $this->actingAsSximo($this->executive, Roles::id(Roles::EXECUTIVE))->get('/executive/analytics/deib?month='.$month)->assertOk();
        $response->assertSee('&lt; 5 perempuan · &lt; 5 laki-laki', false)->assertSee('melindungi privasi')->assertDontSee('75,0%');
        // Nilai kecil tidak ikut terkirim di data grafik
        $this->assertStringNotContainsString('&quot;values&quot;:[3,', $response->getContent());
        $this->assertStringContainsString('&quot;values&quot;:[null,', $response->getContent());

        $this->get('/executive/analytics/workforce?month='.$month)->assertOk()->assertSee('&lt; 5', false)->assertSee('melindungi privasi');
        $this->get('/executive/dashboard?month='.$month)->assertOk()->assertSee('&lt; 5', false)->assertSee('melindungi privasi');
    }

    public function test_nol_dan_kelompok_5_atau_lebih_tidak_disamarkan(): void
    {
        $company = (int) DB::table('corporate')->where('tenant_id', $this->tenant)->value('id_corporate');
        $position = (int) DB::table('positions')->where('tenant_id', $this->tenant)->value('id');
        $id = $this->insert('employees', ['company_id' => $company, 'employee_number' => 'E9', 'full_name' => 'Pegawai 9', 'gender' => 'F',
            'employment_status' => 'pkwtt', 'join_date' => '2021-01-04', 'status' => 'active']);
        $this->insert('employee_assignments', ['employee_id' => $id, 'position_id' => $position, 'type' => 'primary', 'effective_from' => '2021-01-04']);

        // 5 pegawai aktif: total tampil, perempuan 4 tetap disamarkan, pegawai baru 0 tampil 0
        $this->actingAsSximo($this->executive, Roles::id(Roles::EXECUTIVE))->get('/executive/analytics/deib?month='.now()->format('Y-m'))->assertOk()
            ->assertSee('&lt; 5 perempuan', false)->assertSee('<div class="exec-hero-stat-value tabular-nums">5</div>', false)
            ->assertSee('<div class="exec-hero-stat-value tabular-nums">0</div>', false);
    }
}
