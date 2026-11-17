<?php

namespace Tests\Feature\Executive;

use App\Domain\Organization\Services\OrgUnitClosure;
use App\Http\Middleware\NormalizeExecutiveDashboardPath;
use App\Support\Security\Roles;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/** Ringkasan Eksekutif & analitik SDM: angka, hak akses, data scope, isolasi tenant. */
class ExecutiveDashboardTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $company;

    /** @var array<string,int> */
    private array $units = [];

    /** @var array<string,int> */
    private array $positions = [];

    /** @var array<string,int> */
    private array $grades = [];

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->tenant = $this->makeTenant('TA');
        $this->company = $this->makeCompany($this->tenant, 'A1');
        $this->units['div'] = $this->makeUnit($this->tenant, 'Divisi Kredit', null, $this->company);
        $this->units['lain'] = $this->makeUnit($this->tenant, 'Divisi Lain', null, $this->company);
        OrgUnitClosure::rebuild($this->tenant);

        $level = $this->insert('job_levels', ['code' => 'L1', 'name' => 'Staff', 'rank' => 1]);
        foreach (['G1' => 1, 'G2' => 2] as $code => $rank) {
            $this->grades[$code] = $this->insert('job_grades', ['job_level_id' => $level, 'code' => $code, 'name' => 'Grade '.$rank, 'rank' => $rank]);
        }
        $family = $this->insert('job_families', ['code' => 'F', 'name' => 'Finance']);
        $job = $this->insert('jobs_catalog', ['code' => 'J', 'title' => 'Analyst', 'job_family_id' => $family]);
        foreach ([['HEAD', 'div', 'G2', 1], ['ANL', 'div', 'G1', 3], ['SNR', 'div', 'G2', 1], ['LAIN', 'lain', 'G1', 2]] as [$code, $unit, $grade, $hc]) {
            $this->positions[$code] = $this->insert('positions', ['code' => $code, 'title' => 'Posisi '.$code, 'company_id' => $this->company,
                'unit_id' => $this->units[$unit], 'job_id' => $job, 'grade_id' => $this->grades[$grade], 'status' => 'active', 'headcount' => $hc, 'effective_from' => '2020-01-01']);
        }

        $this->hr = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
    }

    private function insert(string $table, array $data): int
    {
        return DB::table($table)->insertGetId($data + ['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'created_at' => now()]);
    }

    private function employee(string $name, string $position, string $joined, string $gender = 'F', ?User $user = null): int
    {
        $id = $this->insert('employees', ['company_id' => $this->company, 'user_id' => $user?->id, 'employee_number' => 'E'.Str::upper(Str::random(5)),
            'full_name' => $name, 'gender' => $gender, 'employment_status' => 'pkwtt', 'join_date' => $joined, 'status' => 'active']);
        $this->insert('employee_assignments', ['employee_id' => $id, 'position_id' => $this->positions[$position], 'type' => 'primary', 'effective_from' => $joined]);

        return $id;
    }

    private function terminate(int $employee, string $date): void
    {
        DB::table('employees')->where('id', $employee)->update(['status' => 'terminated', 'terminated_at' => $date]);
        DB::table('employee_assignments')->where('employee_id', $employee)->whereNull('effective_to')->update(['effective_to' => $date]);
        DB::table('employee_terminations')->insert(['tenant_id' => $this->tenant, 'employee_id' => $employee, 'termination_date' => $date,
            'type' => 'resign', 'reason' => 'Pindah kota', 'state' => 'processed', 'created_at' => now()]);
    }

    /** Skenario Oktober 2026: 4 aktif awal bulan, 1 bergabung, 1 keluar, 1 naik grade. */
    private function scenario(): array
    {
        $head = $this->makeUser($this->tenant, Roles::id(Roles::DEPARTMENT_HEAD));
        $ids = [
            'head' => $this->employee('Kepala', 'HEAD', '2020-01-06', 'M', $head),
            'rina' => $this->employee('Rina', 'ANL', '2021-02-01', 'F'),
            'budi' => $this->employee('Budi', 'ANL', '2022-03-01', 'M'),
            'sari' => $this->employee('Sari', 'LAIN', '2023-04-01', 'F'),
            'baru' => $this->employee('Pegawai Baru', 'ANL', '2026-10-15', 'F'),
        ];
        $this->terminate($ids['budi'], '2026-10-20');
        // Rina naik dari ANL (G1) ke SNR (G2) per 1 Oktober
        DB::table('employee_assignments')->where('employee_id', $ids['rina'])->update(['effective_to' => '2026-09-30']);
        $this->insert('employee_assignments', ['employee_id' => $ids['rina'], 'position_id' => $this->positions['SNR'], 'type' => 'primary', 'effective_from' => '2026-10-01']);

        return [$head, $ids];
    }

    public function test_ringkasan_menghitung_headcount_mutasi_dan_posisi_terbuka(): void
    {
        $this->scenario();
        $response = $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN))->get('/executive/dashboard?month=2026-10')->assertOk();
        $data = $response->viewData('headcount');

        $this->assertSame(4, $data, 'aktif akhir Oktober: kepala, rina, sari, pegawai baru');
        $response->assertViewHas('hires', 1)->assertViewHas('terminations', 1)->assertViewHas('female', 3)->assertViewHas('male', 1);
        // Kapasitas: HEAD 1 + ANL 3 + SNR 1 (div) dan LAIN 2; terisi: HEAD 1, ANL 1 (baru), SNR 1, LAIN 1 → terbuka 2 + 1
        $this->assertSame(3, $response->viewData('openTotal'));
    }

    public function test_infografis_tren_kpi_turunan_dan_status_historis(): void
    {
        [, $ids] = $this->scenario();
        // Pegawai baru diangkat tetap per 5 November: pada laporan Oktober statusnya masih kontrak
        DB::table('employees')->where('id', $ids['baru'])->update(['employment_status' => 'pkwtt']);
        DB::table('employee_status_histories')->insert(['tenant_id' => $this->tenant, 'employee_id' => $ids['baru'], 'field' => 'employment_status',
            'old_value' => 'pkwt', 'new_value' => 'pkwtt', 'effective_date' => '2026-11-05', 'created_at' => now()]);

        $response = $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN))->get('/executive/dashboard?month=2026-10')->assertOk();
        $employment = $response->viewData('employment');
        $this->assertSame([3, 1], [$employment['pkwtt']['count'], $employment['pkwt']['count']]);

        // Akhir September: kepala, rina, budi, sari = 4; Oktober: +1 baru, −1 budi = 4
        $trend = $response->viewData('trend');
        $this->assertCount(12, $trend);
        $this->assertSame(['2026-09', 4, 0, 0], [$trend[10]['ym'], $trend[10]['headcount'], $trend[10]['hires'], $trend[10]['terminations']]);
        $this->assertSame(['2026-10', 4, 1, 1], [$trend[11]['ym'], $trend[11]['headcount'], $trend[11]['hires'], $trend[11]['terminations']]);
        $response->assertViewHas('prevHeadcount', 4)->assertViewHas('turnoverRate', 25.0);
        // Kapasitas 7, terisi: HEAD 1 + ANL 1 + SNR 1 + LAIN 1 = 4
        $response->assertViewHas('capacityTotal', 7)->assertViewHas('filledTotal', 4)->assertViewHas('fillRate', 57.1);
        $response->assertSee('Sorotan')->assertSee('data-chart', false);
    }

    public function test_analitik_turnover_mobilitas_dan_halaman_lain(): void
    {
        $this->scenario();
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN));
        $turnover = $this->get('/executive/analytics/turnover?month=2026-10')->assertOk()->viewData('metrics');
        $this->assertSame([4, 1, 4], [$turnover[0][1], $turnover[1][1], $turnover[2][1]]);
        $this->assertSame('25%', $turnover[3][1], '1 ÷ rata-rata (4+4)/2');

        $promotions = $this->get('/executive/analytics/promotions?month=2026-10')->assertOk()->viewData('metrics');
        $this->assertSame(1, $promotions[0][1], 'Rina G1 → G2');

        foreach (['deib', 'workforce', 'development'] as $report) {
            $this->get('/executive/analytics/'.$report.'?month=2026-10')->assertOk();
        }
    }

    public function test_infografis_analitik_tren_jalur_grade_dan_representasi(): void
    {
        $this->scenario();
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN));

        $turnover = $this->get('/executive/analytics/turnover?month=2026-10')->assertOk()->assertSee('Turnover bulanan')->assertSee('data-chart', false);
        $trend = $turnover->viewData('trend');
        $this->assertCount(12, $trend);
        $this->assertSame(['2026-10', 1, 25.0], [$trend[11]['ym'], $trend[11]['terminations'], $trend[11]['rate']]);
        $turnover->assertViewHas('rate', 25.0)->assertViewHas('retention', 75.0)->assertViewHas('opening', 4)->assertViewHas('closing', 4);
        $this->assertSame([['label' => 'Mengundurkan diri', 'value' => 1]], $turnover->viewData('rows')->all());

        $promotions = $this->get('/executive/analytics/promotions?month=2026-10')->assertOk()->assertSee('Jalur kenaikan grade');
        $this->assertSame([['from' => 'Grade 1', 'to' => 'Grade 2', 'value' => 1]], $promotions->viewData('transitions')->all());
        $this->assertSame(1, $promotions->viewData('trend')[11]['value']);
        $promotions->assertViewHas('yearTotal', 1);
        // Bulan berikutnya: promosi Oktober tetap ada di tren 12 bulan, tetapi bukan angka bulan laporan
        $next = $this->get('/executive/analytics/promotions?month=2026-11')->assertOk();
        $this->assertSame(0, $next->viewData('metrics')[0][1]);
        $next->assertViewHas('yearTotal', 1);

        $deib = $this->get('/executive/analytics/deib?month=2026-10')->assertOk()->assertSee('Representasi gender per tahap')->assertSee('75,0%');
        $rows = $deib->viewData('genderRows')->keyBy('key');
        $this->assertSame([3, 1, 0], [$rows['F']['headcount'], $rows['F']['hires'], $rows['F']['terminations']]);
        $this->assertSame([1, 0, 1], [$rows['M']['headcount'], $rows['M']['hires'], $rows['M']['terminations']]);

        $workforce = $this->get('/executive/analytics/workforce?month=2026-10')->assertOk()->assertSee('Kelompok usia');
        $workforce->assertViewHas('headcount', 4);
        $this->assertSame([['label' => 'Perempuan', 'value' => 3], ['label' => 'Laki-laki', 'value' => 1]], $workforce->viewData('gender')->all());
        $this->assertSame(['Divisi Kredit' => 3, 'Divisi Lain' => 1], $workforce->viewData('units')->pluck('value', 'label')->all());

        $this->get('/executive/analytics/development')->assertOk()->assertSee('Penyelesaian asesmen')->assertViewHas('completion', null);
        $this->get('/executive/analytics/rahasia')->assertNotFound();
    }

    public function test_kepala_departemen_melihat_terminasi_di_unitnya(): void
    {
        // Cakupan kepala divisi berasal dari posisi yang dijabatnya (penugasan pegawai), tanpa user_unit
        [$head] = $this->scenario();
        $response = $this->actingAsSximo($head, Roles::id(Roles::DEPARTMENT_HEAD))->get('/executive/dashboard?month=2026-10')->assertOk();

        // Cakupan kepala divisi = unit Divisi Kredit: kepala, rina, pegawai baru (Sari di unit lain tidak terlihat)
        $this->assertSame(3, $response->viewData('headcount'));
        $this->assertSame(1, $response->viewData('terminations'), 'Budi keluar dari unit ini pada Oktober');
    }

    public function test_hak_akses_dan_isolasi_tenant(): void
    {
        $this->scenario();
        $employee = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE));
        $this->actingAsSximo($employee, Roles::id(Roles::EMPLOYEE))->get('/executive/dashboard')->assertRedirect('dashboard');
        $this->get('/executive/analytics/turnover')->assertRedirect('dashboard');

        $other = $this->makeTenant('TB');
        $admin = $this->makeUser($other, Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($admin, Roles::id(Roles::TENANT_ADMIN))->get('/executive/dashboard?month=2026-10')->assertOk()
            ->assertViewHas('headcount', 0)->assertViewHas('terminations', 0)->assertViewHas('openTotal', 0);
    }

    public function test_url_bergaris_miring_ganda_dialihkan_ke_host_yang_sama(): void
    {
        // Request::create() membaca "//host" sebagai host, jadi middleware diuji langsung dengan REQUEST_URI mentah
        $middleware = new NormalizeExecutiveDashboardPath;
        $handle = function (string $uri, string $method = 'GET') use ($middleware) {
            $request = Request::create('/', $method);
            $request->server->set('REQUEST_URI', $uri);

            return $middleware->handle($request, fn () => response('lanjut'));
        };

        $response = $handle('//executive/dashboard?month=2026-10');
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/executive/dashboard?month=2026-10', $response->headers->get('Location'));
        // Bukan open redirect: "//evil.example/x" menjadi path lokal di host yang sama
        $this->assertSame('/evil.example/x', $handle('//evil.example/x')->headers->get('Location'));
        // URL normal & metode selain GET diteruskan apa adanya
        $this->assertSame('lanjut', $handle('/executive/dashboard')->getContent());
        $this->assertSame('lanjut', $handle('//executive/dashboard', 'POST')->getContent());
    }

    public function test_bulan_tidak_valid_jatuh_ke_bulan_berjalan(): void
    {
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN))->get('/executive/dashboard?month=2026-13')->assertOk()
            ->assertViewHas('month', now()->format('Y-m'));
        $this->get('/executive/dashboard?month[]=x')->assertOk();
    }
}
