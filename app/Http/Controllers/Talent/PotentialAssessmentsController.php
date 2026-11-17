<?php

namespace App\Http\Controllers\Talent;

use App\Domain\Employee\Services\EmployeeScope;
use App\Domain\Talent\PotentialInstruments;
use App\Domain\Talent\TalentReviews;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Talenta › Asesmen Potensi: hasil instrumen potensi per pegawai (data sensitif, setiap akses tercatat). */
class PotentialAssessmentsController extends Controller
{
    use AuthorizesHcmsModule;

    public function __construct(private TalentReviews $talent, private PotentialInstruments $instruments)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'potentialassessments';
    }

    public function index(Request $request)
    {
        $access = $this->authorizeTask('is_view');
        $today = now()->toDateString();
        $rows = $this->talent->visiblePotential()->join('potential_instruments as pi', 'pi.id', '=', 'potential_assessments.instrument_id')
            ->leftJoin('tb_users as u', 'u.id', '=', 'potential_assessments.assessor_user_id')
            ->when($request->query('level'), fn ($q, $v) => $q->where('potential_assessments.level', $v))
            ->when($request->query('state') === 'expired', fn ($q) => $q->where('potential_assessments.valid_until', '<', $today))
            ->when($request->query('state') !== 'all', fn ($q) => $q->where('potential_assessments.status', 'final'))
            ->when(trim((string) $request->query('q')), fn ($q, $v) => $q->where('employees.full_name', 'like', '%'.$v.'%'))
            ->orderByDesc('potential_assessments.assessed_at')->orderByDesc('potential_assessments.id')
            ->select(['potential_assessments.*', 'employees.full_name', 'employees.employee_number', 'pi.version as instrument_version',
                'u.first_name as assessor_first', 'u.last_name as assessor_last'])
            ->paginate(50)->withQueryString();
        AuditLog::sensitiveViewed('potential_assessments', 0, 'Melihat daftar hasil asesmen potensi');

        return view('talent.potential-assessments.index', [
            'pageTitle' => 'Asesmen Potensi', 'pageNote' => 'data sensitif', 'rows' => $rows, 'today' => $today,
            'levels' => TalentReviews::LEVELS, 'canAdd' => ! empty($access['is_add']), 'canRemove' => ! empty($access['is_remove']),
            'instrument' => $this->instruments->published(),
        ]);
    }

    public function create()
    {
        $this->authorizeTask('is_add');
        $instrument = $this->instruments->published();

        return view('talent.potential-assessments.form', [
            'pageTitle' => 'Asesmen Potensi Baru', 'pageNote' => $instrument ? $instrument->name : '',
            'instrument' => $instrument, 'criteria' => $instrument ? $this->instruments->criteria((int) $instrument->id) : collect(),
            'employees' => EmployeeScope::query()->where('employees.status', 'active')->orderBy('employees.full_name')
                ->get(['employees.id', 'employees.full_name', 'employees.employee_number']),
            'levels' => TalentReviews::LEVELS,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTask('is_add');
        $instrument = $this->instruments->published() ?? abort(422);
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::in(EmployeeScope::query()->where('employees.status', 'active')->pluck('employees.id')->all())],
            'scores' => ['required', 'array'], 'scores.*' => ['required', 'numeric', 'between:1,'.$instrument->scale_max],
            'confidence' => ['required', Rule::in(array_keys(TalentReviews::LEVELS))],
            'evidence' => ['required', 'string', 'min:20', 'max:4000'],
        ], ['evidence.min' => 'Bukti perilaku minimal 20 karakter.', 'employee_id.in' => 'Pegawai di luar cakupan data Anda.']);
        $this->talent->assessPotential((int) $data['employee_id'], array_map('floatval', $data['scores']), $data['confidence'], $data['evidence'], (int) auth()->id());
        $this->logActivity($request, 'Asesmen potensi dicatat untuk pegawai #'.$data['employee_id']);

        return redirect('talent/potential-assessments')->with('message', 'Asesmen potensi tersimpan.')->with('status', 'success');
    }

    public function cancel(Request $request, int $id)
    {
        $this->authorizeTask('is_remove');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->talent->cancelPotential($id, $data['reason']);

        return back()->with('message', 'Asesmen potensi dibatalkan.')->with('status', 'success');
    }
}
