<?php

namespace App\Http\Controllers\Employee;

use App\Domain\Employee\Actions\AssignEmployee;
use App\Domain\Employee\Actions\TerminateEmployee;
use App\Domain\Employee\Services\EmployeeScope;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Penugasan (primer/rangkap/Plt.) dan terminasi pegawai. */
class EmployeeAssignmentsController extends Controller
{
    use AuthorizesHcmsModule;

    protected function moduleName(): string
    {
        return 'employees';
    }

    public function store(Request $request, int $employee, AssignEmployee $action)
    {
        $this->authorizeTask('is_edit');
        $this->ensureVisible($employee);

        $data = $request->validate([
            'position_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(array_keys(AssignEmployee::TYPES))],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'decree_number' => ['nullable', 'max:100'],
            'decree_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'max:255'],
        ]);

        $id = $action->handle($employee, $data);
        $this->logActivity($request, 'Penugasan #'.$id.' ('.$data['type'].') untuk pegawai #'.$employee);

        return redirect('employees/'.$employee.'#assignments')->with('message', __('core.note_success'))->with('status', 'success');
    }

    public function end(Request $request, int $employee, int $assignment, AssignEmployee $action)
    {
        $this->authorizeTask('is_edit');
        $this->ensureVisible($employee);
        TenantScope::table('employee_assignments')->where('id', $assignment)->where('employee_id', $employee)->exists() ?: abort(404);

        $data = $request->validate(['effective_to' => ['required', 'date'], 'reason' => ['nullable', 'max:255']]);
        $action->end($assignment, $data['effective_to'], $data['reason'] ?? null);
        $this->logActivity($request, 'Akhiri penugasan #'.$assignment.' pegawai #'.$employee);

        return redirect('employees/'.$employee.'#assignments')->with('message', __('core.note_success'))->with('status', 'success');
    }

    /** Terminasi butuh izin Approve (keputusan berdampak besar). */
    public function terminate(Request $request, int $employee, TerminateEmployee $action)
    {
        $this->authorizeTask('is_approve');
        $this->ensureVisible($employee);

        $data = $request->validate([
            'termination_date' => ['required', 'date'],
            'type' => ['required', Rule::in(array_keys(TerminateEmployee::TYPES))],
            'reason' => ['required', 'string', 'max:500'],
            'rehire_eligible' => ['nullable', 'boolean'],
        ]);
        $data['rehire_eligible'] = $request->boolean('rehire_eligible');

        $id = $action->schedule($employee, $data);
        $this->logActivity($request, 'Terminasi #'.$id.' pegawai #'.$employee.' ('.$data['type'].', '.$data['termination_date'].')');

        return redirect('employees/'.$employee.'#termination')->with('message', __('core.note_success'))->with('status', 'success');
    }

    public function cancelTermination(Request $request, int $employee, int $termination, TerminateEmployee $action)
    {
        $this->authorizeTask('is_approve');
        $this->ensureVisible($employee);
        TenantScope::table('employee_terminations')->where('id', $termination)->where('employee_id', $employee)->exists() ?: abort(404);

        $action->cancel($termination);
        $this->logActivity($request, 'Batalkan terminasi #'.$termination.' pegawai #'.$employee);

        return redirect('employees/'.$employee.'#termination')->with('message', __('core.note_success'))->with('status', 'success');
    }

    private function ensureVisible(int $employee): void
    {
        if (! EmployeeScope::canSee($employee)) {
            abort(404);
        }
    }
}
