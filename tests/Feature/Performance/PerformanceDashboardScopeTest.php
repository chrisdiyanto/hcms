<?php

namespace Tests\Feature\Performance;

use App\Domain\Organization\Services\OrgUnitClosure;
use App\Support\Security\Roles;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/** Kriteria MVP 17: Dasbor Kinerja menampilkan capaian sesuai data scope peran. */
class PerformanceDashboardScopeTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $company;

    private int $job;

    /** @var array<string,int> */
    private array $units = [];

    private int $period;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->tenant = $this->makeTenant('TA');
        $this->company = $this->makeCompany($this->tenant, 'A1');
        $this->units['kredit'] = $this->makeUnit($this->tenant, 'Divisi Kredit', null, $this->company);
        $this->units['dana'] = $this->makeUnit($this->tenant, 'Divisi Dana', null, $this->company);
        OrgUnitClosure::rebuild($this->tenant);
        $family = $this->insert('job_families', ['code' => 'F', 'name' => 'Finance']);
        $this->job = $this->insert('jobs_catalog', ['code' => 'J', 'title' => 'Analis', 'job_family_id' => $family]);
        $this->period = $this->insert('performance_periods', ['code' => 'KIN-2026', 'name' => 'Kinerja 2026', 'start_date' => '2026-01-01',
            'end_date' => '2026-06-30', 'status' => 'closed']);
    }

    private function insert(string $table, array $data): int
    {
        return DB::table($table)->insertGetId($data + ['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'created_at' => now()]);
    }

    private function employee(string $unit, string $name, ?float $score, string $band, ?User $user = null): int
    {
        $position = $this->insert('positions', ['code' => 'P'.Str::upper(Str::random(5)), 'title' => 'Posisi '.$name, 'company_id' => $this->company,
            'unit_id' => $this->units[$unit], 'job_id' => $this->job, 'status' => 'active', 'headcount' => 1, 'effective_from' => '2020-01-01']);
        $id = $this->insert('employees', ['company_id' => $this->company, 'user_id' => $user?->id, 'employee_number' => 'E'.Str::upper(Str::random(5)),
            'full_name' => $name, 'employment_status' => 'pkwtt', 'join_date' => '2021-01-04', 'status' => 'active']);
        $this->insert('employee_assignments', ['employee_id' => $id, 'position_id' => $position, 'type' => 'primary', 'effective_from' => '2021-01-04']);
        if ($score !== null) {
            $this->insert('performance_ratings', ['period_id' => $this->period, 'employee_id' => $id, 'final_score' => $score, 'band' => $band,
                'status' => 'final', 'published_at' => '2026-07-15', 'finalized_at' => '2026-08-01', 'appeal_deadline' => '2026-07-29']);
        }

        return $id;
    }

    public function test_hr_melihat_semua_kepala_divisi_hanya_unitnya(): void
    {
        $head = $this->makeUser($this->tenant, Roles::id(Roles::DEPARTMENT_HEAD));
        $this->employee('kredit', 'Kepala Kredit', 92, 'outstanding', $head);
        $this->employee('kredit', 'Analis Kredit', 78, 'meets');
        $this->employee('dana', 'Analis Dana', 62, 'below');
        $this->employee('dana', 'Staf Dana', 55, 'unsatisfactory');

        $hr = $this->actingAsSximo($this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN)), Roles::id(Roles::TENANT_ADMIN))
            ->get('/executive/analytic/performance?period='.$this->period)->assertOk();
        $this->assertSame(4, $hr->viewData('scored'));
        $this->assertSame(71.75, (float) $hr->viewData('average'));
        $this->assertSame(['Divisi Kredit', 'Divisi Dana'], $hr->viewData('unitChart')['labels'], 'rata-rata unit, tertinggi lebih dulu');

        // Kepala Divisi Kredit (cakupan dari posisinya): hanya 2 pegawai unitnya, unit lain tidak muncul
        $scoped = $this->actingAsSximo($head, Roles::id(Roles::DEPARTMENT_HEAD))->get('/executive/analytic/performance?period='.$this->period)->assertOk();
        $this->assertSame(2, $scoped->viewData('scored'));
        $this->assertSame(85.0, (float) $scoped->viewData('average'));
        $this->assertSame(['Divisi Kredit'], $scoped->viewData('unitChart')['labels']);
        $this->assertSame(0, $scoped->viewData('riskCount'), 'band terbawah hanya ada di Divisi Dana');
        $scoped->assertDontSee('Divisi Dana');

        // Pegawai biasa tidak dapat membuka dasbor
        $this->actingAsSximo($this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE)), Roles::id(Roles::EMPLOYEE))
            ->get('/executive/analytic/performance')->assertRedirect('dashboard');
    }
}
