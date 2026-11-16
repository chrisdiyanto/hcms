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

/**
 * Dasbor kinerja per periode (blueprint 8.1 Performance Analytics) sesuai data scope:
 * progres tiap tahap, sebaran rating (rating sah bila periode ditutup, selain itu hasil
 * penilaian setelah kalibrasi), dan rata-rata skor per unit.
 */
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
        $list = TenantScope::table('performance_periods')->where('status', '!=', 'draft')->orderByDesc('start_date')->get(['id', 'name', 'status']);
        $periodId = $request->integer('period') ?: ($list->first()->id ?? null);
        if (! $periodId) {
            return view('performance.dashboard.index', ['pageTitle' => 'Dasbor Kinerja', 'pageNote' => '', 'periods' => $list, 'period' => null]);
        }
        $period = $this->periods->find($periodId);
        $scope = EmployeeScope::query()->where('employees.status', 'active')->select('employees.id');

        $plans = TenantScope::table('performance_plans')->where('period_id', $period->id)->whereIn('employee_id', $scope);
        $planCounts = (clone $plans)->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');
        $approved = (clone $plans)->where('status', 'approved')->pluck('id');
        $midyear = TenantScope::table('performance_checkins')->whereIn('plan_id', $approved)->where('type', 'midyear')->where('status', 'completed')->count();
        $assessmentCounts = TenantScope::table('performance_assessments')->whereIn('plan_id', $approved)->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');

        // Skor & band per pegawai
        if (in_array($period->status, ['appeal', 'closed'], true)) {
            $scores = TenantScope::table('performance_ratings')->where('period_id', $period->id)->where('is_current', true)->where('rated', true)
                ->whereIn('employee_id', $scope)->get(['employee_id', 'final_score as score', 'band']);
            $source = $period->status === 'closed' ? 'Rating yang disahkan' : 'Rating sementara (tahap keberatan)';
        } else {
            $scores = TenantScope::table('performance_assessments')->join('performance_plans', 'performance_plans.id', '=', 'performance_assessments.plan_id')
                ->whereIn('performance_plans.id', $approved)->whereNotNull('performance_assessments.final_score')
                ->get(['performance_plans.employee_id', 'performance_assessments.final_score', 'performance_assessments.band', 'performance_assessments.calibrated_score', 'performance_assessments.calibrated_band'])
                ->map(fn ($a) => (object) ['employee_id' => $a->employee_id] + PerformanceEvaluation::effective($a));
            $source = 'Hasil penilaian (setelah kalibrasi, belum disahkan)';
        }
        $bands = collect($period->policy['bands'])->sortByDesc('min')->values();
        $dist = $bands->map(fn ($b) => ['label' => $b['label'], 'count' => $scores->where('band', $b['code'])->count()]);

        $units = TenantScope::table('employee_assignments')->join('positions', 'positions.id', '=', 'employee_assignments.position_id')
            ->join('unit', 'unit.id_unit', '=', 'positions.unit_id')->whereIn('employee_assignments.employee_id', $scores->pluck('employee_id'))
            ->where('employee_assignments.type', 'primary')->whereNull('employee_assignments.effective_to')
            ->pluck('unit.unit_name', 'employee_assignments.employee_id');
        $byUnit = $scores->groupBy(fn ($s) => $units[$s->employee_id] ?? 'Tanpa unit')
            ->map(fn ($g) => round($g->avg('score'), 2))->sortDesc();

        return view('performance.dashboard.index', [
            'pageTitle' => 'Dasbor Kinerja', 'pageNote' => $period->name.' · '.PerformancePeriods::STATUS[$period->status],
            'periods' => $list, 'period' => $period, 'source' => $source,
            'planCounts' => $planCounts, 'approvedCount' => $approved->count(), 'midyear' => $midyear, 'assessmentCounts' => $assessmentCounts,
            'scored' => $scores->count(), 'average' => $scores->isEmpty() ? null : round($scores->avg('score'), 2),
            'distChart' => ['type' => 'bar', 'labels' => $dist->pluck('label')->all(), 'values' => $dist->pluck('count')->all(),
                'colors' => array_slice(['#12b76a', '#2e90fa', '#667085', '#f79009', '#d92d20', '#912018', '#344054'], 0, $dist->count())],
            'unitChart' => $byUnit->isEmpty() ? null : ['type' => 'bar', 'labels' => $byUnit->keys()->all(), 'values' => $byUnit->values()->all()],
            'bandLabels' => PerformancePolicy::bandLabels($period->policy),
        ]);
    }
}
