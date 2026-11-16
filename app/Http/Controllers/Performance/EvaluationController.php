<?php

namespace App\Http\Controllers\Performance;

use App\Domain\Employee\Services\EmployeeScope;
use App\Domain\Performance\Services\PerformanceEvaluation;
use App\Domain\Performance\Services\PerformanceMonitoring;
use App\Domain\Performance\Services\PerformancePeriods;
use App\Domain\Performance\Services\PerformancePlans;
use App\Domain\Performance\Services\PerformancePolicy;
use App\Domain\Performance\Services\ScoreCalculator;
use App\Http\Controllers\Controller;
use App\Models\Sximo;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;

/**
 * Penilaian akhir: daftar tugas penilaian pengguna (diri sendiri, sebagai atasan, sebagai
 * atasan dari atasan) dan form per rencana sesuai peran. HR hanya membaca.
 */
class EvaluationController extends Controller
{
    public function __construct(
        private PerformanceEvaluation $evaluation,
        private PerformancePlans $plans,
        private PerformancePeriods $periods,
        private PerformanceMonitoring $monitoring,
    ) {
        parent::__construct();
    }

    public function index()
    {
        $userId = (int) auth()->id();
        $base = fn () => TenantScope::table('performance_assessments')
            ->join('performance_plans', 'performance_plans.id', '=', 'performance_assessments.plan_id')
            ->join('employees', 'employees.id', '=', 'performance_plans.employee_id')
            ->join('positions', 'positions.id', '=', 'performance_plans.position_id')
            ->join('performance_periods', 'performance_periods.id', '=', 'performance_plans.period_id')
            ->whereIn('performance_periods.status', ['assessment', 'calibration'])
            ->select(['performance_assessments.*', 'employees.full_name', 'employees.employee_number', 'positions.title as position_title',
                'performance_periods.name as period_name']);

        return view('performance.evaluation.index', [
            'pageTitle' => 'Penilaian Kinerja',
            'pageNote' => 'penilaian diri, penilaian bawahan, dan review',
            'mine' => $base()->where('employees.user_id', $userId)->get(),
            'asManager' => $base()->where('performance_assessments.manager_user_id', $userId)->get(),
            'asReviewer' => $base()->where('performance_assessments.reviewer_user_id', $userId)->get(),
            'statuses' => PerformanceEvaluation::STATUS,
        ]);
    }

    public function show(int $plan)
    {
        [$row, $assessment, $role] = $this->load($plan);
        $period = $this->periods->find((int) $row->period_id);
        $details = $this->evaluation->details($assessment->id);
        $showManager = $role !== 'owner' || $assessment->status === 'completed';

        return view('performance.evaluation.show', [
            'pageTitle' => 'Penilaian Kinerja: '.$row->full_name,
            'pageNote' => $row->position_title.' · '.$period->name,
            'plan' => $row, 'period' => $period, 'assessment' => $assessment, 'role' => $role,
            'progress' => $this->monitoring->progress($row, $period),
            'self' => $details->get('self', collect()), 'manager' => $showManager ? $details->get('manager', collect()) : collect(),
            'showManager' => $showManager,
            'howItems' => $period->policy['how_items'], 'rubric' => $period->policy['contribution_rubric'],
            'weights' => $assessment->weights ? json_decode($assessment->weights, true) : $this->monitoring->calculator($period)->weightsFor($this->evaluation->jobLevel((int) $row->position_id)),
            'dimensions' => PerformancePolicy::DIMENSIONS, 'types' => ScoreCalculator::TYPES,
            'bands' => PerformancePolicy::bandLabels($period->policy), 'statuses' => PerformanceEvaluation::STATUS,
            'canSelf' => $role === 'owner' && $assessment->status === 'self' && $period->status === 'assessment',
            'canManager' => $role === 'manager' && $assessment->status === 'manager' && $period->status === 'assessment',
            'canReview' => $role === 'reviewer' && $assessment->status === 'review' && $period->status === 'assessment',
            'effective' => PerformanceEvaluation::effective($assessment),
        ]);
    }

    public function saveSelf(Request $request, int $plan)
    {
        $this->load($plan);
        $this->evaluation->saveSelf($plan, (int) auth()->id(), $this->input($request), $request->input('action') === 'submit');

        return redirect('performance/assessments/'.$plan)
            ->with('message', $request->input('action') === 'submit' ? 'Penilaian diri dikirim ke atasan.' : 'Draft tersimpan.')->with('status', 'success');
    }

    public function saveManager(Request $request, int $plan)
    {
        $this->load($plan);
        $this->evaluation->saveManager($plan, (int) auth()->id(), $this->input($request), $request->input('action') === 'submit');

        return redirect('performance/assessments/'.$plan)
            ->with('message', $request->input('action') === 'submit' ? 'Penilaian atasan dikirim.' : 'Draft tersimpan.')->with('status', 'success');
    }

    public function review(Request $request, int $plan)
    {
        $this->load($plan);
        $data = $request->validate(['decision' => ['required', 'in:agree,return'], 'review_note' => ['nullable', 'string', 'max:2000']]);
        $this->evaluation->review($plan, (int) auth()->id(), $data['decision'] === 'agree', $data['review_note'] ?? null);

        return redirect('performance/assessments')->with('message', $data['decision'] === 'agree' ? 'Penilaian disetujui.' : 'Penilaian dikembalikan ke atasan.')
            ->with('status', 'success');
    }

    private function input(Request $request): array
    {
        return $request->validate([
            'summary' => ['nullable', 'string', 'max:3000'],
            'how' => ['nullable', 'array'], 'how.*' => ['nullable', 'integer', 'between:1,5'],
            'how_comment' => ['nullable', 'array'], 'how_comment.*' => ['nullable', 'string', 'max:1000'],
            'contribution' => ['nullable', 'integer', 'between:1,5'],
            'contribution_comment' => ['nullable', 'string', 'max:2000'],
            'kpi_comment' => ['nullable', 'array'], 'kpi_comment.*' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /** @return array{0: object, 1: object, 2: string} */
    private function load(int $planId): array
    {
        $row = $this->plans->plan($planId);
        $assessment = $this->evaluation->forPlan($planId);
        $role = $this->evaluation->roleOf($assessment, $row, (int) auth()->id());
        if (! $role) {
            $model = new Sximo;
            $info = $model->makeInfo('perfperiods');
            $access = isset($info['id']) ? $model->validAccess($info['id'], session('gid')) : [];
            abort_unless(! empty($access['is_view']) && EmployeeScope::canSee((int) $row->employee_id), 404);
            $role = 'hr';
        }

        return [$row, $assessment, $role];
    }
}
