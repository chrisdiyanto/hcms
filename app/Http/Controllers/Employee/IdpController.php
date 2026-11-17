<?php

namespace App\Http\Controllers\Employee;

use App\Domain\Development\Idps;
use App\Domain\Development\TrainingNeeds;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Layanan Mandiri › IDP Saya: pegawai menyusun & melaksanakan IDP; atasan langsung meninjau
 * (persetujuan lewat Tugas Persetujuan) dan memberi catatan; HR dengan izin Rekap IDP dapat melihat.
 */
class IdpController extends Controller
{
    use AuthorizesHcmsModule;

    public function __construct(private Idps $idps, private TrainingNeeds $needs)
    {
        parent::__construct();
    }

    /** Hak HR dibaca dari modul Rekap IDP; halaman ini sendiri terbuka untuk semua pegawai. */
    protected function moduleName(): string
    {
        return 'idpadmin';
    }

    public function index(Request $request)
    {
        $year = $this->year($request);
        $me = $this->me();
        $team = $this->needs->directReports((int) auth()->id());

        return view('employee.idp.index', [
            'pageTitle' => 'IDP Saya', 'pageNote' => (string) $year, 'year' => $year, 'me' => $me,
            'current' => $me ? $this->idps->current((int) $me->id, $year) : null,
            'history' => $me ? TenantScope::table('idps')->where('employee_id', $me->id)->orderByDesc('period_year')->orderByDesc('id')->get() : collect(),
            'teamIdps' => $team->isEmpty() ? collect() : TenantScope::table('idps')->join('employees', 'employees.id', '=', 'idps.employee_id')
                ->whereIn('idps.employee_id', $team->pluck('id'))->where('idps.period_year', $year)->where('idps.status', '!=', 'cancelled')
                ->get(['idps.*', 'employees.full_name']),
            'team' => $team, 'statuses' => Idps::STATUS,
        ]);
    }

    public function store(Request $request)
    {
        $me = $this->me() ?? abort(404);
        $year = $this->year($request);
        $id = $this->idps->createDraft((int) $me->id, $year, (int) auth()->id());

        return redirect('ess/idp/'.$id)->with('message', 'Draft IDP dibuat dari kebutuhan pelatihan yang disetujui dan saran kompetensi Anda. Lengkapi lalu ajukan ke atasan.')
            ->with('status', 'success');
    }

    public function show(int $id)
    {
        $idp = $this->idps->find($id) ?? abort(404);
        $userId = (int) auth()->id();
        abort_unless($this->idps->canView($idp, $userId, $this->can('is_view')), 404);
        $published = TenantScope::table('competency_framework_versions')->where('status', 'published')->value('id');

        return view('employee.idp.show', [
            'pageTitle' => 'IDP '.$idp->period_year, 'pageNote' => $idp->full_name,
            'idp' => $idp, 'activities' => $this->idps->activities($id),
            'isOwner' => (int) $idp->user_id === $userId, 'isManager' => $this->idps->isManager($idp, $userId),
            'competencies' => $published ? TenantScope::table('competencies')->where('framework_version_id', $published)->orderBy('code')->get(['id', 'code', 'name']) : collect(),
            'interventions' => TenantScope::table('development_interventions')->whereNull('deleted_at')->where('is_active', true)->orderBy('name')
                ->get(['id', 'code', 'name', 'type', 'competency_id', 'cost_per_participant']),
            'statuses' => Idps::STATUS, 'activityStatuses' => Idps::ACTIVITY_STATUS, 'types' => Idps::TYPES,
            'needs' => TenantScope::table('training_needs')->whereIn('id', $this->idps->activities($id)->pluck('training_need_id')->filter())->pluck('topic', 'id'),
        ]);
    }

    /** Simpan draft dan/atau ajukan (pemilik saja). */
    public function update(Request $request, int $id)
    {
        $idp = $this->idps->find($id) ?? abort(404);
        abort_unless((int) $idp->user_id === (int) auth()->id(), 403);
        $data = $request->validate([
            'career_goal' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.title' => ['required', 'string', 'max:200'],
            'items.*.goal' => ['required', 'string', 'max:255'],
            'items.*.activity_type' => ['required', Rule::in(array_keys(Idps::TYPES))],
            'items.*.competency_id' => ['nullable', 'integer', TenantScope::existsRule('competencies', 'id')],
            'items.*.target_level' => ['nullable', 'integer', 'between:1,5'],
            'items.*.intervention_id' => ['nullable', 'integer', TenantScope::existsRule('development_interventions', 'id')],
            'items.*.due_date' => ['required', 'date', 'after_or_equal:'.$idp->period_year.'-01-01', 'before_or_equal:'.($idp->period_year + 1).'-12-31'],
            'items.*.cost_estimate' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'items.*.employee_note' => ['nullable', 'string', 'max:1000'],
            'action' => ['nullable', Rule::in(['save', 'submit'])],
        ], [], ['items.*.title' => 'aktivitas', 'items.*.goal' => 'sasaran', 'items.*.due_date' => 'tenggat']);

        $this->idps->saveDraft($id, $data['career_goal'] ?? null, $data['items']);
        if (($data['action'] ?? 'save') === 'submit') {
            $this->idps->submit($id, (int) auth()->id());

            return redirect('ess/idp/'.$id)->with('message', 'IDP diajukan ke atasan langsung.')->with('status', 'success');
        }

        return redirect('ess/idp/'.$id)->with('message', 'Draft tersimpan.')->with('status', 'success');
    }

    /** Pelaksanaan aktivitas (pemilik) atau catatan atasan. */
    public function activity(Request $request, int $activity)
    {
        $row = TenantScope::table('idp_activities')->where('id', $activity)->first() ?? abort(404);
        $idp = $this->idps->find((int) $row->idp_id);
        $userId = (int) auth()->id();
        $isOwner = (int) $idp->user_id === $userId;
        $isManager = $this->idps->isManager($idp, $userId) || $this->can('is_edit');
        abort_unless($isOwner || $isManager, 403);

        $data = $request->validate($isOwner ? [
            'status' => ['required', Rule::in(array_keys(Idps::ACTIVITY_STATUS))],
            'evidence' => ['nullable', 'string', 'max:2000'],
            'cost_actual' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'certification_name' => ['nullable', 'string', 'max:200'],
            'certification_expiry' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'before_or_equal:today'],
            'employee_note' => ['nullable', 'string', 'max:1000'],
        ] : ['manager_note' => ['required', 'string', 'max:1000']]);
        $this->idps->updateActivity($activity, $data, ! $isOwner);

        return redirect('ess/idp/'.$idp->id)->with('message', 'Aktivitas diperbarui.')->with('status', 'success');
    }

    private function year(Request $request): int
    {
        $year = (int) $request->input('year', now()->year);

        return $year >= 2020 && $year <= 2100 ? $year : now()->year;
    }

    private function me(): ?object
    {
        return TenantScope::table('employees')->where('user_id', auth()->id())->whereNull('deleted_at')->where('status', 'active')->first();
    }
}
