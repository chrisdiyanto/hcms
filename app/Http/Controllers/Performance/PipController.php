<?php

namespace App\Http\Controllers\Performance;

use App\Domain\Employee\Services\EmployeeScope;
use App\Domain\Workflow\Services\ApproverResolver;
use App\Domain\Workflow\Services\Notifier;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Performance Improvement Plan (blueprint 8.1): dibuat HR untuk pegawai dengan rating rendah
 * (atau atas usulan atasan), dengan atasan sebagai penanggung jawab; ditutup dengan hasil.
 */
class PipController extends Controller
{
    use AuthorizesHcmsModule;

    public const STATUS = ['active' => 'Berjalan', 'completed' => 'Berhasil', 'extended' => 'Diperpanjang', 'unsuccessful' => 'Tidak berhasil', 'cancelled' => 'Dibatalkan'];

    public const LOW_BANDS = ['below', 'unsatisfactory'];

    public function __construct(private ApproverResolver $resolver, private Notifier $notifier)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'perfperiods';
    }

    public function index()
    {
        $userId = (int) auth()->id();
        $isHr = $this->can('is_view');
        $pips = TenantScope::table('performance_improvement_plans')->join('employees', 'employees.id', '=', 'performance_improvement_plans.employee_id')
            ->join('tb_users', 'tb_users.id', '=', 'performance_improvement_plans.owner_user_id')
            ->where(fn ($q) => $q->where('performance_improvement_plans.owner_user_id', $userId)
                ->when($isHr, fn ($w) => $w->orWhereIn('performance_improvement_plans.employee_id', EmployeeScope::query()->select('employees.id'))))
            ->orderByRaw("performance_improvement_plans.status = 'active' DESC")->orderByDesc('performance_improvement_plans.id')
            ->get(['performance_improvement_plans.*', 'employees.full_name', 'tb_users.first_name', 'tb_users.last_name']);

        // Kandidat: rating terkini dengan band rendah tanpa PIP aktif
        $candidates = $this->can('is_add') ? TenantScope::table('performance_ratings')->join('employees', 'employees.id', '=', 'performance_ratings.employee_id')
            ->join('performance_periods', 'performance_periods.id', '=', 'performance_ratings.period_id')
            ->where('performance_ratings.is_current', true)->whereIn('performance_ratings.band', self::LOW_BANDS)
            ->whereIn('performance_ratings.employee_id', EmployeeScope::query()->select('employees.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('performance_improvement_plans as p')
                ->whereColumn('p.employee_id', 'performance_ratings.employee_id')->whereIn('p.status', ['active', 'extended']))
            ->get(['performance_ratings.id', 'performance_ratings.band_label', 'performance_ratings.final_score', 'employees.full_name', 'performance_periods.name as period_name']) : collect();

        return view('performance.pip.index', [
            'pageTitle' => 'Rencana Perbaikan Kinerja (PIP)', 'pageNote' => 'untuk rating Below / Unsatisfactory',
            'pips' => $pips, 'candidates' => $candidates, 'statuses' => self::STATUS, 'canAdd' => $this->can('is_add'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTask('is_add');
        $data = $request->validate([
            'rating_id' => ['required', 'integer', TenantScope::existsRule('performance_ratings', 'id')->where('is_current', true)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'objectives' => ['required', 'string', 'min:20', 'max:3000'],
            'support' => ['nullable', 'string', 'max:3000'],
        ], [], ['rating_id' => 'rating', 'objectives' => 'area perbaikan', 'end_date' => 'tanggal selesai']);
        $rating = TenantScope::table('performance_ratings')->where('id', $data['rating_id'])->first();
        abort_unless(EmployeeScope::canSee((int) $rating->employee_id), 404);
        if (TenantScope::table('performance_improvement_plans')->where('employee_id', $rating->employee_id)->whereIn('status', ['active', 'extended'])->exists()) {
            return back()->withInput()->withErrors(['rating_id' => 'Pegawai ini masih memiliki PIP yang berjalan.']);
        }
        $employee = TenantScope::table('employees')->where('id', $rating->employee_id)->first();
        $position = $this->resolver->positionOf((int) $employee->user_id);
        $owner = $position ? (collect($this->resolver->escalationFrom($position, (int) $employee->user_id))->pluck('user_id')->first()) : null;
        if (! $owner) {
            return back()->withInput()->withErrors(['rating_id' => 'Atasan penanggung jawab tidak ditemukan.']);
        }

        $id = DB::table('performance_improvement_plans')->insertGetId(TenantScope::fillForInsert('performance_improvement_plans', [
            'uuid' => (string) Str::uuid7(), 'employee_id' => $employee->id, 'rating_id' => $rating->id,
            'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'objectives' => $data['objectives'], 'support' => $data['support'] ?? null,
            'owner_user_id' => $owner, 'status' => 'active', 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
        ]));
        AuditLog::created('performance_improvement_plans', $id, ['pegawai' => $employee->employee_number, 'mulai' => $data['start_date'], 'selesai' => $data['end_date']], 'PIP dibuat');
        foreach (array_filter([(int) $employee->user_id, (int) $owner]) as $notify) {
            $this->notifier->send($notify, 'Rencana perbaikan kinerja', 'PIP '.$employee->full_name.' dimulai '.$data['start_date'].'.', 'performance/pips', 'fa fa-life-ring');
        }

        return redirect('performance/pips')->with('message', 'PIP dibuat.')->with('status', 'success');
    }

    public function close(Request $request, int $pip)
    {
        $row = TenantScope::table('performance_improvement_plans')->where('id', $pip)->first() ?? abort(404);
        abort_unless((int) $row->owner_user_id === (int) auth()->id() || ($this->can('is_edit') && EmployeeScope::canSee((int) $row->employee_id)), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['completed', 'extended', 'unsuccessful', 'cancelled'])],
            'outcome_note' => ['required', 'string', 'min:10', 'max:3000'],
            'end_date' => ['nullable', 'required_if:status,extended', 'date', 'after:'.$row->end_date],
        ], [], ['outcome_note' => 'catatan hasil', 'end_date' => 'tanggal selesai baru']);
        if (! in_array($row->status, ['active', 'extended'], true)) {
            return back()->withErrors(['status' => 'PIP sudah ditutup.']);
        }

        $update = ['status' => $data['status'], 'outcome_note' => $data['outcome_note'], 'updated_at' => now()];
        $update += $data['status'] === 'extended' ? ['end_date' => $data['end_date']] : ['closed_at' => now()];
        DB::table('performance_improvement_plans')->where('id', $row->id)->update($update);
        AuditLog::updated('performance_improvement_plans', $row->id, ['status' => $row->status], ['status' => $data['status']], 'PIP diperbarui');

        return redirect('performance/pips')->with('message', 'PIP diperbarui.')->with('status', 'success');
    }
}
