<?php

namespace App\Http\Controllers\Performance;

use App\Domain\Performance\Services\PerformanceMonitoring;
use App\Domain\Performance\Services\PerformancePeriods;
use App\Domain\Performance\Services\PerformancePlans;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantScope;

/**
 * Layanan mandiri atasan: rencana kinerja bawahan langsung (posisi yang melapor solid line
 * ke posisi yang dijabat pengguna) pada periode berjalan — status, progres, check-in.
 */
class TeamController extends Controller
{
    public function __construct(private PerformancePeriods $periods, private PerformanceMonitoring $monitoring)
    {
        parent::__construct();
    }

    public function index()
    {
        $today = now()->toDateString();
        $held = TenantScope::table('employee_assignments')->join('employees', 'employees.id', '=', 'employee_assignments.employee_id')
            ->where('employees.user_id', (int) auth()->id())->where('employees.status', 'active')
            ->whereIn('employee_assignments.type', ['primary', 'acting'])
            ->where('employee_assignments.effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('employee_assignments.effective_to')->orWhere('employee_assignments.effective_to', '>=', $today))
            ->pluck('employee_assignments.position_id');
        $reports = TenantScope::table('position_relationships')->whereIn('reports_to_id', $held)->where('type', 'solid')->whereNull('effective_to')
            ->pluck('position_id');

        $periods = $this->periods->running()->map(function ($period) use ($reports) {
            $period->plans = TenantScope::table('performance_plans')
                ->join('employees', 'employees.id', '=', 'performance_plans.employee_id')
                ->join('positions', 'positions.id', '=', 'performance_plans.position_id')
                ->where('performance_plans.period_id', $period->id)->whereIn('performance_plans.position_id', $reports)
                ->orderBy('employees.full_name')
                ->get(['performance_plans.*', 'employees.full_name', 'employees.employee_number', 'positions.title as position_title'])
                ->map(function ($plan) use ($period) {
                    $plan->user_id = TenantScope::table('employees')->where('id', $plan->employee_id)->value('user_id');
                    $plan->progress = $plan->status === 'approved' ? $this->monitoring->progress($plan, $period) : null;
                    $checkins = $this->monitoring->checkins($plan->id);
                    $plan->open_checkins = $checkins->where('status', 'open')->count();
                    $plan->midyear = $checkins->firstWhere('type', 'midyear');

                    return $plan;
                });

            return $period;
        });

        return view('performance.team.index', [
            'pageTitle' => 'Kinerja Tim Saya',
            'pageNote' => 'bawahan langsung pada periode berjalan',
            'periods' => $periods,
            'statuses' => PerformancePlans::STATUS, 'periodStatuses' => PerformancePeriods::STATUS,
        ]);
    }
}
