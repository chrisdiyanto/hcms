<?php

namespace App\Http\Controllers\Development;

use App\Domain\Development\TrainingNeeds;
use App\Domain\Employee\Services\EmployeeScope;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** HR: daftar, pengajuan kebutuhan organisasi, tinjauan, dan rincian tindak lanjut TNA. */
class NeedsController extends Controller
{
    use AuthorizesHcmsModule;

    public function __construct(private TrainingNeeds $needs)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'trainingneeds';
    }

    public function index(Request $request)
    {
        $access = $this->authorizeTask('is_view');
        $year = $this->year($request);
        $status = array_key_exists((string) $request->query('status'), TrainingNeeds::STATUS) ? (string) $request->query('status') : 'proposed';

        $base = $this->needs->visible()->where('training_needs.period_year', $year);
        $counts = (clone $base)->selectRaw('training_needs.status, COUNT(*) n')->groupBy('training_needs.status')->pluck('n', 'status');
        $rows = (clone $base)
            ->leftJoin('employee_assignments as a', function ($j) {
                $today = now()->toDateString();
                $j->on('a.employee_id', '=', 'employees.id')->where('a.type', 'primary')->where('a.effective_from', '<=', $today)
                    ->where(fn ($w) => $w->whereNull('a.effective_to')->orWhere('a.effective_to', '>=', $today));
            })
            ->leftJoin('positions as p', 'p.id', '=', 'a.position_id')->leftJoin('unit as u', 'u.id_unit', '=', 'p.unit_id')
            ->where('training_needs.status', $status)
            ->when($request->query('source'), fn ($q, $v) => $q->where('training_needs.source', $v))
            ->when($request->query('priority'), fn ($q, $v) => $q->where('training_needs.priority', $v))
            ->when($request->filled('unit'), fn ($q) => $q->where('p.unit_id', (int) $request->query('unit')))
            ->when(trim((string) $request->query('q')), fn ($q, $v) => $q->where(fn ($w) => $w->where('employees.full_name', 'like', '%'.$v.'%')
                ->orWhere('training_needs.topic', 'like', '%'.$v.'%')))
            ->orderByRaw("FIELD(training_needs.priority, 'high', 'medium', 'low')")->orderByDesc('training_needs.priority_score')->orderBy('employees.full_name')
            ->select(['training_needs.*', 'employees.full_name', 'employees.employee_number', 'u.unit_name'])
            ->paginate(50)->withQueryString();

        return view('development.needs.index', [
            'pageTitle' => 'Kebutuhan Pelatihan', 'pageNote' => 'TNA '.$year,
            'year' => $year, 'status' => $status, 'counts' => $counts, 'rows' => $rows,
            'units' => TenantScope::table('unit')->orderBy('unit_name')->pluck('unit_name', 'id_unit'),
            'statuses' => TrainingNeeds::STATUS, 'sources' => TrainingNeeds::SOURCES, 'priorities' => TrainingNeeds::PRIORITIES,
            'canEdit' => ! empty($access['is_edit']), 'canApprove' => ! empty($access['is_approve']),
        ]);
    }

    public function refresh(Request $request)
    {
        $this->authorizeTask('is_edit');
        $year = $this->year($request);
        $result = $this->needs->refreshSuggestions($year);
        $this->logActivity($request, 'Saran TNA '.$year.' diperbarui');

        return redirect('development/needs?status=suggested&year='.$year)->with('message', sprintf('Saran diperbarui: %d baru dari gap, %d baru dari PIP, %d saran usang dihapus.',
            $result['gap'], $result['performance'], $result['removed']))->with('status', 'success');
    }

    public function create(Request $request)
    {
        $this->authorizeTask('is_add');

        return view('development.needs.form', $this->formData($request) + ['row' => null, 'pageTitle' => 'Kebutuhan Organisasi', 'pageNote' => 'ajukan untuk pegawai/unit']);
    }

    public function store(Request $request)
    {
        $this->authorizeTask('is_add');
        $data = $request->validate($this->rules() + [
            'employee_ids' => ['nullable', 'array'], 'employee_ids.*' => ['integer'],
            'unit_id' => ['nullable', 'integer', 'required_without:employee_ids'],
            'period_year' => ['required', 'integer', 'between:2020,2100'],
        ], ['unit_id.required_without' => 'Pilih pegawai atau unit.']);

        $visible = EmployeeScope::query()->select('employees.id');
        $employees = collect($data['employee_ids'] ?? [])->map(fn ($v) => (int) $v);
        if (! empty($data['unit_id'])) {
            $today = now()->toDateString();
            $employees = $employees->merge(TenantScope::table('employee_assignments')->join('positions', 'positions.id', '=', 'employee_assignments.position_id')
                ->where('positions.unit_id', $data['unit_id'])->where('employee_assignments.type', 'primary')
                ->where('employee_assignments.effective_from', '<=', $today)
                ->where(fn ($w) => $w->whereNull('employee_assignments.effective_to')->orWhere('employee_assignments.effective_to', '>=', $today))
                ->pluck('employee_assignments.employee_id'));
        }
        $employees = TenantScope::table('employees')->whereIn('id', $employees->unique())->whereIn('id', $visible)->where('status', 'active')->pluck('id');
        if ($employees->isEmpty()) {
            return back()->withInput()->withErrors(['employee_ids' => 'Tidak ada pegawai aktif dalam cakupan data Anda yang dipilih.']);
        }
        foreach ($employees as $employeeId) {
            $this->needs->propose((int) $employeeId, (int) $data['period_year'], 'organization', $data, (int) auth()->id());
        }
        $this->logActivity($request, 'Kebutuhan organisasi diajukan untuk '.$employees->count().' pegawai');

        return redirect('development/needs?status=proposed&year='.$data['period_year'])
            ->with('message', $employees->count().' kebutuhan diajukan dan menunggu persetujuan.')->with('status', 'success');
    }

    public function edit(Request $request, int $id)
    {
        $this->authorizeTask('is_edit');
        $row = $this->needs->visible()->where('training_needs.id', $id)->first(['training_needs.*', 'employees.full_name', 'employees.employee_number']) ?? abort(404);

        return view('development.needs.form', $this->formData($request) + ['row' => $row, 'pageTitle' => 'Rincian Kebutuhan', 'pageNote' => $row->full_name]);
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeTask('is_edit');
        $data = $request->validate([
            'priority' => ['required', Rule::in(array_keys(TrainingNeeds::PRIORITIES))],
            'strategic_objective_id' => ['nullable', 'integer', TenantScope::existsRule('strategic_objectives', 'id')],
            'impact_if_unaddressed' => ['nullable', 'string', 'max:2000'],
            'suggested_intervention' => ['nullable', 'string', 'max:255'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'due_date' => ['nullable', 'date'],
            'owner_user_id' => ['nullable', 'integer', TenantScope::existsRule('tb_users', 'id')],
        ]);
        $this->needs->updateDetails($id, $data);

        return redirect('development/needs/'.$id.'/edit')->with('message', 'Rincian tersimpan.')->with('status', 'success');
    }

    /** Setujui/tolak satu atau beberapa kebutuhan sekaligus. */
    public function review(Request $request)
    {
        $this->authorizeTask('is_approve');
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'], 'ids.*' => ['integer'],
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:500'],
        ], ['ids.required' => 'Pilih minimal satu kebutuhan.']);
        $count = $this->needs->review($data['ids'], $data['decision'], $data['review_note'] ?? null, (int) auth()->id());
        $this->logActivity($request, 'Tinjauan TNA: '.$count.' '.$data['decision']);

        return back()->with('message', $count.' kebutuhan '.($data['decision'] === 'approved' ? 'disetujui' : 'ditolak').'.')->with('status', 'success');
    }

    // ---------------------------------------------------------------- bantuan

    private function year(Request $request): int
    {
        $year = (int) $request->input('year', now()->year);

        return $year >= 2020 && $year <= 2100 ? $year : now()->year;
    }

    private function rules(): array
    {
        return [
            'competency_id' => ['nullable', 'integer', TenantScope::existsRule('competencies', 'id')],
            'topic' => ['nullable', 'string', 'max:200', 'required_without:competency_id'],
            'reason' => ['required', 'string', 'max:2000'],
            'evidence' => ['nullable', 'string', 'max:2000'],
            'priority' => ['nullable', Rule::in(array_keys(TrainingNeeds::PRIORITIES))],
            'strategic_objective_id' => ['nullable', 'integer', TenantScope::existsRule('strategic_objectives', 'id')],
            'suggested_intervention' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }

    private function formData(Request $request): array
    {
        $published = TenantScope::table('competency_framework_versions')->where('status', 'published')->value('id');

        return [
            'year' => $this->year($request),
            'employees' => EmployeeScope::query()->where('employees.status', 'active')->orderBy('employees.full_name')
                ->get(['employees.id', 'employees.full_name', 'employees.employee_number']),
            'units' => TenantScope::table('unit')->orderBy('unit_name')->pluck('unit_name', 'id_unit'),
            'competencies' => $published ? TenantScope::table('competencies')->where('framework_version_id', $published)->orderBy('code')->get(['id', 'code', 'name']) : collect(),
            'objectives' => TenantScope::table('strategic_objectives')->whereNull('deleted_at')->orderBy('code')->get(['id', 'code', 'title']),
            'owners' => TenantScope::table('tb_users')->where('active', 1)->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'priorities' => TrainingNeeds::PRIORITIES, 'sources' => TrainingNeeds::SOURCES, 'statuses' => TrainingNeeds::STATUS,
        ];
    }
}
