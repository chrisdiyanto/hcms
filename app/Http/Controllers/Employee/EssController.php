<?php

namespace App\Http\Controllers\Employee;

use App\Domain\Employee\Services\EmployeeIdentity;
use App\Domain\Employee\Workflow\EmployeeChangeRequestSubject;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLog;
use App\Support\Audit\DataExport;
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

    /**
     * Hak akses subjek data (UU PDP): pegawai mengunduh salinan data pribadinya sendiri (JSON),
     * termasuk identitas yang didekripsi, riwayat, kompetensi, dan kinerja. Tercatat di audit & log ekspor.
     */
    public function download()
    {
        $employee = $this->me() ?? abort(404);
        $id = (int) $employee->id;
        $rows = fn (string $table, string $order = 'id') => TenantScope::table($table)->where('employee_id', $id)->orderBy($order)->get()
            ->map(fn ($r) => collect((array) $r)->except(['tenant_id', 'nik_hash'])->all())->all();
        $planIds = TenantScope::table('performance_plans')->where('employee_id', $id)->pluck('id');

        $data = [
            'diekspor_pada' => now()->toIso8601String(),
            'pegawai' => collect((array) $employee)->except(['tenant_id'])->all(),
            'identitas' => EmployeeIdentity::reveal($id),
            'akun' => TenantScope::table('tb_users')->where('id', auth()->id())->first(['username', 'email', 'first_name', 'last_name', 'last_login', 'mfa_enabled_at']),
            'keluarga' => $rows('employee_families'),
            'kontak_darurat' => $rows('employee_emergency_contacts'),
            'pendidikan' => $rows('employee_education'),
            'pengalaman_kerja' => $rows('employee_experiences'),
            'sertifikasi' => $rows('employee_certifications'),
            'penugasan' => $rows('employee_assignments', 'effective_from'),
            'riwayat_status' => $rows('employee_status_histories', 'effective_date'),
            'pengajuan_perubahan_data' => $rows('employee_change_requests'),
            'kompetensi' => $rows('employee_competencies', 'competency_code'),
            'kesesuaian_jabatan' => $rows('job_fit_results'),
            'riwayat_skor_kompetensi' => $rows('employee_competency_history', 'assessed_at'),
            'riwayat_kesesuaian_jabatan' => $rows('job_fit_history', 'computed_at'),
            'riwayat_gap_kompetensi' => $rows('competency_gap_history', 'job_fit_history_id'),
            'rencana_kinerja' => TenantScope::table('performance_plans')->where('employee_id', $id)->get()->map(fn ($p) => (array) $p)->all(),
            'kpi' => TenantScope::table('performance_objectives')->whereIn('plan_id', $planIds)->get()->map(fn ($o) => (array) $o)->all(),
            'realisasi_kpi' => TenantScope::table('performance_updates')->whereIn('plan_id', $planIds)->get()->map(fn ($u) => (array) $u)->all(),
            'rating_kinerja' => $rows('performance_ratings'),
            'keberatan' => $rows('performance_appeals'),
            'pip' => $rows('performance_improvement_plans'),
            'riwayat_login' => TenantScope::table('login_histories')->where('user_id', auth()->id())->orderByDesc('id')->limit(100)
                ->get(['created_at', 'event', 'ip_address', 'user_agent'])->all(),
        ];

        $logId = DataExport::log('ess_my_data', 'json', 1, 'Permintaan akses data pribadi oleh subjek data', ['employee' => $employee->employee_number]);
        AuditLog::sensitiveViewed('employees', $id, 'Pegawai mengunduh salinan data pribadinya (hak akses subjek data, log ekspor #'.$logId.')');

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, 'data-saya-'.$employee->employee_number.'-'.now()->format('Ymd').'.json', ['Content-Type' => 'application/json; charset=UTF-8']);
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
