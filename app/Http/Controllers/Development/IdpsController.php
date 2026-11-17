<?php

namespace App\Http\Controllers\Development;

use App\Domain\Development\Idps;
use App\Domain\Employee\Services\EmployeeScope;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Privacy\SmallGroups;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;

/**
 * Rekap IDP (laporan CBHRM 6; kamus metrik §6): cakupan IDP, status, kemajuan aktivitas,
 * aktivitas lewat tenggat, dan gap kompetensi yang belum tertutup setelah aktivitas selesai.
 */
class IdpsController extends Controller
{
    use AuthorizesHcmsModule;

    protected function moduleName(): string
    {
        return 'idpadmin';
    }

    public function index(Request $request)
    {
        $this->authorizeTask('is_view');
        $year = (int) $request->input('year', now()->year);
        $year = $year >= 2020 && $year <= 2100 ? $year : now()->year;
        $today = now()->toDateString();
        $status = array_key_exists((string) $request->query('status'), Idps::STATUS) ? (string) $request->query('status') : null;

        $idps = EmployeeScope::apply(TenantScope::table('idps')->join('employees', 'employees.id', '=', 'idps.employee_id')
            ->where('idps.period_year', $year)->where('idps.status', '!=', 'cancelled'), 'idps.employee_id');
        $statusCounts = (clone $idps)->selectRaw('idps.status, COUNT(*) n')->groupBy('idps.status')->pluck('n', 'status');
        $headcount = EmployeeScope::query()->where('employees.status', 'active')->count();

        $activities = TenantScope::table('idp_activities')->whereIn('idp_activities.idp_id', (clone $idps)->whereIn('idps.status', ['approved', 'completed'])->select('idps.id'))
            ->where('idp_activities.status', '!=', 'cancelled');
        $totals = (clone $activities)->selectRaw("COUNT(*) total, SUM(status = 'completed') done,
            SUM(status IN ('planned', 'in_progress', 'deferred') AND due_date < ?) late, COALESCE(SUM(cost_estimate), 0) planned_cost, COALESCE(SUM(cost_actual), 0) actual_cost", [$today])->first();

        // Gap setelah aktivitas selesai: gap terkini kompetensi sasaran (≥ 0 = tertutup)
        $gapAfter = (clone $activities)->where('idp_activities.status', 'completed')->whereNotNull('idp_activities.competency_code')
            ->join('idps as i', 'i.id', '=', 'idp_activities.idp_id')
            ->leftJoin('competency_gaps as g', fn ($j) => $j->on('g.employee_id', '=', 'i.employee_id')->on('g.competency_code', '=', 'idp_activities.competency_code'))
            ->selectRaw('SUM(g.gap >= 0) closed, SUM(g.gap < 0) still_open, SUM(g.gap IS NULL) unknown')->first();

        $rows = (clone $idps)
            ->leftJoin('employee_assignments as a', function ($j) use ($today) {
                $j->on('a.employee_id', '=', 'employees.id')->where('a.type', 'primary')->where('a.effective_from', '<=', $today)
                    ->where(fn ($w) => $w->whereNull('a.effective_to')->orWhere('a.effective_to', '>=', $today));
            })
            ->leftJoin('positions as p', 'p.id', '=', 'a.position_id')->leftJoin('unit as u', 'u.id_unit', '=', 'p.unit_id')
            ->when($status, fn ($q) => $q->where('idps.status', $status))
            ->when($request->filled('unit'), fn ($q) => $q->where('p.unit_id', (int) $request->query('unit')))
            ->when(trim((string) $request->query('q')), fn ($q, $v) => $q->where('employees.full_name', 'like', '%'.$v.'%'))
            ->selectRaw("idps.id, idps.status, idps.approved_at, employees.full_name, employees.employee_number, u.unit_name,
                (SELECT COUNT(*) FROM idp_activities x WHERE x.idp_id = idps.id AND x.status != 'cancelled') as total,
                (SELECT COUNT(*) FROM idp_activities x WHERE x.idp_id = idps.id AND x.status = 'completed') as done,
                (SELECT COUNT(*) FROM idp_activities x WHERE x.idp_id = idps.id AND x.status IN ('planned', 'in_progress', 'deferred') AND x.due_date < ?) as late", [$today])
            ->orderByDesc('late')->orderBy('employees.full_name')->paginate(50)->withQueryString();

        return view('development.idps.index', [
            'pageTitle' => 'Rekap IDP', 'year' => $year, 'status' => $status, 'rows' => $rows,
            'mask' => app(SmallGroups::class)->masks('idpadmin'),
            'statusCounts' => $statusCounts, 'idpTotal' => (int) $statusCounts->sum(), 'headcount' => $headcount,
            'activityTotal' => (int) $totals->total, 'activityDone' => (int) $totals->done, 'late' => (int) $totals->late,
            'plannedCost' => (float) $totals->planned_cost, 'actualCost' => (float) $totals->actual_cost,
            'gapClosed' => (int) $gapAfter->closed, 'gapOpen' => (int) $gapAfter->still_open, 'gapUnknown' => (int) $gapAfter->unknown,
            'units' => TenantScope::table('unit')->orderBy('unit_name')->pluck('unit_name', 'id_unit'),
            'statuses' => Idps::STATUS,
        ]);
    }
}
