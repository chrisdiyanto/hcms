<?php

namespace App\Http\Controllers\Employee;

use App\Domain\Employee\Workflow\EmployeeChangeRequestSubject;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Employee Self Service: "Profil Saya" dan pengajuan perubahan data pribadi. */
class EssController extends Controller
{
    public function profile()
    {
        $employee = $this->me();

        return view('employee.ess', [
            'pageTitle' => 'Profil Saya',
            'pageNote' => $employee ? $employee->employee_number : '',
            'employee' => $employee,
            'fields' => EmployeeChangeRequestSubject::FIELDS,
            'requests' => $employee ? TenantScope::table('employee_change_requests')->where('employee_id', $employee->id)->orderByDesc('id')->limit(20)->get() : collect(),
            'marital' => EmployeesController::MARITAL,
            'ptkp' => EmployeesController::PTKP,
        ]);
    }

    public function submit(Request $request, EmployeeChangeRequestSubject $subject)
    {
        $employee = $this->me() ?? abort(404);

        $data = $request->validate([
            'phone' => ['nullable', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address_ktp' => ['nullable', 'max:1000'],
            'address_domicile' => ['nullable', 'max:1000'],
            'marital_status' => ['nullable', Rule::in(array_keys(EmployeesController::MARITAL))],
            'ptkp_status' => ['nullable', Rule::in(EmployeesController::PTKP)],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $subject->submit($employee, collect($data)->except('reason')->all(), $data['reason'], (int) auth()->id());

        return redirect('ess/profile')->with('message', 'Pengajuan perubahan dikirim ke HR untuk diverifikasi.')->with('status', 'success');
    }

    private function me(): ?object
    {
        return TenantScope::table('employees')->where('user_id', auth()->id())->whereNull('deleted_at')->where('status', 'active')->first();
    }
}
