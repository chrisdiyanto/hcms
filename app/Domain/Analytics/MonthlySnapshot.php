<?php

namespace App\Domain\Analytics;

use App\Support\Audit\AuditLog;
use App\Support\Security\DataScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Snapshot analitik bulanan per tenant × unit (prasyarat P2 laporan CBHRM).
 *
 * Semua metrik aditif: jumlah orang atau jumlah nilai (mis. jfi_sum + jfi_n), sehingga total
 * organisasi/cakupan data = SUM antar-unit, dan rata-rata = sum ÷ n saat dibaca. Unit = unit
 * posisi primer per tanggal yang relevan (akhir bulan; tanggal bergabung untuk hires; tanggal
 * terminasi untuk terminations). Dihitung sebagai sistem, tanpa data scope pengguna — cakupan
 * diterapkan saat membaca (series()).
 *
 * Bulan lampau dapat dibangun ulang dari data efektif-bertanggal (penugasan, terminasi, riwayat
 * kompetensi P1, rating final); snapshot seperti itu ditandai reconstructed.
 */
class MonthlySnapshot
{
    /** Naikkan bila definisi metrik berubah; tersimpan di setiap snapshot. */
    public const DEFINITIONS_VERSION = 1;

    public const METRICS = [
        'headcount' => 'Pegawai aktif per akhir bulan',
        'female' => 'Pegawai aktif perempuan',
        'male' => 'Pegawai aktif laki-laki',
        'hires' => 'Pegawai bergabung selama bulan (unit per tanggal bergabung)',
        'terminations' => 'Terminasi diproses selama bulan (unit per tanggal terminasi)',
        'competency_valid' => 'Pegawai aktif dengan ≥ 1 skor kompetensi yang masih berlaku',
        'jfi_n' => 'Pegawai aktif yang memiliki JFI (perhitungan terakhir ≤ akhir bulan)',
        'jfi_sum' => 'Jumlah JFI (rata-rata = jfi_sum ÷ jfi_n)',
        'jfi_ready_now' => 'JFI band siap sekarang',
        'jfi_ready_1_2' => 'JFI band siap 1–2 tahun',
        'jfi_ready_later' => 'JFI band siap > 2 tahun',
        'critical_gap_employees' => 'Pegawai dengan ≥ 1 kompetensi kritikalitas 3 bergap negatif (definisi sementara sampai kamus metrik P4)',
        'performance_rated' => 'Pegawai aktif dengan rating kinerja final terakhir (rated)',
        'performance_score_sum' => 'Jumlah skor final (rata-rata = sum ÷ performance_rated)',
        // + performance_band:{kode band} per band rating final
    ];

    private const RECONSTRUCTED_AFTER_DAYS = 7;

    /**
     * Buat snapshot satu tenant untuk satu bulan.
     *
     * @return array{snapshot_id: ?int, skipped: bool, version: int, rows: int}
     */
    public function generate(int $tenantId, string $period, bool $force = false, ?int $userId = null): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new InvalidArgumentException('Periode harus berformat YYYY-MM.');
        }
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfDay();
        $asOf = $start->copy()->endOfMonth();
        if ($asOf->isFuture()) {
            throw new InvalidArgumentException("Bulan {$period} belum berakhir.");
        }

        $current = DB::table('analytics_snapshots')->where('tenant_id', $tenantId)->where('period', $period)->whereNull('superseded_at')->first();
        if ($current && ! $force) {
            return ['snapshot_id' => (int) $current->id, 'skipped' => true, 'version' => (int) $current->version, 'rows' => (int) $current->row_count];
        }

        $rows = $this->compute($tenantId, $start->toDateString(), $asOf->toDateString());

        return DB::transaction(function () use ($tenantId, $period, $asOf, $current, $rows, $userId) {
            $version = (int) DB::table('analytics_snapshots')->where('tenant_id', $tenantId)->where('period', $period)->max('version') + 1;
            if ($current) {
                DB::table('analytics_snapshots')->where('id', $current->id)->update(['superseded_at' => now()]);
            }
            $id = DB::table('analytics_snapshots')->insertGetId([
                'tenant_id' => $tenantId, 'period' => $period, 'as_of' => $asOf->toDateString(), 'version' => $version,
                'definitions_version' => self::DEFINITIONS_VERSION,
                'reconstructed' => now()->greaterThan($asOf->copy()->addDays(self::RECONSTRUCTED_AFTER_DAYS)),
                'row_count' => count($rows), 'generated_at' => now(), 'generated_by' => $userId,
            ]);
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('analytics_unit_metrics')->insert(array_map(fn ($r) => $r + ['tenant_id' => $tenantId, 'snapshot_id' => $id], $chunk));
            }
            if ($current) {
                AuditLog::security('analytics_snapshots', $id, "Snapshot analitik {$period} dibuat ulang (versi {$version}); versi {$current->version} digantikan");
            }

            return ['snapshot_id' => $id, 'skipped' => false, 'version' => $version, 'rows' => count($rows)];
        });
    }

    /**
     * Deret waktu metrik untuk cakupan data: [period => [metric => nilai]] dari snapshot terkini
     * (bukan superseded). Unit di luar cakupan tidak dijumlahkan; cakupan "own" tidak mendapat agregat.
     *
     * @param  string[]  $metrics
     * @return array<string, array<string, float>>
     */
    public static function series(int $tenantId, array $metrics, string $from, string $to, ?DataScope $scope = null): array
    {
        $scope ??= DataScope::current();
        $query = DB::table('analytics_unit_metrics as m')->join('analytics_snapshots as s', 's.id', '=', 'm.snapshot_id')
            ->where('s.tenant_id', $tenantId)->whereNull('s.superseded_at')->whereBetween('s.period', [$from, $to])
            // "performance_band:*" = semua metrik berawalan performance_band:
            ->where(function ($q) use ($metrics) {
                $q->whereIn('m.metric', $metrics);
                foreach (array_filter($metrics, fn ($m) => str_ends_with($m, ':*')) as $prefix) {
                    $q->orWhere('m.metric', 'like', substr($prefix, 0, -1).'%');
                }
            });
        if (! $scope->isAll()) {
            $scope->unitIds === [] ? $query->whereRaw('1 = 0') : $query->whereIn('m.unit_id', $scope->unitIds);
        }

        $out = [];
        foreach ($query->groupBy('s.period', 'm.metric')->orderBy('s.period')->selectRaw('s.period, m.metric, SUM(m.value) as total')->get() as $row) {
            $out[$row->period][$row->metric] = (float) $row->total;
        }

        return $out;
    }

    // ------------------------------------------------------------- perhitungan

    /** @return array<int, array{unit_id: ?int, metric: string, value: float}> */
    public function compute(int $tenantId, string $start, string $asOf): array
    {
        $acc = [];
        $add = function (iterable $rows) use (&$acc) {
            foreach ($rows as $row) {
                foreach ((array) $row as $metric => $value) {
                    if ($metric === 'unit_id' || $value === null || (float) $value == 0.0) {
                        continue;
                    }
                    $key = ($row->unit_id ?? 'null').'|'.$metric;
                    $acc[$key] = ($acc[$key] ?? 0) + (float) $value;
                }
            }
        };

        $active = $this->activeAt($tenantId, $asOf);
        $end = $asOf.' 23:59:59';

        $add(DB::select("SELECT x.unit_id, COUNT(*) AS headcount, SUM(x.gender = 'F') AS female, SUM(x.gender = 'M') AS male FROM ({$active['sql']}) x GROUP BY x.unit_id", $active['bindings']));

        $add(DB::select('SELECT '.$this->unitAt('e.id', 'e.join_date').' AS unit_id, COUNT(*) AS hires
            FROM employees e WHERE e.tenant_id = ? AND e.deleted_at IS NULL AND e.join_date BETWEEN ? AND ? GROUP BY unit_id', [$tenantId, $start, $asOf]));
        $add(DB::select('SELECT '.$this->unitAt('t.employee_id', 't.termination_date').' AS unit_id, COUNT(*) AS terminations
            FROM employee_terminations t WHERE t.tenant_id = ? AND t.state = \'processed\' AND t.termination_date BETWEEN ? AND ? GROUP BY unit_id', [$tenantId, $start, $asOf]));

        // Kompetensi per akhir bulan dari riwayat P1: skor berlaku & JFI terakhir ≤ as_of
        $add(DB::select("SELECT x.unit_id, COUNT(*) AS competency_valid FROM ({$active['sql']}) x
            WHERE EXISTS (SELECT 1 FROM employee_competency_history h WHERE h.employee_id = x.employee_id AND h.assessed_at <= ? AND h.valid_until >= ?)
            GROUP BY x.unit_id", [...$active['bindings'], $asOf, $asOf]));
        $add(DB::select("SELECT x.unit_id, COUNT(*) AS jfi_n, SUM(f.jfi) AS jfi_sum,
                SUM(f.band = 'ready_now') AS jfi_ready_now, SUM(f.band = 'ready_1_2') AS jfi_ready_1_2, SUM(f.band = 'ready_later') AS jfi_ready_later,
                SUM(EXISTS (SELECT 1 FROM competency_gap_history g WHERE g.job_fit_history_id = f.id AND g.criticality = 3 AND g.gap < 0)) AS critical_gap_employees
            FROM ({$active['sql']}) x
            JOIN (SELECT h.*, ROW_NUMBER() OVER (PARTITION BY h.employee_id ORDER BY h.computed_at DESC, h.id DESC) AS rn
                  FROM job_fit_history h WHERE h.tenant_id = ? AND h.computed_at <= ?) f ON f.employee_id = x.employee_id AND f.rn = 1
            GROUP BY x.unit_id", [...$active['bindings'], $tenantId, $end]));

        // Rating kinerja final terakhir yang sudah disahkan per as_of (revisi tertinggi yang berlaku saat itu)
        $ratings = DB::select("SELECT x.unit_id, r.band, COUNT(*) AS n, SUM(r.final_score) AS score_sum
            FROM ({$active['sql']}) x
            JOIN (SELECT r.employee_id, r.band, r.final_score,
                         ROW_NUMBER() OVER (PARTITION BY r.employee_id ORDER BY p.end_date DESC, r.revision DESC) AS rn
                  FROM performance_ratings r JOIN performance_periods p ON p.id = r.period_id
                  WHERE r.tenant_id = ? AND r.status = 'final' AND r.rated = 1 AND r.finalized_at <= ?) r ON r.employee_id = x.employee_id AND r.rn = 1
            GROUP BY x.unit_id, r.band", [...$active['bindings'], $tenantId, $end]);
        foreach ($ratings as $r) {
            $add([(object) ['unit_id' => $r->unit_id, 'performance_rated' => $r->n, 'performance_score_sum' => $r->score_sum,
                'performance_band:'.($r->band ?? 'none') => $r->n]]);
        }

        $out = [];
        foreach ($acc as $key => $value) {
            [$unit, $metric] = explode('|', $key, 2);
            $out[] = ['unit_id' => $unit === 'null' ? null : (int) $unit, 'metric' => $metric, 'value' => round($value, 4)];
        }

        return $out;
    }

    /** Pegawai aktif per tanggal + unit posisi primer pada tanggal itu. */
    private function activeAt(int $tenantId, string $date): array
    {
        return [
            'sql' => 'SELECT e.id AS employee_id, e.gender, '.$this->unitAt('e.id', '?').' AS unit_id
                FROM employees e WHERE e.tenant_id = ? AND e.deleted_at IS NULL AND e.join_date <= ?
                AND (e.terminated_at IS NULL OR e.terminated_at > ?)',
            // unitAt() memakai placeholder tanggal dua kali
            'bindings' => [$date, $date, $tenantId, $date, $date],
        ];
    }

    /** Subquery skalar: unit posisi primer pegawai pada tanggal tertentu (kolom atau placeholder). */
    private function unitAt(string $employeeColumn, string $dateExpr): string
    {
        return "(SELECT MIN(p.unit_id) FROM employee_assignments a JOIN positions p ON p.id = a.position_id
            WHERE a.employee_id = {$employeeColumn} AND a.type = 'primary' AND a.effective_from <= {$dateExpr}
            AND (a.effective_to IS NULL OR a.effective_to >= {$dateExpr}))";
    }
}
