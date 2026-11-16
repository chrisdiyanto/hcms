<?php

namespace App\Http\Controllers\Performance;

use App\Domain\Performance\Services\PerformancePeriods;
use App\Domain\Performance\Services\PerformancePlans;
use App\Domain\Performance\Services\ScoreCalculator;
use App\Domain\Workflow\WorkflowEngine;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Layanan mandiri: pegawai menyusun KPI per penugasan dalam periode berjalan,
 * menyimpan draft, lalu mengajukan ke atasan. Atasan langsung juga dapat melihat
 * rencana bawahan yang diajukan kepadanya (lewat tautan tugas persetujuan).
 */
class MyPlansController extends Controller
{
    public function __construct(private PerformancePeriods $periods, private PerformancePlans $plans)
    {
        parent::__construct();
    }

    public function index()
    {
        $employee = $this->employee();
        $periods = $this->periods->running()->map(function ($period) use ($employee) {
            $period->plans = $employee ? $this->plans->forEmployee((int) $employee->id, $period, (int) auth()->id())
                ->map(function ($p) {
                    $p->kpi_count = TenantScope::table('performance_objectives')->where('plan_id', $p->id)->count();
                    $p->weight_total = (float) TenantScope::table('performance_objectives')->where('plan_id', $p->id)->sum('weight');

                    return $p;
                }) : collect();

            return $period;
        });

        return view('performance.plans.index', [
            'pageTitle' => 'Rencana Kinerja Saya',
            'pageNote' => 'KPI per penugasan dalam periode berjalan',
            'employee' => $employee, 'periods' => $periods,
            'statuses' => PerformancePlans::STATUS, 'periodStatuses' => PerformancePeriods::STATUS,
        ]);
    }

    public function edit(int $plan)
    {
        $row = $this->visiblePlan($plan);
        $period = $this->periods->find((int) $row->period_id);
        $own = $this->isOwner($row);

        return view('performance.plans.edit', [
            'pageTitle' => 'Rencana Kinerja: '.$row->position_title,
            'pageNote' => $period->name.' · '.$row->start_date.' s.d. '.$row->end_date,
            'plan' => $row, 'period' => $period,
            'items' => old('items', $this->plans->objectives($row->id)->map(fn ($o) => [
                'title' => $o->title, 'description' => $o->description, 'kpi_type' => $o->kpi_type, 'unit' => $o->unit,
                'target' => $o->target !== null ? (float) $o->target : null, 'range_min' => $o->range_min !== null ? (float) $o->range_min : null,
                'range_max' => $o->range_max !== null ? (float) $o->range_max : null, 'milestones' => $o->milestones,
                'rubric' => $o->rubric ?: ['', '', '', '', ''], 'weight' => (float) $o->weight, 'cap' => $o->cap !== null ? (float) $o->cap : null,
                'strategic_objective_id' => $o->strategic_objective_id, 'parent_objective_id' => $o->parent_objective_id,
                'contribution_weight' => $o->contribution_weight !== null ? (float) $o->contribution_weight : null,
            ])->values()->all()),
            'types' => ScoreCalculator::TYPES,
            'rules' => $period->policy['kpi'],
            'strategic' => TenantScope::table('strategic_objectives')->where('period_id', $period->id)->whereNull('deleted_at')->orderBy('code')
                ->get(['id', 'code', 'title'])->mapWithKeys(fn ($s) => [$s->id => $s->code.' — '.$s->title])->all(),
            'parents' => $this->plans->parentOptions($row)->mapWithKeys(fn ($o) => [$o->id => $o->full_name.': '.$o->title])->all(),
            'canEdit' => $own && $this->plans->editable($row, $period),
            'canCancel' => $own && $row->status === 'submitted',
            'statuses' => PerformancePlans::STATUS,
        ]);
    }

    public function update(Request $request, int $plan)
    {
        $row = $this->ownPlan($plan);
        $items = array_values($request->input('items', []));
        $this->plans->save($row->id, $items);

        if ($request->input('action') === 'submit') {
            try {
                $this->plans->submit($row->id, (int) auth()->id());
            } catch (ValidationException $e) {
                return redirect('performance/my-plans/'.$row->id)->withErrors($e->errors())
                    ->with('message', 'Draft tersimpan, tetapi belum dapat diajukan.')->with('status', 'error');
            }

            return redirect('performance/my-plans')->with('message', 'Rencana kinerja diajukan ke atasan.')->with('status', 'success');
        }

        return redirect('performance/my-plans/'.$row->id)->with('message', 'Draft tersimpan.')->with('status', 'success');
    }

    public function cancel(int $plan)
    {
        $row = $this->ownPlan($plan);
        if ($row->status !== 'submitted' || ! $row->workflow_instance_id) {
            return back()->withErrors(['plan' => 'Rencana tidak sedang menunggu persetujuan.']);
        }
        app(WorkflowEngine::class)->cancel((int) $row->workflow_instance_id, (int) auth()->id());

        return redirect('performance/my-plans/'.$row->id)->with('message', 'Pengajuan ditarik; rencana kembali menjadi draft.')->with('status', 'success');
    }

    // ---------------------------------------------------------------- bantuan

    private function employee(): ?object
    {
        return TenantScope::table('employees')->where('user_id', (int) auth()->id())->whereNull('deleted_at')->where('status', 'active')->first();
    }

    private function isOwner(object $plan): bool
    {
        return (int) $plan->user_id === (int) auth()->id();
    }

    private function ownPlan(int $id): object
    {
        $row = $this->plans->plan($id);
        abort_unless($this->isOwner($row), 404);

        return $row;
    }

    /** Pemilik, atau approver tugas rencana ini (atasan / penerima delegasi). */
    private function visiblePlan(int $id): object
    {
        $row = $this->plans->plan($id);
        $isApprover = $row->workflow_instance_id && TenantScope::table('approval_tasks')
            ->where('instance_id', $row->workflow_instance_id)->where('approver_user_id', (int) auth()->id())->exists();
        abort_unless($this->isOwner($row) || $isApprover, 404);

        return $row;
    }
}
