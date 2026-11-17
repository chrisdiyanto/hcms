<?php

namespace App\Http\Controllers\Performance;

use App\Domain\Employee\Services\EmployeeScope;
use App\Domain\Performance\Services\PerformanceEvaluation;
use App\Domain\Performance\Services\PerformancePeriods;
use App\Domain\Performance\Services\PerformancePolicy;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Executive performance dashboard: score trend, coverage, distribution and action signals. */
class DashboardController extends Controller
{
    use AuthorizesHcmsModule;

    public function __construct(private PerformancePeriods $periods)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'perfdashboard';
    }

    public function index(Request $request)
    {
        $this->authorizeTask('is_view');
        $periods = TenantScope::table('performance_periods')->where('status', '!=', 'draft')
            ->orderByDesc('start_date')->get(['id', 'name', 'status', 'start_date', 'end_date']);
        $periodId = $request->integer('period') ?: ($periods->first()->id ?? null);
        if (! $periodId) {
            return view('performance.dashboard.index', [
                'pageTitle' => 'Kinerja', 'periods' => $periods, 'period' => null,
            ]);
        }

        $period = $this->periods->find($periodId);
        $employees = EmployeeScope::forPeriod($period->start_date, $period->end_date)->select('employees.id');
        $plans = TenantScope::table('performance_plans')->where('period_id', $period->id)->whereIn('employee_id', $employees);
        $planCounts = (clone $plans)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $approved = (clone $plans)->where('status', 'approved')->pluck('id');
        $assessments = TenantScope::table('performance_assessments')->whereIn('plan_id', $approved);
        $assessmentCounts = (clone $assessments)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $completed = (int) ($assessmentCounts['completed'] ?? 0);
        $midyear = TenantScope::table('performance_checkins')->whereIn('plan_id', $approved)
            ->where('type', 'midyear')->where('status', 'completed')->count();

        if (in_array($period->status, ['appeal', 'closed'], true)) {
            $scores = TenantScope::table('performance_ratings')->where('period_id', $period->id)
                ->where('is_current', true)->where('rated', true)->whereIn('employee_id', $employees)
                ->get(['employee_id', 'final_score as score', 'band']);
            $source = $period->status === 'closed' ? 'Rating final yang disahkan' : 'Rating sementara pada masa keberatan';
        } else {
            $scores = TenantScope::table('performance_assessments')->join('performance_plans', 'performance_plans.id', '=', 'performance_assessments.plan_id')
                ->whereIn('performance_plans.id', $approved)->whereNotNull('performance_assessments.final_score')
                ->get(['performance_plans.employee_id', 'performance_assessments.final_score', 'performance_assessments.band',
                    'performance_assessments.calibrated_score', 'performance_assessments.calibrated_band'])
                ->map(fn ($assessment) => (object) ['employee_id' => $assessment->employee_id] + PerformanceEvaluation::effective($assessment));
            $source = 'Hasil penilaian setelah kalibrasi';
        }

        $policy = $period->policy ?? PerformancePolicy::DEFAULTS;
        $bands = collect($policy['bands'])->sortByDesc('min')->values();
        $bandCounts = $bands->map(fn ($band) => [
            'code' => $band['code'], 'label' => $band['label'], 'count' => $scores->where('band', $band['code'])->count(),
        ]);
        $riskCodes = $bands->sortBy('min')->take(2)->pluck('code')->all();
        $riskCount = $scores->whereIn('band', $riskCodes)->count();
        $average = $scores->isEmpty() ? null : round((float) $scores->avg('score'), 2);
        $coverage = $approved->isNotEmpty() ? round($completed / $approved->count() * 100, 1) : 0;
        $activePips = TenantScope::table('performance_improvement_plans')->whereIn('employee_id', $employees)
            ->whereIn('status', ['active', 'extended'])->where('start_date', '<=', $period->end_date)->where('end_date', '>=', $period->start_date)->count();
        $byUnit = $this->scoresByUnit($scores, $period->end_date);
        $trend = $this->closedTrend($periods->filter(fn ($item) => $item->end_date <= $period->end_date));
        $trendChange = count($trend) >= 2 ? round($trend[count($trend) - 1]['score'] - $trend[count($trend) - 2]['score'], 2) : null;
        $trendChart = count($trend) >= 1 ? [
            'type' => 'area', 'name' => 'Rata-rata rating', 'suffix' => ' poin', 'zero' => false,
            'labels' => array_column($trend, 'label'), 'values' => array_column($trend, 'score'),
        ] : null;
        $unitChart = $byUnit->isNotEmpty() ? [
            'type' => 'hbar', 'name' => 'Rata-rata skor', 'suffix' => ' poin',
            'labels' => $byUnit->take(10)->keys()->all(), 'values' => $byUnit->take(10)->values()->all(),
        ] : null;

        $lowBandLabels = $bands->whereIn('code', $riskCodes)->pluck('label')->implode(' / ');
        $insights = collect();
        if ($approved->isEmpty()) {
            $insights->push(['type' => 'info', 'title' => 'Belum ada rencana disetujui', 'text' => 'Selesaikan perencanaan dan persetujuan agar hasil periode ini dapat dipantau.']);
        } elseif ($coverage < 100) {
            $insights->push(['type' => $coverage < 70 ? 'warning' : 'info', 'title' => 'Penilaian belum lengkap',
                'text' => $approved->count() - $completed.' dari '.$approved->count().' rencana disetujui belum selesai dinilai.']);
        }
        if ($riskCount > 0) {
            $insights->push(['type' => 'warning', 'title' => 'Perlu tindak lanjut',
                'text' => $riskCount.' hasil berada pada band '.$lowBandLabels.'. Tinjau kebutuhan dukungan atau PIP.']);
        }
        if ($trendChange !== null) {
            $insights->push(['type' => $trendChange >= 0 ? 'success' : 'warning', 'title' => 'Perubahan dari periode tertutup sebelumnya',
                'text' => ($trendChange > 0 ? '+' : '').number_format($trendChange, 2, ',', '.').' poin pada rata-rata rating final.']);
        }
        if ($activePips > 0) {
            $insights->push(['type' => 'info', 'title' => 'Rencana perbaikan berjalan', 'text' => $activePips.' PIP aktif atau diperpanjang pada rentang periode ini.']);
        }

        return view('performance.dashboard.index', [
            'pageTitle' => 'Kinerja', 'periods' => $periods, 'period' => $period, 'source' => $source,
            'planCounts' => $planCounts, 'approvedCount' => $approved->count(), 'midyear' => $midyear,
            'assessmentCounts' => $assessmentCounts, 'completed' => $completed, 'coverage' => $coverage,
            'scored' => $scores->pluck('employee_id')->unique()->count(), 'average' => $average,
            'riskCount' => $riskCount, 'activePips' => $activePips, 'bandCounts' => $bandCounts,
            'bandChart' => $scores->isNotEmpty() ? [
                'type' => 'hbar', 'name' => 'Pegawai', 'slot' => 0,
                'labels' => $bandCounts->pluck('label')->all(), 'values' => $bandCounts->pluck('count')->all(),
                'colors' => $bands->map(fn ($band) => in_array($band['code'], $riskCodes, true) ? '#c2283a' : '#1baf7a')->all(),
            ] : null,
            'trend' => $trend, 'trendChart' => $trendChart, 'unitChart' => $unitChart, 'trendChange' => $trendChange,
            'insights' => $insights, 'sourceLabel' => $source,
        ]);
    }

    private function scoresByUnit(Collection $scores, string $asOf): Collection
    {
        if ($scores->isEmpty()) {
            return collect();
        }
        $units = TenantScope::table('employee_assignments')->join('positions', 'positions.id', '=', 'employee_assignments.position_id')
            ->join('unit', 'unit.id_unit', '=', 'positions.unit_id')->whereIn('employee_assignments.employee_id', $scores->pluck('employee_id')->unique())
            ->where('employee_assignments.type', 'primary')->where('employee_assignments.effective_from', '<=', $asOf)
            ->where(fn ($q) => $q->whereNull('employee_assignments.effective_to')->orWhere('employee_assignments.effective_to', '>=', $asOf))
            ->pluck('unit.unit_name', 'employee_assignments.employee_id');

        return $scores->groupBy(fn ($score) => $units[$score->employee_id] ?? 'Tanpa unit')
            ->map(fn ($group) => round((float) $group->avg('score'), 2))->sortDesc();
    }

    /** Final ratings only: unlike in-progress scores, these are comparable across closed periods. */
    private function closedTrend(Collection $periods): array
    {
        return $periods->where('status', 'closed')->take(6)->reverse()->values()->map(function ($period) {
            $employees = EmployeeScope::forPeriod($period->start_date, $period->end_date)->select('employees.id');
            $row = TenantScope::table('performance_ratings')->where('period_id', $period->id)
                ->where('is_current', true)->where('rated', true)->whereIn('employee_id', $employees)
                ->selectRaw('AVG(final_score) as average_score, COUNT(*) as total')->first();

            return [
                'label' => $period->name,
                'score' => $row->average_score === null ? 0 : round((float) $row->average_score, 2),
                'total' => (int) $row->total,
            ];
        })->filter(fn ($item) => $item['total'] > 0)->values()->all();
    }
}
