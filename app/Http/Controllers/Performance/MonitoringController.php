<?php

namespace App\Http\Controllers\Performance;

use App\Domain\Employee\Services\EmployeeScope;
use App\Domain\Performance\Services\KpiChangeRequests;
use App\Domain\Performance\Services\PerformanceMonitoring;
use App\Domain\Performance\Services\PerformancePeriods;
use App\Domain\Performance\Services\PerformancePlans;
use App\Domain\Performance\Services\ScoreCalculator;
use App\Domain\Workflow\Services\ApproverResolver;
use App\Http\Controllers\Controller;
use App\Models\Sximo;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Monitoring rencana kinerja yang sudah disetujui:
 * - pemilik  : catat realisasi & bukti, buka check-in / mid-year review, ajukan perubahan KPI;
 * - atasan   : lihat progres, buka & selesaikan check-in (mid-year review wajib diselesaikan atasan);
 * - HR       : hanya lihat (izin modul Periode Kinerja + data scope pegawai).
 */
class MonitoringController extends Controller
{
    public function __construct(
        private PerformancePlans $plans,
        private PerformancePeriods $periods,
        private PerformanceMonitoring $monitoring,
        private KpiChangeRequests $changes,
        private ApproverResolver $resolver,
    ) {
        parent::__construct();
    }

    public function show(int $plan)
    {
        [$row, $role] = $this->planAccess($plan);
        $period = $this->periods->find((int) $row->period_id);
        $progress = $this->monitoring->progress($row, $period);
        if ($role === 'hr') {
            AuditLog::sensitiveViewed('performance_plans', $row->id, 'Melihat monitoring kinerja '.$row->full_name);
        }

        return view('performance.monitoring.show', [
            'pageTitle' => 'Monitoring Kinerja: '.$row->full_name,
            'pageNote' => $row->position_title.' · '.$period->name,
            'plan' => $row, 'period' => $period, 'role' => $role, 'progress' => $progress,
            'checkins' => $this->monitoring->checkins($row->id),
            'pendingChange' => $this->changes->pending($row->id),
            'changeHistory' => TenantScope::table('kpi_change_requests')->where('plan_id', $row->id)->where('status', '!=', 'pending')->orderByDesc('id')->get(),
            'types' => ScoreCalculator::TYPES, 'checkinTypes' => PerformanceMonitoring::CHECKIN_TYPES,
            'bands' => array_column($period->policy['bands'], 'label', 'code'),
            'calculator' => $this->monitoring->calculator($period),
            'canRecord' => $role === 'owner' && $period->status === 'monitoring' && $row->status === 'approved',
            'canCheckin' => in_array($role, ['owner', 'manager'], true) && $period->status === 'monitoring' && $row->status === 'approved',
            'hasMidyear' => $this->monitoring->checkins($row->id)->contains('type', 'midyear'),
            'evidenceRule' => implode(', ', PerformanceMonitoring::EVIDENCE_MIMES).' · maks. '.(PerformanceMonitoring::EVIDENCE_MAX_KB / 1024).' MB · '.PerformanceMonitoring::EVIDENCE_MAX_FILES.' berkas',
        ]);
    }

    public function storeUpdate(Request $request, int $objective)
    {
        $planId = (int) (TenantScope::table('performance_objectives')->where('id', $objective)->value('plan_id') ?? abort(404));
        [$row, $role] = $this->planAccess($planId);
        abort_unless($role === 'owner', 403);

        $data = $request->validate([
            'as_of' => ['required', 'date', 'before_or_equal:today'],
            'realization' => ['nullable', 'numeric'],
            'score' => ['nullable', 'integer', 'between:1,5'],
            'milestones' => ['nullable', 'array'],
            'milestones.*.done' => ['nullable', 'boolean'],
            'milestones.*.done_date' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:2000'],
            'evidences' => ['nullable', 'array', 'max:'.PerformanceMonitoring::EVIDENCE_MAX_FILES],
            'evidences.*' => ['file', 'max:'.PerformanceMonitoring::EVIDENCE_MAX_KB, 'mimes:'.implode(',', PerformanceMonitoring::EVIDENCE_MIMES)],
        ], [], ['as_of' => 'tanggal', 'realization' => 'realisasi', 'evidences.*' => 'bukti']);

        $this->monitoring->recordUpdate($objective, $data, $request->file('evidences', []), (int) auth()->id());

        return redirect('performance/plans/'.$row->id.'/monitoring#kpi-'.$objective)->with('message', 'Realisasi dicatat.')->with('status', 'success');
    }

    public function evidence(int $evidence)
    {
        $file = $this->monitoring->evidence($evidence);
        [$row] = $this->planAccess((int) $file->plan_id);
        AuditLog::sensitiveViewed('performance_evidences', $file->id, 'Mengunduh bukti kinerja '.$row->full_name.': '.$file->original_name);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    public function storeCheckin(Request $request, int $plan)
    {
        [$row, $role] = $this->planAccess($plan);
        abort_unless(in_array($role, ['owner', 'manager'], true), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(PerformanceMonitoring::CHECKIN_TYPES))],
            'checkin_date' => ['required', 'date'],
            'employee_notes' => [$role === 'owner' ? 'required' : 'nullable', 'string', 'max:3000'],
        ], [], ['employee_notes' => 'catatan pegawai', 'checkin_date' => 'tanggal']);

        $notify = $role === 'owner' ? $this->supervisorUsers($row)[0] ?? null : (int) $row->user_id;
        $this->monitoring->openCheckin($row, $data['type'], $data['checkin_date'], $data['employee_notes'] ?? null, (int) auth()->id(), $notify);

        return redirect('performance/plans/'.$row->id.'/monitoring#checkin')->with('message', 'Check-in dibuat.')->with('status', 'success');
    }

    public function completeCheckin(Request $request, int $checkin)
    {
        $planId = (int) (TenantScope::table('performance_checkins')->where('id', $checkin)->value('plan_id') ?? abort(404));
        [$row, $role] = $this->planAccess($planId);
        abort_unless($role === 'manager', 403, 'Check-in diselesaikan oleh atasan.');
        $data = $request->validate(['manager_notes' => ['required', 'string', 'max:3000']], [], ['manager_notes' => 'catatan atasan']);
        $this->monitoring->completeCheckin($checkin, $data['manager_notes'], (int) auth()->id());

        return redirect('performance/plans/'.$row->id.'/monitoring#checkin')->with('message', 'Check-in diselesaikan.')->with('status', 'success');
    }

    // ------------------------------------------------------------ perubahan KPI

    public function changeForm(int $plan)
    {
        [$row, $role] = $this->planAccess($plan);
        abort_unless($role === 'owner', 403);
        $period = $this->periods->find((int) $row->period_id);

        return view('performance.monitoring.change', [
            'pageTitle' => 'Pengajuan Perubahan KPI',
            'pageNote' => $row->position_title.' · versi KPI saat ini '.$row->kpi_version,
            'plan' => $row, 'period' => $period,
            'pending' => $this->changes->pending($row->id),
            'items' => old('items', $this->changes->current($row->id)),
            'types' => ScoreCalculator::TYPES, 'rules' => $period->policy['kpi'],
            'strategic' => TenantScope::table('strategic_objectives')->where('period_id', $period->id)->whereNull('deleted_at')->orderBy('code')
                ->get(['id', 'code', 'title'])->mapWithKeys(fn ($s) => [$s->id => $s->code.' — '.$s->title])->all(),
            'parents' => $this->plans->parentOptions($row)->mapWithKeys(fn ($o) => [$o->id => $o->full_name.': '.$o->title])->all(),
        ]);
    }

    public function submitChange(Request $request, int $plan)
    {
        [$row, $role] = $this->planAccess($plan);
        abort_unless($role === 'owner', 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000'], 'items' => ['required', 'array']], [], ['reason' => 'alasan perubahan']);
        $this->changes->submit($row->id, $data['items'], $data['reason'], (int) auth()->id());

        return redirect('performance/plans/'.$row->id.'/monitoring')->with('message', 'Perubahan KPI diajukan ke atasan.')->with('status', 'success');
    }

    // ---------------------------------------------------------------- akses

    /** @return array{0: object, 1: string} rencana & peran pengguna (owner|manager|hr) */
    private function planAccess(int $planId): array
    {
        $row = $this->plans->plan($planId);
        $userId = (int) auth()->id();
        if ((int) $row->user_id === $userId) {
            return [$row, 'owner'];
        }
        if (in_array($userId, $this->supervisorUsers($row), true)) {
            return [$row, 'manager'];
        }
        $model = new Sximo;
        $info = $model->makeInfo('perfperiods');
        $access = isset($info['id']) ? $model->validAccess($info['id'], session('gid')) : [];
        if (! empty($access['is_view']) && EmployeeScope::canSee((int) $row->employee_id)) {
            return [$row, 'hr'];
        }
        abort(404);
    }

    /** @return int[] pengguna atasan langsung (pemangku posisi di atas; naik bila kosong; delegasi berlaku) */
    private function supervisorUsers(object $plan): array
    {
        return collect($this->resolver->escalationFrom((int) $plan->position_id, (int) $plan->user_id))->pluck('user_id')->map(fn ($v) => (int) $v)->all();
    }
}
