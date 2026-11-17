<?php

namespace App\Http\Controllers\Talent;

use App\Domain\Competency\Services\PositionReadiness;
use App\Domain\Employee\Services\EmployeeScope;
use App\Domain\Talent\Succession;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Talenta › Rencana Suksesi: posisi kritis, kandidat suksesor, pengesahan. Nama kandidat sensitif, akses tercatat. */
class SuccessionController extends Controller
{
    use AuthorizesHcmsModule;

    public function __construct(private Succession $succession)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'succession';
    }

    public function index()
    {
        $this->authorizeTask('is_view');
        $positions = $this->succession->criticalPositions();

        return view('talent.succession.index', [
            'pageTitle' => 'Rencana Suksesi', 'pageNote' => 'posisi kritis', 'positions' => $positions,
            'metrics' => Succession::metrics($positions, $this->succession->primaryCounts()),
            'risks' => Succession::RISKS, 'retirementAge' => $this->succession->retirementAge(),
        ]);
    }

    public function show(int $position)
    {
        $access = $this->authorizeTask('is_view');
        $row = $this->succession->criticalPositions()->firstWhere('id', $position) ?? abort(404);
        $plan = ! empty($access['is_edit']) ? $this->succession->plan($position, (int) auth()->id())
            : TenantScope::table('succession_plans')->where('position_id', $position)->first();
        AuditLog::sensitiveViewed('succession_plans', $plan->id ?? 0, 'Membuka rencana suksesi '.$row->code);
        $canEdit = ! empty($access['is_edit']);

        return view('talent.succession.show', [
            'pageTitle' => $row->title, 'pageNote' => $row->code, 'position' => $row, 'plan' => $plan,
            'candidates' => $plan ? $this->succession->candidates((int) $plan->id) : collect(),
            'suggestions' => $plan && $canEdit ? $this->succession->suggestions($plan) : collect(),
            'employees' => $canEdit ? EmployeeScope::query()->where('employees.status', 'active')->orderBy('employees.full_name')
                ->get(['employees.id', 'employees.full_name', 'employees.employee_number']) : collect(),
            'primaryCounts' => $this->succession->primaryCounts(),
            'roles' => Succession::ROLES, 'sources' => Succession::SOURCES, 'risks' => Succession::RISKS, 'horizons' => PositionReadiness::HORIZONS,
            'canEdit' => $canEdit, 'canApprove' => ! empty($access['is_approve']) && $plan && $plan->status === 'draft' && (int) $plan->last_changed_by !== (int) auth()->id(),
        ]);
    }

    public function addCandidate(Request $request, int $position)
    {
        $this->authorizeTask('is_edit');
        $plan = $this->succession->plan($position, (int) auth()->id());
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::in(EmployeeScope::query()->where('employees.status', 'active')->pluck('employees.id')->all())],
            'role' => ['required', Rule::in(array_keys(Succession::ROLES))],
            'readiness' => ['required', Rule::in(array_keys(PositionReadiness::HORIZONS))],
            'source' => ['required', Rule::in(array_keys(Succession::SOURCES))],
            'talent_pool_member_id' => ['nullable', 'integer', TenantScope::existsRule('talent_pool_members', 'id')],
            'rationale' => ['required', 'string', 'min:10', 'max:2000'],
            'development_action' => ['nullable', 'string', 'max:500'],
        ], ['employee_id.in' => 'Pegawai di luar cakupan data Anda.', 'rationale.min' => 'Alasan minimal 10 karakter.']);
        $this->succession->addCandidate($plan, $data, (int) auth()->id());

        return redirect('talent/succession/'.$position)->with('message', 'Kandidat ditambahkan; rencana kembali ke draft dan perlu disahkan.')->with('status', 'success');
    }

    public function updateCandidate(Request $request, int $candidate)
    {
        $this->authorizeTask('is_edit');
        $action = $request->input('action');
        if ($action === 'remove') {
            $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Alasan wajib diisi.']);
            $this->succession->removeCandidate($candidate, $data['reason'], (int) auth()->id());
        } else {
            $data = $request->validate([
                'role' => ['required', Rule::in(array_keys(Succession::ROLES))],
                'readiness' => ['required', Rule::in(array_keys(PositionReadiness::HORIZONS))],
                'development_action' => ['nullable', 'string', 'max:500'],
                'reason' => ['required', 'string', 'max:500'],
            ], ['reason.required' => 'Alasan perubahan wajib diisi.']);
            $this->succession->updateCandidate($candidate, $data, (int) auth()->id());
        }
        $position = (int) TenantScope::table('succession_candidates as c')->join('succession_plans as p', 'p.id', '=', 'c.plan_id')->where('c.id', $candidate)->value('p.position_id');

        return redirect('talent/succession/'.$position)->with('message', 'Kandidat diperbarui; rencana kembali ke draft.')->with('status', 'success');
    }

    public function approve(Request $request, int $position)
    {
        $this->authorizeTask('is_approve');
        $plan = TenantScope::table('succession_plans')->where('position_id', $position)->first() ?? abort(404);
        $this->succession->approve((int) $plan->id, (int) auth()->id());
        $this->logActivity($request, 'Rencana suksesi posisi #'.$position.' disahkan');

        return redirect('talent/succession/'.$position)->with('message', 'Rencana suksesi disahkan.')->with('status', 'success');
    }
}
