<?php

namespace App\Http\Controllers\Employee;

use App\Domain\Employee\Actions\AssignEmployee;
use App\Domain\Employee\Actions\TerminateEmployee;
use App\Domain\Employee\Services\EmployeeHistory;
use App\Domain\Employee\Services\EmployeeIdentity;
use App\Domain\Employee\Services\EmployeeScope;
use App\Domain\Talent\CareerAspirations;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLog;
use App\Support\Audit\DataExport;
use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Master pegawai (blueprint 6.1). Daftar dan profil dibatasi data scope
 * pengguna; identitas negara hanya untuk pemegang izin Data Sensitif.
 */
class EmployeesController extends Controller
{
    use AuthorizesHcmsModule;

    public const EMPLOYMENT = ['pkwtt' => 'PKWTT (tetap)', 'pkwt' => 'PKWT (kontrak)', 'probation' => 'Masa percobaan', 'outsourcing' => 'Alih daya', 'internship' => 'Magang'];

    public const MARITAL = ['single' => 'Belum menikah', 'married' => 'Menikah', 'divorced' => 'Cerai hidup', 'widowed' => 'Cerai mati'];

    public const PTKP = ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'];

    protected function moduleName(): string
    {
        return 'employees';
    }

    public function index(Request $request)
    {
        $access = $this->authorizeTask('is_view');
        $filters = $this->filters($request);
        $rows = $this->listQuery($filters)->paginate(25)->withQueryString();

        return view('employee.index', [
            'pageTitle' => 'Pegawai',
            'pageNote' => '',
            'rows' => $rows,
            'filters' => $filters,
            'companies' => TenantScope::table('corporate')->orderBy('corporate_name')->pluck('corporate_name', 'id_corporate'),
            'access' => $access,
        ]);
    }

    /**
     * Ekspor CSV daftar pegawai sesuai filter & data scope. Wajib alasan, diberi
     * watermark, tercatat; identitas negara tidak pernah ikut diekspor.
     */
    public function export(Request $request)
    {
        $access = $this->moduleAccess();
        if (empty($access['is_csv']) && empty($access['is_excel'])) {
            $this->authorizeTask('is_csv');
        }

        $reason = (string) $request->input('export_reason');
        if (! DataExport::validReason($reason)) {
            return view('sximo.module.utility.export-reason', [
                'action' => $request->url(), 'query' => $request->except('export_reason'),
                'threshold' => DataExport::MASS_THRESHOLD, 'min' => DataExport::MIN_REASON,
                'error' => $request->has('export_reason') ? 'Alasan minimal '.DataExport::MIN_REASON.' karakter.' : null,
                'pageTitle' => 'Ekspor Pegawai', 'pageNote' => '',
            ]);
        }

        $filters = $this->filters($request);
        $rows = $this->listQuery($filters)->get();
        if (! DataExport::allowed($rows->count(), $access)) {
            return redirect('employees')->with('message', 'Ekspor lebih dari '.DataExport::MASS_THRESHOLD.' baris membutuhkan izin Approve. Persempit filter.')->with('status', 'error');
        }

        $logId = DataExport::log('employees', 'csv', $rows->count(), $reason, $filters);
        $watermark = DataExport::watermark($logId);

        return response()->streamDownload(function () use ($rows, $watermark) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, [$watermark]);
            fputcsv($out, ['NIP', 'Nama', 'Company', 'Posisi', 'Unit', 'Status kerja', 'Bergabung', 'Status']);
            foreach ($rows as $e) {
                fputcsv($out, [$e->employee_number, $e->full_name, $e->corporate_name, $e->position_title, $e->unit_name,
                    self::EMPLOYMENT[$e->employment_status] ?? $e->employment_status, $e->join_date, $e->status === 'active' ? 'Aktif' : 'Berhenti']);
            }
            fclose($out);
        }, 'pegawai-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->input('q')),
            'company' => $request->input('company'),
            'status' => $request->input('status', 'active'),
        ];
    }

    /** Daftar pegawai dalam data scope, dengan posisi primer aktif hari ini. */
    private function listQuery(array $filters): Builder
    {
        $today = now()->toDateString();

        return EmployeeScope::query()
            ->leftJoin('employee_assignments as pa', function ($join) use ($today) {
                $join->on('pa.employee_id', '=', 'employees.id')->where('pa.type', 'primary')
                    ->where('pa.effective_from', '<=', $today)
                    ->where(fn ($w) => $w->whereNull('pa.effective_to')->orWhere('pa.effective_to', '>=', $today));
            })
            ->leftJoin('positions', 'positions.id', '=', 'pa.position_id')
            ->leftJoin('unit', 'unit.id_unit', '=', 'positions.unit_id')
            ->leftJoin('corporate', 'corporate.id_corporate', '=', 'employees.company_id')
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('employees.full_name', 'like', '%'.$filters['q'].'%')
                ->orWhere('employees.employee_number', 'like', '%'.$filters['q'].'%')))
            ->when($filters['company'], fn ($q) => $q->where('employees.company_id', $filters['company']))
            ->when(in_array($filters['status'], ['active', 'terminated'], true), fn ($q) => $q->where('employees.status', $filters['status']))
            ->select('employees.*', 'positions.title as position_title', 'unit.unit_name', 'corporate.corporate_name')
            ->orderBy('employees.full_name');
    }

    public function create()
    {
        $this->authorizeTask('is_add');

        return $this->form(null);
    }

    public function store(Request $request)
    {
        $this->authorizeTask('is_add');
        $data = $this->validated($request, null);

        $id = DB::transaction(function () use ($data, $request) {
            $id = (int) DB::table('employees')->insertGetId(TenantScope::fillForInsert('employees', $data + [
                'status' => 'active', 'created_by' => auth()->id(), 'updated_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]));
            EmployeeHistory::diff($id, null, TenantScope::table('employees')->where('id', $id)->first());
            AuditLog::created('employees', $id, TenantScope::table('employees')->where('id', $id)->first());
            if ($this->can('is_sensitive')) {
                EmployeeIdentity::save($id, $request->only(array_keys(EmployeeIdentity::FIELDS)));
            }

            return $id;
        });

        $this->logActivity($request, 'Tambah pegawai #'.$id.' ('.$data['employee_number'].')');

        return redirect('employees/'.$id)->with('message', __('core.note_success'))->with('status', 'success');
    }

    public function show(int $id)
    {
        $access = $this->authorizeTask('is_view');
        $employee = $this->findVisible($id);
        $today = now()->toDateString();

        $assignments = TenantScope::table('employee_assignments')
            ->join('positions', 'positions.id', '=', 'employee_assignments.position_id')
            ->leftJoin('unit', 'unit.id_unit', '=', 'positions.unit_id')
            ->where('employee_assignments.employee_id', $id)
            ->orderByDesc('employee_assignments.effective_from')
            ->get(['employee_assignments.*', 'positions.code as position_code', 'positions.title as position_title', 'unit.unit_name']);

        $records = [];
        foreach (EmployeeRecordsController::TYPES as $type => $def) {
            $records[$type] = TenantScope::table($def['table'])->where('employee_id', $id)->orderBy('id')->get();
        }

        return view('employee.show', [
            'pageTitle' => $employee->full_name,
            'pageNote' => $employee->employee_number,
            'employee' => $employee,
            'company' => TenantScope::table('corporate')->where('id_corporate', $employee->company_id)->value('corporate_name'),
            'tenure' => $this->tenure($employee),
            'access' => $access,
            'identity' => $this->can('is_sensitive') ? EmployeeIdentity::masked($id) : null,
            'records' => $records,
            'careerHistory' => app(CareerAspirations::class)->history($id),
            'recordTypes' => EmployeeRecordsController::TYPES,
            'assignments' => $assignments,
            'activeAssignments' => $assignments->filter(fn ($a) => $a->effective_from <= $today && (! $a->effective_to || $a->effective_to >= $today)),
            'positions' => TenantScope::table('positions')->whereNull('deleted_at')->where('status', 'active')->orderBy('code')
                ->get(['id', 'code', 'title'])->mapWithKeys(fn ($p) => [$p->id => $p->code.' — '.$p->title]),
            'assignmentTypes' => AssignEmployee::TYPES,
            'histories' => TenantScope::table('employee_status_histories')->where('employee_id', $id)->orderByDesc('id')->limit(50)->get(),
            'terminations' => TenantScope::table('employee_terminations')->where('employee_id', $id)->orderByDesc('id')->get(),
            'terminationTypes' => TerminateEmployee::TYPES,
            'labels' => ['employment' => self::EMPLOYMENT, 'marital' => self::MARITAL],
        ]);
    }

    public function edit(int $id)
    {
        $this->authorizeTask('is_edit');

        return $this->form($this->findVisible($id));
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeTask('is_edit');
        $before = $this->findVisible($id);
        $data = $this->validated($request, $before);

        DB::transaction(function () use ($id, $data, $before, $request) {
            TenantScope::table('employees')->where('id', $id)->update(TenantScope::guardForUpdate('employees', $data + ['updated_by' => auth()->id(), 'updated_at' => now()]));
            EmployeeHistory::diff($id, $before, TenantScope::table('employees')->where('id', $id)->first());
            AuditLog::updated('employees', $id, $before, TenantScope::table('employees')->where('id', $id)->first());
            if ($this->can('is_sensitive') && $request->boolean('update_identity')) {
                EmployeeIdentity::save($id, $request->only(array_keys(EmployeeIdentity::FIELDS)));
            }
        });

        $this->logActivity($request, 'Ubah pegawai #'.$id);

        return redirect('employees/'.$id)->with('message', __('core.note_success'))->with('status', 'success');
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorizeTask('is_remove');
        $employee = $this->findVisible($id);

        if (TenantScope::table('employee_assignments')->where('employee_id', $id)->exists()) {
            return redirect('employees/'.$id)->with('message', 'Pegawai yang sudah punya riwayat penugasan tidak dapat dihapus. Gunakan terminasi.')->with('status', 'error');
        }

        TenantScope::table('employees')->where('id', $id)->update(['deleted_at' => now(), 'updated_by' => auth()->id()]);
        AuditLog::deleted('employees', $id, $employee, 'Soft delete');
        $this->logActivity($request, 'Hapus pegawai #'.$id.' ('.$employee->employee_number.')');

        return redirect('employees')->with('message', __('core.note_success_delete'))->with('status', 'success');
    }

    /** Buka identitas lengkap (izin Data Sensitif); setiap pembukaan dicatat. */
    public function revealIdentity(Request $request, int $id)
    {
        $this->authorizeTask('is_sensitive');
        $this->findVisible($id);
        AuditLog::sensitiveViewed('employee_identities', $id, 'Membuka identitas negara pegawai #'.$id);

        return response()->json(EmployeeIdentity::reveal($id));
    }

    // ---------------------------------------------------------------- bantuan

    private function form(?object $employee)
    {
        $id = $employee->id ?? null;
        $linkedUsers = TenantScope::table('employees')->whereNotNull('user_id')->when($id, fn ($q) => $q->where('id', '!=', $id))->pluck('user_id');

        return view('employee.form', [
            'pageTitle' => $employee ? 'Ubah Pegawai' : 'Pegawai Baru',
            'pageNote' => $employee->employee_number ?? '',
            'employee' => $employee,
            'companies' => TenantScope::table('corporate')->orderBy('corporate_name')->pluck('corporate_name', 'id_corporate'),
            'users' => TenantScope::table('tb_users')->whereNotIn('id', $linkedUsers)->orderBy('username')
                ->get(['id', 'username', 'email'])->mapWithKeys(fn ($u) => [$u->id => $u->username.' ('.$u->email.')']),
            'canSensitive' => $this->can('is_sensitive'),
            'hasIdentity' => $id && TenantScope::table('employee_identities')->where('employee_id', $id)->exists(),
            'labels' => ['employment' => self::EMPLOYMENT, 'marital' => self::MARITAL, 'ptkp' => self::PTKP],
        ]);
    }

    private function validated(Request $request, ?object $employee): array
    {
        $id = $employee->id ?? null;
        $rules = [
            'company_id' => ['required', 'integer', TenantScope::existsRule('corporate', 'id_corporate')],
            'employee_number' => ['required', 'max:30', 'regex:/^[A-Za-z0-9._\/-]+$/', TenantScope::uniqueRule('employees', 'employee_number')->ignore($id)],
            'full_name' => ['required', 'max:150'],
            'birth_place' => ['nullable', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:M,F'],
            'marital_status' => ['nullable', Rule::in(array_keys(self::MARITAL))],
            'ptkp_status' => ['nullable', Rule::in(self::PTKP)],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'max:30'],
            'address_ktp' => ['nullable', 'max:1000'],
            'address_domicile' => ['nullable', 'max:1000'],
            'employment_status' => ['required', Rule::in(array_keys(self::EMPLOYMENT))],
            'join_date' => ['required', 'date'],
            'probation_end_date' => ['nullable', 'required_if:employment_status,probation', 'date', 'after:join_date'],
            'contract_end_date' => ['nullable', 'required_if:employment_status,pkwt', 'date', 'after:join_date'],
            'user_id' => ['nullable', 'integer', TenantScope::existsRule('tb_users', 'id'), Rule::unique('employees', 'user_id')->ignore($id)],
        ];

        $identity = $this->can('is_sensitive') && ($employee === null || $request->boolean('update_identity'));
        if ($identity) {
            $rules += [
                'nik_ktp' => ['nullable', 'digits:16', function ($a, $v, $fail) use ($id) {
                    if ($v && EmployeeIdentity::nikTaken($v, $id)) {
                        $fail('NIK KTP sudah dipakai pegawai lain.');
                    }
                }],
                'npwp' => ['nullable', 'regex:/^[0-9.\-]{15,20}$/'],
                'bpjs_kesehatan' => ['nullable', 'digits_between:11,13'],
                'bpjs_ketenagakerjaan' => ['nullable', 'digits_between:11,13'],
            ];
        }

        $data = $request->validate($rules);
        $data['employee_number'] = strtoupper($data['employee_number']);

        return array_intersect_key($data, array_flip(['company_id', 'employee_number', 'full_name', 'birth_place', 'birth_date', 'gender', 'marital_status', 'ptkp_status',
            'email', 'phone', 'address_ktp', 'address_domicile', 'employment_status', 'join_date', 'probation_end_date', 'contract_end_date', 'user_id'])) + array_fill_keys(
                ['birth_place', 'birth_date', 'gender', 'marital_status', 'ptkp_status', 'email', 'phone', 'address_ktp', 'address_domicile', 'probation_end_date', 'contract_end_date', 'user_id'], null);
    }

    private function findVisible(int $id): object
    {
        if (! EmployeeScope::canSee($id)) {
            abort(404);
        }

        return TenantScope::table('employees')->where('id', $id)->whereNull('deleted_at')->first() ?? abort(404);
    }

    /** Masa kerja dihitung, bukan diisi (blueprint 6.1). */
    private function tenure(object $employee): string
    {
        $end = $employee->terminated_at ? Carbon::parse($employee->terminated_at) : now();
        $diff = Carbon::parse($employee->join_date)->diff($end);

        return $diff->invert ? 'Belum bergabung' : $diff->y.' tahun '.$diff->m.' bulan';
    }
}
