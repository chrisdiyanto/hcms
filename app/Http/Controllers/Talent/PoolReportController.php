<?php

namespace App\Http\Controllers\Talent;

use App\Domain\Competency\Services\PositionReadiness;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Privacy\SmallGroups;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;

/**
 * Laporan Talent Pool (laporan CBHRM 10, agregat): komposisi & kesiapan anggota, keselarasan aspirasi,
 * cakupan IDP, tinjau ulang yang lewat, pergerakan masuk/keluar tahun berjalan, keragaman gender.
 * Tanpa nama individu; kelompok 1–4 orang disamarkan bagi pengguna tanpa akses data pool individual.
 */
class PoolReportController extends Controller
{
    use AuthorizesHcmsModule;

    protected function moduleName(): string
    {
        return 'talentpoolreport';
    }

    public function index(Request $request)
    {
        $this->authorizeTask('is_view');
        $year = (int) $request->input('year', now()->year);
        $year = $year >= 2020 && $year <= 2100 ? $year : now()->year;
        $today = now()->toDateString();

        $pools = TenantScope::table('talent_pools')->leftJoin('positions as p', 'p.id', '=', 'talent_pools.target_position_id')
            ->leftJoin('jobs_catalog as j', 'j.id', '=', 'p.job_id')->where('talent_pools.is_active', true)->orderBy('talent_pools.name')
            ->get(['talent_pools.id', 'talent_pools.code', 'talent_pools.name', 'talent_pools.target_job_family_id', 'j.job_family_id as position_family_id']);
        $members = TenantScope::table('talent_pool_members')->join('employees', 'employees.id', '=', 'talent_pool_members.employee_id')
            ->whereIn('talent_pool_members.pool_id', $pools->pluck('id'))
            ->get(['talent_pool_members.id', 'talent_pool_members.pool_id', 'talent_pool_members.employee_id', 'talent_pool_members.status',
                'talent_pool_members.readiness', 'talent_pool_members.next_review_date', 'employees.gender']);
        $active = $members->where('status', 'active');
        $aspirations = TenantScope::table('employee_career_aspirations')->leftJoin('jobs_catalog as j', 'j.id', '=', 'employee_career_aspirations.target_job_id')
            ->whereIn('employee_career_aspirations.employee_id', $active->pluck('employee_id'))->where('employee_career_aspirations.is_current', true)
            ->get(['employee_career_aspirations.employee_id', 'employee_career_aspirations.target_job_family_id', 'j.job_family_id'])->keyBy('employee_id');
        $withIdp = TenantScope::table('idps')->whereIn('employee_id', $active->pluck('employee_id'))->where('period_year', $year)
            ->whereIn('status', ['approved', 'completed'])->pluck('employee_id')->flip();
        $events = TenantScope::table('talent_pool_events as ev')->join('talent_pool_members as m', 'm.id', '=', 'ev.member_id')
            ->whereIn('m.pool_id', $pools->pluck('id'))->whereYear('ev.created_at', $year)->whereIn('ev.to_status', ['active', 'exited'])
            ->whereRaw('(ev.from_status IS NULL OR ev.from_status != ev.to_status)')->get(['m.pool_id', 'ev.to_status']);

        $rows = $pools->map(function ($pool) use ($members, $active, $aspirations, $withIdp, $events, $today) {
            $mine = $active->where('pool_id', $pool->id);
            $family = $pool->target_job_family_id ?: $pool->position_family_id;
            $aligned = $mine->filter(function ($m) use ($aspirations, $family) {
                $a = $aspirations->get($m->employee_id);

                return $a && $family && ((int) $a->target_job_family_id === (int) $family || (int) $a->job_family_id === (int) $family);
            })->count();

            return (object) [
                'code' => $pool->code, 'name' => $pool->name, 'active' => $mine->count(),
                'nominated' => $members->where('pool_id', $pool->id)->where('status', 'nominated')->count(),
                'now' => $mine->where('readiness', 'now')->count(), 'soon' => $mine->where('readiness', '1_2')->count(), 'later' => $mine->where('readiness', '3_plus')->count(),
                'aligned' => $aligned, 'with_idp' => $mine->filter(fn ($m) => $withIdp->has($m->employee_id))->count(),
                'overdue' => $mine->filter(fn ($m) => $m->next_review_date && $m->next_review_date < $today)->count(),
                'joined' => $events->where('pool_id', $pool->id)->where('to_status', 'active')->count(),
                'exited' => $events->where('pool_id', $pool->id)->where('to_status', 'exited')->count(),
                'female' => $mine->where('gender', 'F')->count(),
            ];
        });
        $people = $active->unique('employee_id');

        return view('talent.pools.report', [
            'pageTitle' => 'Laporan Talent Pool', 'year' => $year, 'rows' => $rows,
            'mask' => app(SmallGroups::class)->masks('talentpools'),
            'people' => $people->count(), 'memberships' => $active->count(),
            'readyNow' => $people->where('readiness', 'now')->count(),
            'aligned' => (int) $rows->sum('aligned'), 'withIdp' => (int) $rows->sum('with_idp'), 'overdue' => (int) $rows->sum('overdue'),
            'nominated' => (int) $rows->sum('nominated'), 'joined' => (int) $rows->sum('joined'), 'exited' => (int) $rows->sum('exited'),
            'horizons' => PositionReadiness::HORIZONS,
        ]);
    }
}
