<?php

namespace App\Http\Controllers\Employee;

use App\Domain\Development\TrainingNeeds;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Layanan Mandiri › Usulan Pelatihan: pegawai untuk dirinya, atasan untuk bawahan langsung (sumber "permintaan"). */
class TrainingRequestsController extends Controller
{
    public function __construct(private TrainingNeeds $needs)
    {
        parent::__construct();
    }

    public function index()
    {
        $me = $this->me();
        $team = $this->needs->directReports((int) auth()->id());
        $ids = $team->pluck('id')->when($me, fn ($c) => $c->prepend($me->id))->all();
        $published = TenantScope::table('competency_framework_versions')->where('status', 'published')->value('id');

        return view('employee.training-requests', [
            'pageTitle' => 'Usulan Pelatihan', 'pageNote' => $me ? $me->employee_number : '',
            'me' => $me, 'team' => $team,
            'rows' => TenantScope::table('training_needs')->join('employees', 'employees.id', '=', 'training_needs.employee_id')
                ->whereIn('training_needs.employee_id', $ids)->where('training_needs.status', '!=', 'suggested')
                ->orderByDesc('training_needs.id')->limit(100)->get(['training_needs.*', 'employees.full_name']),
            'competencies' => $published ? TenantScope::table('competencies')->where('framework_version_id', $published)->orderBy('code')->get(['id', 'code', 'name']) : collect(),
            'statuses' => TrainingNeeds::STATUS, 'priorities' => TrainingNeeds::PRIORITIES, 'year' => now()->year,
        ]);
    }

    public function store(Request $request)
    {
        $me = $this->me();
        $allowed = $this->needs->directReports((int) auth()->id())->pluck('id')->when($me, fn ($c) => $c->push($me->id))->all();
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::in($allowed)],
            'period_year' => ['required', 'integer', 'between:'.now()->year.','.(now()->year + 1)],
            'competency_id' => ['nullable', 'integer', TenantScope::existsRule('competencies', 'id')],
            'topic' => ['nullable', 'string', 'max:200', 'required_without:competency_id'],
            'reason' => ['required', 'string', 'max:2000'],
            'evidence' => ['nullable', 'string', 'max:2000'],
            'priority' => ['required', Rule::in(array_keys(TrainingNeeds::PRIORITIES))],
            'suggested_intervention' => ['nullable', 'string', 'max:255'],
        ], ['employee_id.in' => 'Anda hanya dapat mengusulkan untuk diri sendiri atau bawahan langsung.']);

        $this->needs->propose((int) $data['employee_id'], (int) $data['period_year'], 'request', $data, (int) auth()->id());

        return redirect('ess/training-requests')->with('message', 'Usulan dikirim ke HR untuk ditinjau.')->with('status', 'success');
    }

    private function me(): ?object
    {
        return TenantScope::table('employees')->where('user_id', auth()->id())->whereNull('deleted_at')->where('status', 'active')->first();
    }
}
