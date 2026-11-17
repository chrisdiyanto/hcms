<?php

namespace App\Models;

use App\Domain\Employee\Services\EmployeeScope;
use App\Support\Security\DataScope;
use App\Support\Tenancy\TenantScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Dashboard extends Sximo
{
    public const PERIOD_PRESETS = [
        '7d' => '7 Hari Terakhir',
        '30d' => '30 Hari Terakhir',
        'this_month' => 'Bulan Ini',
        'last_month' => 'Bulan Lalu',
        'this_year' => 'Tahun Ini',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Validasi dan tentukan rentang tanggal filter periode dashboard.
     */
    public function resolvePeriod(?string $period = null, ?string $startInput = null, ?string $endInput = null): array
    {
        $now = now();
        $selectedPeriod = is_string($period) && isset(self::PERIOD_PRESETS[$period]) ? $period : '30d';

        if (
            is_string($startInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startInput) &&
            is_string($endInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endInput)
        ) {
            try {
                $start = Carbon::createFromFormat('Y-m-d', $startInput)->startOfDay();
                $end = Carbon::createFromFormat('Y-m-d', $endInput)->endOfDay();
                if ($start->lte($end)) {
                    return [
                        'selected_period' => 'custom',
                        'start_date' => $start->toDateString(),
                        'end_date' => $end->toDateString(),
                        'label' => $start->translatedFormat('j M Y').' - '.$end->translatedFormat('j M Y'),
                        'options' => self::PERIOD_PRESETS,
                    ];
                }
            } catch (\Throwable) {
                // Gunakan preset default bila tanggal tidak valid
            }
        }

        [$start, $end] = match ($selectedPeriod) {
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
        };

        return [
            'selected_period' => $selectedPeriod,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'label' => $start->translatedFormat('j M Y').' - '.$end->translatedFormat('j M Y'),
            'options' => self::PERIOD_PRESETS,
        ];
    }

    /**
     * Ambil seluruh data real untuk halaman dashboard.
     */
    public function getDashboardData(string $startDate, string $endDate): array
    {
        return [
            'metrics' => $this->getTopMetrics($startDate, $endDate),
            'trendChart' => $this->getDailyTrend($startDate, $endDate),
            'unitChart' => $this->getUnitDistribution($endDate),
            'popularDocuments' => $this->getPopularItems($startDate, $endDate, 5),
            'visitBehavior' => $this->getVisitBehavior($startDate, $endDate),
            'accessMethods' => $this->getAccessMethods($startDate, $endDate),
            'securityAlerts' => $this->getSecurityAndSystemAlerts($startDate, $endDate),
        ];
    }

    /**
     * Empat kartu metrik utama di bagian atas dashboard.
     */
    public function getTopMetrics(string $startDate, string $endDate): array
    {
        $now = now();
        $today = $now->toDateString();
        $yesterday = $now->copy()->subDay()->toDateString();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();
        $lastMonthEnd = $now->copy()->startOfMonth()->subDay()->toDateString();

        // 1. Total Dokumen / Berkas & Pegawai Terdata
        [$totalDocs, $lastMonthDocs, $newDocsThisMonth, $docLabel] = $this->countDocumentsAndRecords($monthStart, $monthEnd, $lastMonthEnd, $endDate);
        $docTrendPct = $lastMonthDocs > 0 ? round((($totalDocs - $lastMonthDocs) / $lastMonthDocs) * 100, 1) : ($totalDocs > 0 ? 100.0 : 0.0);

        // 2. Pengunjung / Pengguna Aktif Hari Ini vs Kemarin
        $visitorsToday = $this->countDailyVisitors($today);
        $visitorsYesterday = $this->countDailyVisitors($yesterday);
        $visitorTrendPct = $visitorsYesterday > 0
            ? round((($visitorsToday - $visitorsYesterday) / $visitorsYesterday) * 100, 1)
            : ($visitorsToday > 0 ? 100.0 : 0.0);

        // 3. Rata-rata Durasi Sesi / Aktivitas (Menit) & Tren Mingguan
        $currentWeekStart = $now->copy()->subDays(6)->toDateString();
        $prevWeekStart = $now->copy()->subDays(13)->toDateString();
        $prevWeekEnd = $now->copy()->subDays(7)->toDateString();

        $avgDuration = $this->calculateAverageSessionMinutes($startDate, $endDate);
        $prevWeekDuration = $this->calculateAverageSessionMinutes($prevWeekStart, $prevWeekEnd);
        $currWeekDuration = $this->calculateAverageSessionMinutes($currentWeekStart, $today);
        $durationTrendPct = $prevWeekDuration > 0
            ? round((($currWeekDuration - $prevWeekDuration) / $prevWeekDuration) * 100, 1)
            : 0.0;

        return [
            'total_documents' => [
                'label' => $docLabel,
                'value' => $totalDocs,
                'formatted' => number_format($totalDocs, 0, ',', '.'),
                'trend_pct' => abs($docTrendPct),
                'trend_dir' => $docTrendPct >= 0 ? 'up' : 'down',
                'trend_note' => 'dari bulan lalu',
            ],
            'visitors_today' => [
                'label' => 'Pengunjung Hari Ini',
                'value' => $visitorsToday,
                'formatted' => number_format($visitorsToday, 0, ',', '.'),
                'trend_pct' => abs($visitorTrendPct),
                'trend_dir' => $visitorTrendPct >= 0 ? 'up' : 'down',
                'trend_note' => 'dari kemarin',
            ],
            'avg_duration' => [
                'label' => 'Rata-rata Durasi Akses',
                'value' => $avgDuration,
                'formatted' => number_format($avgDuration, 0, ',', '.').' Menit',
                'trend_pct' => abs($durationTrendPct),
                'trend_dir' => $durationTrendPct >= 0 ? 'up' : 'down',
                'trend_note' => 'tren mingguan',
            ],
            'new_this_month' => [
                'label' => $docLabel === 'Total Pegawai Aktif' ? 'Pegawai Baru (Bulan Ini)' : 'Data Baru (Bulan Ini)',
                'value' => $newDocsThisMonth,
                'formatted' => '+'.number_format($newDocsThisMonth, 0, ',', '.'),
            ],
        ];
    }

    /**
     * Hitung total dokumen/rekaman atau pegawai aktif berdasarkan ketersediaan data di sistem.
     */
    private function countDocumentsAndRecords(string $monthStart, string $monthEnd, string $lastMonthEnd, string $endDate): array
    {
        $ragTotal = 0;
        $ragLastMonth = 0;
        $ragNew = 0;

        if (Schema::hasTable('tb_rag_documents')) {
            $ragTotal += (int) DB::table('tb_rag_documents')->whereDate('created_at', '<=', $endDate)->count();
            $ragLastMonth += (int) DB::table('tb_rag_documents')->whereDate('created_at', '<=', $lastMonthEnd)->count();
            $ragNew += (int) DB::table('tb_rag_documents')->whereBetween('created_at', [$monthStart.' 00:00:00', $monthEnd.' 23:59:59'])->count();
        }

        if (Schema::hasTable('performance_evidences')) {
            $ragTotal += (int) TenantScope::table('performance_evidences')->whereDate('created_at', '<=', $endDate)->count();
            $ragLastMonth += (int) TenantScope::table('performance_evidences')->whereDate('created_at', '<=', $lastMonthEnd)->count();
            $ragNew += (int) TenantScope::table('performance_evidences')->whereBetween('created_at', [$monthStart.' 00:00:00', $monthEnd.' 23:59:59'])->count();
        }

        if (Schema::hasTable('employee_certifications')) {
            $ragTotal += (int) TenantScope::table('employee_certifications')->whereDate('created_at', '<=', $endDate)->count();
            $ragLastMonth += (int) TenantScope::table('employee_certifications')->whereDate('created_at', '<=', $lastMonthEnd)->count();
            $ragNew += (int) TenantScope::table('employee_certifications')->whereBetween('created_at', [$monthStart.' 00:00:00', $monthEnd.' 23:59:59'])->count();
        }

        if (Schema::hasTable('employees')) {
            $scope = DataScope::current();
            $empActive = (int) EmployeeScope::applyForPeriod(
                TenantScope::table('employees')
                    ->whereNull('employees.deleted_at')
                    ->where('employees.join_date', '<=', $endDate)
                    ->where(fn ($q) => $q->whereNull('employees.terminated_at')->orWhere('employees.terminated_at', '>', $endDate)),
                'employees.id',
                $endDate,
                $endDate,
                $scope
            )->count();

            $empLastMonth = (int) EmployeeScope::applyForPeriod(
                TenantScope::table('employees')
                    ->whereNull('employees.deleted_at')
                    ->where('employees.join_date', '<=', $lastMonthEnd)
                    ->where(fn ($q) => $q->whereNull('employees.terminated_at')->orWhere('employees.terminated_at', '>', $lastMonthEnd)),
                'employees.id',
                $lastMonthEnd,
                $lastMonthEnd,
                $scope
            )->count();

            $empNewThisMonth = (int) EmployeeScope::applyForPeriod(
                TenantScope::table('employees')
                    ->whereNull('employees.deleted_at')
                    ->whereBetween('employees.join_date', [$monthStart, $monthEnd]),
                'employees.id',
                $monthStart,
                $monthEnd,
                $scope
            )->count();

            if ($empActive > 0 || $ragTotal === 0) {
                return [$empActive, $empLastMonth, $empNewThisMonth, 'Total Pegawai Aktif'];
            }
        }

        return [$ragTotal, $ragLastMonth, $ragNew, 'Total Dokumen'];
    }

    /**
     * Hitung pengunjung/pengguna unik yang aktif pada satu tanggal.
     */
    private function countDailyVisitors(string $date): int
    {
        $start = $date.' 00:00:00';
        $end = $date.' 23:59:59';
        $userIds = collect();

        if (Schema::hasTable('login_histories')) {
            $q = DB::table('login_histories')
                ->where('event', 'login')
                ->whereBetween('created_at', [$start, $end]);
            if ($tenantId = TenantScope::tenantId()) {
                $q->where('tenant_id', $tenantId);
            }
            $userIds = $userIds->merge($q->whereNotNull('user_id')->pluck('user_id'));
        }

        if (Schema::hasTable('audit_logs')) {
            $q = TenantScope::table('audit_logs')
                ->whereBetween('created_at', [$start, $end])
                ->whereNotNull('user_id');
            $userIds = $userIds->merge($q->pluck('user_id'));
        }

        if (Schema::hasTable('tb_logs')) {
            $userIds = $userIds->merge(
                DB::table('tb_logs')
                    ->whereBetween('created_at', [$start, $end])
                    ->whereNotNull('user_id')
                    ->pluck('user_id')
            );
        }

        if ($userIds->isEmpty() && Schema::hasTable('tb_users')) {
            $q = TenantScope::table('tb_users')->whereBetween('last_login', [$start, $end]);
            $userIds = $userIds->merge($q->pluck('id'));
        }

        return $userIds->unique()->count();
    }

    /**
     * Hitung rata-rata durasi sesi aktif (dalam menit) dari jejak login/audit.
     */
    private function calculateAverageSessionMinutes(string $startDate, string $endDate): int
    {
        $sessions = $this->collectSessionDurations($startDate, $endDate);
        if ($sessions->isEmpty()) {
            return 0;
        }

        return (int) round($sessions->avg());
    }

    /**
     * Kumpulkan estimasi durasi sesi per pengguna per hari (dalam menit) pada periode.
     */
    private function collectSessionDurations(string $startDate, string $endDate)
    {
        $start = $startDate.' 00:00:00';
        $end = $endDate.' 23:59:59';
        $events = collect();

        if (Schema::hasTable('login_histories')) {
            $q = DB::table('login_histories')
                ->whereIn('event', ['login', 'logout'])
                ->whereBetween('created_at', [$start, $end])
                ->whereNotNull('user_id');
            if ($tenantId = TenantScope::tenantId()) {
                $q->where('tenant_id', $tenantId);
            }
            foreach ($q->get(['user_id', 'created_at']) as $row) {
                $events->push(['user_id' => (int) $row->user_id, 'ts' => Carbon::parse($row->created_at)]);
            }
        }

        if (Schema::hasTable('audit_logs')) {
            $q = TenantScope::table('audit_logs')
                ->whereBetween('created_at', [$start, $end])
                ->whereNotNull('user_id');
            foreach ($q->get(['user_id', 'created_at']) as $row) {
                $events->push(['user_id' => (int) $row->user_id, 'ts' => Carbon::parse($row->created_at)]);
            }
        }

        if (Schema::hasTable('tb_logs')) {
            $q = DB::table('tb_logs')
                ->whereBetween('created_at', [$start, $end])
                ->whereNotNull('user_id');
            foreach ($q->get(['user_id', 'created_at']) as $row) {
                $events->push(['user_id' => (int) $row->user_id, 'ts' => Carbon::parse($row->created_at)]);
            }
        }

        if ($events->isEmpty()) {
            return collect();
        }

        return $events
            ->groupBy(fn ($item) => $item['user_id'].':'.$item['ts']->toDateString())
            ->map(function ($group) {
                $min = $group->min('ts');
                $max = $group->max('ts');
                $diff = (int) round($min->diffInSeconds($max) / 60);

                // Jika hanya ada 1 event tercatat pada hari itu, estimasikan 1 menit per aksi
                return $diff > 0 ? min($diff, 240) : min(max(1, $group->count() * 2), 60);
            })
            ->values();
    }

    /**
     * Data grafik tren pengunjung & aktivitas harian pada rentang tanggal.
     */
    public function getDailyTrend(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // Batasi titik grafik harian maksimal 31 hari terakhir dari endDate agar label tetap terbaca
        if ($start->diffInDays($end) > 30) {
            $start = $end->copy()->subDays(29)->startOfDay();
        }

        $rangeStart = $start->toDateTimeString();
        $rangeEnd = $end->toDateTimeString();

        $visitorsByDate = [];
        $activitiesByDate = [];

        if (Schema::hasTable('login_histories')) {
            $q = DB::table('login_histories')
                ->selectRaw('DATE(created_at) as d, COUNT(DISTINCT COALESCE(user_id, ip_address)) as visitors, COUNT(*) as total')
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->where('event', 'login')
                ->groupBy('d');
            if ($tenantId = TenantScope::tenantId()) {
                $q->where('tenant_id', $tenantId);
            }
            foreach ($q->get() as $row) {
                $visitorsByDate[$row->d] = ($visitorsByDate[$row->d] ?? 0) + (int) $row->visitors;
                $activitiesByDate[$row->d] = ($activitiesByDate[$row->d] ?? 0) + (int) $row->total;
            }
        }

        if (Schema::hasTable('audit_logs')) {
            $rows = TenantScope::table('audit_logs')
                ->selectRaw('DATE(created_at) as d, COUNT(DISTINCT user_id) as users, COUNT(*) as total')
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->groupBy('d')
                ->get();
            foreach ($rows as $row) {
                $visitorsByDate[$row->d] = max($visitorsByDate[$row->d] ?? 0, (int) $row->users);
                $activitiesByDate[$row->d] = ($activitiesByDate[$row->d] ?? 0) + (int) $row->total;
            }
        }

        if (Schema::hasTable('tb_logs')) {
            $rows = DB::table('tb_logs')
                ->selectRaw('DATE(created_at) as d, COUNT(DISTINCT user_id) as users, COUNT(*) as total')
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->groupBy('d')
                ->get();
            foreach ($rows as $row) {
                $visitorsByDate[$row->d] = max($visitorsByDate[$row->d] ?? 0, (int) $row->users);
                $activitiesByDate[$row->d] = ($activitiesByDate[$row->d] ?? 0) + (int) $row->total;
            }
        }

        // Jika belum ada log aktivitas, gunakan tanggal bergabung/pembaruan pegawai sebagai sinyal aktivitas
        if ($visitorsByDate === [] && $activitiesByDate === [] && Schema::hasTable('employees')) {
            $rows = TenantScope::table('employees')
                ->selectRaw('join_date as d, COUNT(*) as total')
                ->whereNull('deleted_at')
                ->whereBetween('join_date', [$start->toDateString(), $end->toDateString()])
                ->groupBy('d')
                ->get();
            foreach ($rows as $row) {
                $visitorsByDate[$row->d] = (int) $row->total;
                $activitiesByDate[$row->d] = (int) $row->total;
            }
        }

        $dates = [];
        $visitorSeries = [];
        $activitySeries = [];
        $peakIndex = null;
        $peakValue = 0;

        $cursor = $start->copy();
        $index = 0;
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $label = $cursor->translatedFormat('j M');
            $v = (int) ($visitorsByDate[$key] ?? 0);
            $a = (int) ($activitiesByDate[$key] ?? 0);

            $dates[] = $label;
            $visitorSeries[] = $v;
            $activitySeries[] = $a;

            $maxDay = max($v, $a);
            if ($maxDay > $peakValue) {
                $peakValue = $maxDay;
                $peakIndex = $index;
            }

            $cursor->addDay();
            $index++;
        }

        $peak = null;
        if ($peakIndex !== null && $peakValue > 0) {
            $peak = [
                'x' => $dates[$peakIndex],
                'y' => $peakValue,
                'text' => 'Puncak Tertinggi: '.$dates[$peakIndex].' ('.$peakValue.' aktivitas)',
            ];
        }

        return [
            'dates' => $dates,
            'visitors' => $visitorSeries,
            'activities' => $activitySeries,
            'peak' => $peak,
        ];
    }

    /**
     * Distribusi pegawai / pengguna berdasarkan unit organisasi.
     */
    public function getUnitDistribution(string $endDate): array
    {
        $rows = collect();

        if (Schema::hasTable('employee_assignments') && Schema::hasTable('positions') && Schema::hasTable('unit') && Schema::hasTable('employees')) {
            $scope = DataScope::current();
            $query = EmployeeScope::applyForPeriod(
                TenantScope::table('employee_assignments')
                    ->join('employees', function ($j) use ($endDate) {
                        $j->on('employees.id', '=', 'employee_assignments.employee_id')
                            ->whereNull('employees.deleted_at')
                            ->where('employees.join_date', '<=', $endDate)
                            ->where(fn ($q) => $q->whereNull('employees.terminated_at')->orWhere('employees.terminated_at', '>', $endDate));
                    })
                    ->join('positions', 'positions.id', '=', 'employee_assignments.position_id')
                    ->join('unit', 'unit.id_unit', '=', 'positions.unit_id')
                    ->where('employee_assignments.type', 'primary')
                    ->where('employee_assignments.effective_from', '<=', $endDate)
                    ->where(fn ($q) => $q->whereNull('employee_assignments.effective_to')->orWhere('employee_assignments.effective_to', '>=', $endDate)),
                'employee_assignments.employee_id',
                $endDate,
                $endDate,
                $scope
            );

            $rows = $query
                ->selectRaw('unit.unit_name as name, COUNT(DISTINCT employee_assignments.employee_id) as total')
                ->groupBy('unit.unit_name')
                ->orderByDesc('total')
                ->get();
        }

        if ($rows->isEmpty() && Schema::hasTable('user_unit') && Schema::hasTable('unit')) {
            $rows = TenantScope::table('unit')
                ->join('user_unit', 'user_unit.id_unit', '=', 'unit.id_unit')
                ->selectRaw('unit.unit_name as name, COUNT(DISTINCT user_unit.id_user) as total')
                ->groupBy('unit.unit_name')
                ->orderByDesc('total')
                ->get();
        }

        if ($rows->isEmpty() && Schema::hasTable('positions') && Schema::hasTable('unit')) {
            $rows = TenantScope::table('positions')
                ->join('unit', 'unit.id_unit', '=', 'positions.unit_id')
                ->where('positions.status', 'active')
                ->selectRaw('unit.unit_name as name, SUM(positions.headcount) as total')
                ->groupBy('unit.unit_name')
                ->orderByDesc('total')
                ->get();
        }

        $total = (int) $rows->sum('total');
        if ($total <= 0) {
            return [
                'series' => [],
                'labels' => [],
                'items' => [],
                'total' => 0,
            ];
        }

        $top = $rows->take(4)->values();
        $otherTotal = (int) $rows->slice(4)->sum('total');
        if ($otherTotal > 0) {
            $top->push((object) ['name' => 'Lainnya', 'total' => $otherTotal]);
        }

        $series = [];
        $labels = [];
        $items = [];

        foreach ($top as $row) {
            $count = (int) $row->total;
            $pct = round(($count / $total) * 100, 1);
            $pctFormatted = fmod($pct, 1.0) === 0.0 ? (int) $pct : $pct;
            $series[] = $count;
            $labels[] = $row->name.' ('.$pctFormatted.'%)';
            $items[] = [
                'name' => $row->name,
                'count' => $count,
                'percentage' => $pct,
            ];
        }

        return [
            'series' => $series,
            'labels' => $labels,
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Daftar dokumen atau modul/aktivitas terpopuler berdasarkan data real.
     */
    public function getPopularItems(string $startDate, string $endDate, int $limit = 5): array
    {
        $items = collect();
        $colors = ['blue', 'orange', 'green', 'blue', 'orange'];

        // 1. Cek dokumen RAG bila tersedia
        if (Schema::hasTable('tb_rag_documents')) {
            $docs = DB::table('tb_rag_documents')
                ->leftJoin('tb_rag_chunks', 'tb_rag_chunks.document_id', '=', 'tb_rag_documents.id')
                ->selectRaw('tb_rag_documents.file_name as title, COUNT(tb_rag_chunks.id) as score')
                ->groupBy('tb_rag_documents.id', 'tb_rag_documents.file_name')
                ->orderByDesc('score')
                ->limit($limit)
                ->get();

            foreach ($docs as $doc) {
                $items->push([
                    'title' => $doc->title,
                    'count' => max(1, (int) $doc->score),
                    'icon' => 'fas fa-file-pdf',
                ]);
            }
        }

        // 2. Cek bukti kinerja (performance_evidences) bila tersedia
        if ($items->count() < $limit && Schema::hasTable('performance_evidences')) {
            $evidences = TenantScope::table('performance_evidences')
                ->selectRaw('file_name as title, COUNT(*) as score')
                ->groupBy('file_name')
                ->orderByDesc('score')
                ->limit($limit - $items->count())
                ->get();

            foreach ($evidences as $ev) {
                $items->push([
                    'title' => $ev->title,
                    'count' => (int) $ev->score,
                    'icon' => 'fas fa-file-alt',
                ]);
            }
        }

        // 3. Cek modul/entitas yang paling sering diakses di audit_logs
        if ($items->count() < $limit && Schema::hasTable('audit_logs')) {
            $audits = TenantScope::table('audit_logs')
                ->selectRaw('auditable_type as title, COUNT(*) as score')
                ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                ->groupBy('auditable_type')
                ->orderByDesc('score')
                ->limit($limit - $items->count())
                ->get();

            foreach ($audits as $audit) {
                $items->push([
                    'title' => $this->formatModuleLabel((string) $audit->title),
                    'count' => (int) $audit->score,
                    'icon' => 'fas fa-folder-open',
                ]);
            }
        }

        // 4. Fallback ke tb_logs atau jabatan dengan pegawai terbanyak
        if ($items->count() < $limit && Schema::hasTable('tb_logs')) {
            $logs = DB::table('tb_logs')
                ->selectRaw('module as title, COUNT(*) as score')
                ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                ->groupBy('module')
                ->orderByDesc('score')
                ->limit($limit - $items->count())
                ->get();

            foreach ($logs as $log) {
                $items->push([
                    'title' => $this->formatModuleLabel((string) $log->title),
                    'count' => (int) $log->score,
                    'icon' => 'fas fa-folder-open',
                ]);
            }
        }

        if ($items->isEmpty() && Schema::hasTable('positions') && Schema::hasTable('employee_assignments')) {
            $positions = TenantScope::table('employee_assignments')
                ->join('positions', 'positions.id', '=', 'employee_assignments.position_id')
                ->whereNull('employee_assignments.effective_to')
                ->selectRaw('positions.title as title, COUNT(*) as score')
                ->groupBy('positions.title')
                ->orderByDesc('score')
                ->limit($limit)
                ->get();

            foreach ($positions as $pos) {
                $items->push([
                    'title' => $pos->title,
                    'count' => (int) $pos->score,
                    'icon' => 'fas fa-id-badge',
                ]);
            }
        }

        return $items->take($limit)->values()->map(function ($item, $index) use ($colors) {
            return [
                'rank' => $index + 1,
                'title' => $item['title'],
                'count' => (int) $item['count'],
                'formatted_count' => number_format((int) $item['count'], 0, ',', '.'),
                'icon' => $item['icon'],
                'color' => $colors[$index % count($colors)],
            ];
        })->all();
    }

    /**
     * Perilaku kunjungan berdasarkan durasi sesi aktif (2-10 Menit vs > 10 Menit).
     */
    public function getVisitBehavior(string $startDate, string $endDate): array
    {
        $sessions = $this->collectSessionDurations($startDate, $endDate);
        $total = $sessions->count();

        if ($total > 0) {
            $moderate = $sessions->filter(fn ($m) => $m >= 2 && $m <= 10)->count();
            $long = $sessions->filter(fn ($m) => $m > 10)->count();

            $moderatePct = (int) round(($moderate / $total) * 100);
            $longPct = (int) round(($long / $total) * 100);

            return [
                'moderate_label' => '2-10 Menit',
                'moderate_pct' => $moderatePct,
                'long_label' => '> 10 Menit',
                'long_pct' => $longPct,
                'total_sessions' => $total,
            ];
        }

        return [
            'moderate_label' => '2-10 Menit',
            'moderate_pct' => 0,
            'long_label' => '> 10 Menit',
            'long_pct' => 0,
            'total_sessions' => 0,
        ];
    }

    /**
     * Distribusi metode akses (Desktop vs Mobile) berdasarkan user_agent real di log.
     */
    public function getAccessMethods(string $startDate, string $endDate): array
    {
        $start = $startDate.' 00:00:00';
        $end = $endDate.' 23:59:59';
        $agents = collect();

        if (Schema::hasTable('login_histories')) {
            $q = DB::table('login_histories')
                ->whereBetween('created_at', [$start, $end])
                ->whereNotNull('user_agent')
                ->where('user_agent', '!=', '');
            if ($tenantId = TenantScope::tenantId()) {
                $q->where('tenant_id', $tenantId);
            }
            $agents = $agents->merge($q->pluck('user_agent'));
        }

        if (Schema::hasTable('audit_logs')) {
            $q = TenantScope::table('audit_logs')
                ->whereBetween('created_at', [$start, $end])
                ->whereNotNull('user_agent')
                ->where('user_agent', '!=', '');
            $agents = $agents->merge($q->pluck('user_agent'));
        }

        $total = $agents->count();
        if ($total === 0) {
            return [
                'desktop_count' => 0,
                'mobile_count' => 0,
                'desktop_pct' => 0,
                'mobile_pct' => 0,
            ];
        }

        $mobileCount = $agents->filter(function ($ua) {
            return (bool) preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', (string) $ua);
        })->count();
        $desktopCount = $total - $mobileCount;

        $desktopPct = (int) round(($desktopCount / $total) * 100);
        $mobilePct = 100 - $desktopPct;

        return [
            'desktop_count' => $desktopCount,
            'mobile_count' => $mobileCount,
            'desktop_pct' => $desktopPct,
            'mobile_pct' => $mobilePct,
        ];
    }

    /**
     * Peringatan keamanan & sistem dari data real (login gagal, kontrak/sertifikasi/tugas jatuh tempo).
     */
    public function getSecurityAndSystemAlerts(string $startDate, string $endDate): array
    {
        $start = $startDate.' 00:00:00';
        $end = $endDate.' 23:59:59';
        $today = now()->toDateString();
        $in30Days = now()->addDays(30)->toDateString();

        $failedLogins = 0;
        if (Schema::hasTable('login_histories')) {
            $q = DB::table('login_histories')
                ->whereIn('event', ['failed', 'blocked'])
                ->whereBetween('created_at', [$start, $end]);
            if ($tenantId = TenantScope::tenantId()) {
                $q->where(fn ($w) => $w->where('tenant_id', $tenantId)->orWhereNull('tenant_id'));
            }
            $failedLogins = (int) $q->count();
        }

        if ($failedLogins === 0 && Schema::hasTable('tb_users')) {
            $failedLogins = (int) TenantScope::table('tb_users')->sum('login_attempt');
        }

        $expiringCount = 0;
        if (Schema::hasTable('employee_certifications')) {
            $expiringCount += (int) TenantScope::table('employee_certifications')
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [$today, $in30Days])
                ->count();
        }

        if (Schema::hasTable('employees')) {
            $expiringCount += (int) TenantScope::table('employees')
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->where(function ($q) use ($today, $in30Days) {
                    $q->whereBetween('contract_end_date', [$today, $in30Days])
                        ->orWhereBetween('probation_end_date', [$today, $in30Days]);
                })
                ->count();
        }

        if (Schema::hasTable('competency_assessments')) {
            $expiringCount += (int) TenantScope::table('competency_assessments')
                ->where('status', 'open')
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$today, $in30Days])
                ->count();
        }

        return [
            'failed_logins' => $failedLogins,
            'failed_logins_text' => number_format($failedLogins, 0, ',', '.').'x Gagal Login / Akses Sistem',
            'expiring_items' => $expiringCount,
            'expiring_items_text' => number_format($expiringCount, 0, ',', '.').' Kontrak / Sertifikasi Jatuh Tempo (30 hari)',
        ];
    }

    private function formatModuleLabel(string $raw): string
    {
        $map = [
            'employees' => 'Data Pegawai',
            'employee_assignments' => 'Penugasan Pegawai',
            'employee_change_requests' => 'Perubahan Data Mandiri (ESS)',
            'positions' => 'Manajemen Posisi',
            'unit' => 'Unit Organisasi',
            'competency_assessments' => 'Asesmen Kompetensi',
            'performance_plans' => 'Rencana Kinerja (KPI)',
            'performance_assessments' => 'Penilaian Kinerja',
            'performance_ratings' => 'Rating Kinerja',
            'workflow_definitions' => 'Alur Persetujuan',
            'DemoSeeder' => 'Inisialisasi Data Sistem',
        ];

        return $map[$raw] ?? ucwords(str_replace(['_', '-'], ' ', $raw));
    }
}
