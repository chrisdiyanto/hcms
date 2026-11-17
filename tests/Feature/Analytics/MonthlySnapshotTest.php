<?php

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\MonthlySnapshot;
use App\Domain\Organization\Services\OrgUnitClosure;
use App\Support\Security\DataScope;
use App\Support\Security\Roles;
use App\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/** Snapshot analitik bulanan (prasyarat P2 CBHRM): metrik aditif per unit, versi, cakupan, isolasi. */
class MonthlySnapshotTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $company;

    /** @var array<string,int> */
    private array $units = [];

    /** @var array<string,int> */
    private array $positions = [];

    /** @var array<string,int> */
    private array $ids = [];

    private User $head;

    private ?int $frameworkVersion = null;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->tenant = $this->makeTenant('TA');
        $this->company = $this->makeCompany($this->tenant, 'A1');
        $this->units['div'] = $this->makeUnit($this->tenant, 'Divisi Kredit', null, $this->company);
        $this->units['lain'] = $this->makeUnit($this->tenant, 'Divisi Lain', null, $this->company);
        OrgUnitClosure::rebuild($this->tenant);
        $family = $this->insert('job_families', ['code' => 'F', 'name' => 'Finance']);
        $job = $this->insert('jobs_catalog', ['code' => 'J', 'title' => 'Analyst', 'job_family_id' => $family]);
        foreach ([['HEAD', 'div'], ['ANL', 'div'], ['LAIN', 'lain']] as [$code, $unit]) {
            $this->positions[$code] = $this->insert('positions', ['code' => $code, 'title' => 'Posisi '.$code, 'company_id' => $this->company,
                'unit_id' => $this->units[$unit], 'job_id' => $job, 'status' => 'active', 'headcount' => 5, 'effective_from' => '2020-01-01']);
        }

        // Oktober 2026: kepala, rina, budi (keluar 20/10), sari (unit lain), pegawai baru (15/10)
        $this->head = $this->makeUser($this->tenant, Roles::id(Roles::DEPARTMENT_HEAD));
        $this->ids['head'] = $this->employee('Kepala', 'HEAD', '2020-01-06', 'M', $this->head);
        $this->ids['rina'] = $this->employee('Rina', 'ANL', '2021-02-01', 'F');
        $this->ids['budi'] = $this->employee('Budi', 'ANL', '2022-03-01', 'M');
        $this->ids['sari'] = $this->employee('Sari', 'LAIN', '2023-04-01', 'F');
        $this->ids['baru'] = $this->employee('Baru', 'ANL', '2026-10-15', 'F');
        DB::table('employees')->where('id', $this->ids['budi'])->update(['status' => 'terminated', 'terminated_at' => '2026-10-20']);
        DB::table('employee_assignments')->where('employee_id', $this->ids['budi'])->update(['effective_to' => '2026-10-20']);
        DB::table('employee_terminations')->insert(['tenant_id' => $this->tenant, 'employee_id' => $this->ids['budi'], 'termination_date' => '2026-10-20',
            'type' => 'resign', 'reason' => 'Pindah', 'state' => 'processed', 'created_at' => now()]);
    }

    private function insert(string $table, array $data): int
    {
        return DB::table($table)->insertGetId($data + ['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'created_at' => now()]);
    }

    private function employee(string $name, string $position, string $joined, string $gender, ?User $user = null): int
    {
        $id = $this->insert('employees', ['company_id' => $this->company, 'user_id' => $user?->id, 'employee_number' => 'E'.Str::upper(Str::random(5)),
            'full_name' => $name, 'gender' => $gender, 'employment_status' => 'pkwtt', 'join_date' => $joined, 'status' => 'active']);
        $this->insert('employee_assignments', ['employee_id' => $id, 'position_id' => $this->positions[$position], 'type' => 'primary', 'effective_from' => $joined]);

        return $id;
    }

    /** @return array<string, array<string, float>> unit (div|lain|null) => metric => nilai */
    private function metrics(int $snapshotId): array
    {
        $names = array_flip($this->units);
        $out = [];
        foreach (DB::table('analytics_unit_metrics')->where('snapshot_id', $snapshotId)->get() as $row) {
            $out[$row->unit_id === null ? 'null' : $names[$row->unit_id]][$row->metric] = (float) $row->value;
        }

        return $out;
    }

    private function fit(int $employee, string $position, float $jfi, string $band, string $at, bool $criticalGap): void
    {
        $fitId = DB::table('job_fit_history')->insertGetId(['tenant_id' => $this->tenant, 'employee_id' => $employee, 'position_id' => $this->positions[$position],
            'jfi' => $jfi, 'band' => $band, 'complete' => true, 'trigger' => 'assessment', 'computed_at' => $at, 'created_at' => now()]);
        $version = $this->frameworkVersion ??= $this->insert('competency_framework_versions', ['version' => 1, 'name' => 'v1', 'status' => 'published']);
        $category = DB::table('competency_categories')->insertGetId(['tenant_id' => $this->tenant, 'code' => 'C'.Str::random(4), 'name' => 'Core']);
        $competency = $this->insert('competencies', ['framework_version_id' => $version, 'category_id' => $category, 'code' => 'K'.Str::random(4), 'name' => 'Kompetensi']);
        DB::table('competency_gap_history')->insert(['tenant_id' => $this->tenant, 'job_fit_history_id' => $fitId, 'employee_id' => $employee,
            'position_id' => $this->positions[$position], 'competency_id' => $competency, 'competency_code' => 'K', 'required_level' => 4,
            'criticality' => 3, 'actual' => $criticalGap ? 2 : 4, 'gap' => $criticalGap ? -2 : 0]);
    }

    public function test_metrik_tenaga_kerja_kompetensi_dan_kinerja_per_unit(): void
    {
        // Kompetensi: JFI Rina Oktober (gap kritis) dihitung; JFI Sari November tidak masuk snapshot Oktober
        $this->fit($this->ids['rina'], 'ANL', 70, 'ready_later', '2026-10-10 09:00:00', true);
        $this->fit($this->ids['sari'], 'LAIN', 95, 'ready_now', '2026-11-05 09:00:00', false);
        // Kinerja: rating final Kepala disahkan September (dihitung), rating Rina disahkan November (belum)
        $period = $this->insert('performance_periods', ['code' => 'KIN-2026', 'name' => 'Kinerja 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'status' => 'closed']);
        foreach ([[$this->ids['head'], 92.5, 'exceeds', '2026-09-15 10:00:00'], [$this->ids['rina'], 80, 'meets', '2026-11-02 10:00:00']] as [$emp, $score, $band, $final]) {
            $this->insert('performance_ratings', ['period_id' => $period, 'employee_id' => $emp, 'final_score' => $score, 'band' => $band, 'status' => 'final',
                'published_at' => $final, 'finalized_at' => $final, 'appeal_deadline' => '2026-12-31']);
        }

        $this->travelTo('2026-11-01 01:30:00'); // dijalankan penjadwal pada tanggal 1
        $result = app(MonthlySnapshot::class)->generate($this->tenant, '2026-10');
        $this->assertFalse($result['skipped']);
        $m = $this->metrics($result['snapshot_id']);

        $this->assertSame(['headcount' => 3.0, 'female' => 2.0, 'male' => 1.0, 'hires' => 1.0, 'terminations' => 1.0],
            array_intersect_key($m['div'], array_flip(['headcount', 'female', 'male', 'hires', 'terminations'])));
        $this->assertSame(1.0, $m['lain']['headcount']);
        $this->assertSame([1.0, 70.0, 1.0, 1.0], [$m['div']['jfi_n'], $m['div']['jfi_sum'], $m['div']['jfi_ready_later'], $m['div']['critical_gap_employees']]);
        $this->assertArrayNotHasKey('jfi_n', $m['lain'], 'JFI November tidak masuk snapshot Oktober');
        $this->assertSame([1.0, 92.5, 1.0], [$m['div']['performance_rated'], $m['div']['performance_score_sum'], $m['div']['performance_band:exceeds']]);
        $this->assertArrayNotHasKey('performance_band:meets', $m['div']);

        $snapshot = DB::table('analytics_snapshots')->find($result['snapshot_id']);
        $this->assertSame(['2026-10-31', 1, MonthlySnapshot::DEFINITIONS_VERSION, 0], [$snapshot->as_of, (int) $snapshot->version, (int) $snapshot->definitions_version, (int) $snapshot->reconstructed]);
    }

    public function test_deret_waktu_menghormati_cakupan_data_dan_isolasi_tenant(): void
    {
        $snapshots = app(MonthlySnapshot::class);
        $snapshots->generate($this->tenant, '2026-09');
        $snapshots->generate($this->tenant, '2026-10');

        $all = MonthlySnapshot::series($this->tenant, ['headcount', 'hires', 'terminations'], '2026-09', '2026-10',
            DataScope::for($this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN))->id, Roles::id(Roles::TENANT_ADMIN)));
        $this->assertSame(['2026-09' => ['headcount' => 4.0], '2026-10' => ['headcount' => 4.0, 'hires' => 1.0, 'terminations' => 1.0]], $all);

        // Kepala divisi (cakupan dari posisinya) hanya menjumlahkan unit Divisi Kredit
        $scoped = MonthlySnapshot::series($this->tenant, ['headcount'], '2026-10', '2026-10', $this->asTenant($this->tenant, fn () => DataScope::for($this->head->id, Roles::id(Roles::DEPARTMENT_HEAD))));
        $this->assertSame(['2026-10' => ['headcount' => 3.0]], $scoped);
        // Cakupan "own" tidak mendapat agregat
        $own = MonthlySnapshot::series($this->tenant, ['headcount'], '2026-10', '2026-10', DataScope::for($this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE))->id, Roles::id(Roles::EMPLOYEE)));
        $this->assertSame([], $own);

        // Tenant lain: snapshot sendiri, tidak melihat angka tenant ini
        $other = $this->makeTenant('TB');
        $snapshots->generate($other, '2026-10');
        $this->assertSame(0, DB::table('analytics_unit_metrics')->where('tenant_id', $other)->count());
        $this->assertSame([], MonthlySnapshot::series($other, ['headcount'], '2026-10', '2026-10', DataScope::for($this->makeUser($other, Roles::id(Roles::TENANT_ADMIN))->id, Roles::id(Roles::TENANT_ADMIN))));
    }

    public function test_versi_snapshot_tidak_menimpa_dan_perintah_artisan(): void
    {
        $snapshots = app(MonthlySnapshot::class);
        $first = $snapshots->generate($this->tenant, '2026-10');
        $this->assertTrue($snapshots->generate($this->tenant, '2026-10')['skipped'], 'tanpa --force bulan yang sudah ada dilewati');

        // Data berubah lalu dibuat ulang: versi 2, versi 1 tetap tersimpan tapi superseded
        DB::table('employees')->where('id', $this->ids['sari'])->update(['gender' => 'M']);
        $second = $snapshots->generate($this->tenant, '2026-10', true);
        $this->assertSame(2, $second['version']);
        $this->assertNotNull(DB::table('analytics_snapshots')->find($first['snapshot_id'])->superseded_at);
        $this->assertSame(1.0, $this->metrics($first['snapshot_id'])['lain']['female'], 'angka versi lama tidak ditimpa');
        $admin = DataScope::for($this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN))->id, Roles::id(Roles::TENANT_ADMIN));
        $this->assertSame(['2026-10' => ['female' => 2.0]], MonthlySnapshot::series($this->tenant, ['female'], '2026-10', '2026-10', $admin));
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'analytics_snapshots')->exists());

        try {
            DB::table('analytics_unit_metrics')->where('snapshot_id', $second['snapshot_id'])->update(['value' => 999]);
            $this->fail('UPDATE metrik seharusnya ditolak');
        } catch (QueryException $e) {
            $this->assertStringContainsString('tidak dapat diubah', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $snapshots->generate($this->tenant, now()->format('Y-m'));
    }

    public function test_perintah_membangun_rentang_dan_menandai_rekonstruksi(): void
    {
        // Oktober dibuat oleh penjadwal tepat waktu (3 November); Agustus–September dibangun ulang belakangan
        $this->travelTo('2026-11-03 01:30:00');
        $this->artisan('hcms:analytics-snapshot', ['--tenant' => 'TA'])->assertExitCode(0);
        $this->travelBack();
        $this->artisan('hcms:analytics-snapshot', ['--from' => '2026-08', '--period' => '2026-10', '--tenant' => 'TA'])->assertExitCode(0);
        $rows = DB::table('analytics_snapshots')->where('tenant_id', $this->tenant)->orderBy('period')->get();
        $this->assertSame(['2026-08', '2026-09', '2026-10'], $rows->pluck('period')->all());
        // Agustus & September dibuat > 7 hari setelah akhir bulan → rekonstruksi; Oktober tidak dibuat ulang (sudah ada)
        $this->assertSame([1, 1, 0], $rows->map(fn ($r) => (int) $r->reconstructed)->all());
        $this->artisan('hcms:analytics-snapshot', ['--period' => '2026-13'])->assertExitCode(1);
    }
}
