<?php

namespace App\Http\Controllers\Development;

use App\Domain\Development\Idps;
use App\Domain\Development\TrainingDelivery;
use App\Domain\Employee\Services\EmployeeScope;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Jadwal pelatihan: kelas dari katalog, pendaftaran peserta (dari IDP/TNA), kehadiran, kelulusan, penutupan. */
class SessionsController extends Controller
{
    use AuthorizesHcmsModule;

    public function __construct(private TrainingDelivery $delivery)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'trainingsessions';
    }

    public function index(Request $request)
    {
        $access = $this->authorizeTask('is_view');
        $year = (int) $request->input('year', now()->year);
        $year = $year >= 2020 && $year <= 2100 ? $year : now()->year;

        $sessions = TenantScope::table('training_sessions')->join('development_interventions as i', 'i.id', '=', 'training_sessions.intervention_id')
            ->whereYear('training_sessions.start_date', $year)
            ->when(array_key_exists((string) $request->query('status'), TrainingDelivery::SESSION_STATUS), fn ($q) => $q->where('training_sessions.status', $request->query('status')))
            ->selectRaw("training_sessions.*, i.name as intervention_name, i.type,
                (SELECT COUNT(*) FROM training_participants p WHERE p.session_id = training_sessions.id AND p.status != 'cancelled') as enrolled,
                (SELECT COUNT(*) FROM training_participants p WHERE p.session_id = training_sessions.id AND p.status = 'completed') as passed")
            ->orderBy('training_sessions.start_date')->paginate(50)->withQueryString();

        return view('development.sessions.index', [
            'pageTitle' => 'Jadwal Pelatihan', 'pageNote' => (string) $year, 'year' => $year, 'sessions' => $sessions,
            'statuses' => TrainingDelivery::SESSION_STATUS, 'types' => Idps::TYPES, 'canAdd' => ! empty($access['is_add']),
        ]);
    }

    public function create()
    {
        $this->authorizeTask('is_add');

        return view('development.sessions.form', [
            'pageTitle' => 'Kelas Pelatihan Baru', 'pageNote' => '',
            'interventions' => TenantScope::table('development_interventions')->whereNull('deleted_at')->where('is_active', true)->orderBy('name')->get(),
            'modes' => TrainingDelivery::MODES,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTask('is_add');
        $data = $request->validate([
            'intervention_id' => ['required', 'integer', TenantScope::existsRule('development_interventions', 'id')],
            'code' => ['required', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', TenantScope::uniqueRule('training_sessions', 'code')],
            'title' => ['nullable', 'string', 'max:200'],
            'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'mode' => ['required', Rule::in(array_keys(TrainingDelivery::MODES))],
            'location' => ['nullable', 'string', 'max:200'], 'trainer' => ['nullable', 'string', 'max:150'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'], 'has_test' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $intervention = TenantScope::table('development_interventions')->where('id', $data['intervention_id'])->first();
        $id = DB::table('training_sessions')->insertGetId(TenantScope::fillForInsert('training_sessions', [
            'code' => strtoupper($data['code']), 'title' => trim((string) ($data['title'] ?? '')) ?: $intervention->name,
            'capacity' => $data['capacity'] ?? $intervention->capacity, 'has_test' => $request->boolean('has_test'),
            'status' => 'planned', 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
        ] + collect($data)->only(['intervention_id', 'start_date', 'end_date', 'mode', 'location', 'trainer', 'notes'])->all()));
        AuditLog::created('training_sessions', $id, $data, 'Kelas pelatihan dibuat');

        return redirect('development/sessions/'.$id)->with('message', 'Kelas dibuat. Daftarkan peserta dari IDP/TNA atau pilih pegawai.')->with('status', 'success');
    }

    public function show(int $id)
    {
        $access = $this->authorizeTask('is_view');
        $session = $this->delivery->session($id) ?? abort(404);
        $open = ! in_array($session->status, ['completed', 'cancelled'], true);

        return view('development.sessions.show', [
            'pageTitle' => $session->title, 'pageNote' => $session->code,
            'session' => $session, 'participants' => $this->delivery->participants($id),
            'candidates' => $open && ! empty($access['is_edit']) ? $this->delivery->candidates($session) : collect(),
            'employees' => $open && ! empty($access['is_edit']) ? EmployeeScope::query()->where('employees.status', 'active')->orderBy('employees.full_name')
                ->get(['employees.id', 'employees.full_name', 'employees.employee_number']) : collect(),
            'sessionStatuses' => TrainingDelivery::SESSION_STATUS, 'participantStatuses' => TrainingDelivery::PARTICIPANT_STATUS,
            'modes' => TrainingDelivery::MODES, 'types' => Idps::TYPES, 'canEdit' => ! empty($access['is_edit']) && $open,
        ]);
    }

    public function enroll(Request $request, int $id)
    {
        $this->authorizeTask('is_edit');
        $data = $request->validate([
            'candidates' => ['nullable', 'array'], 'candidates.*' => ['string', 'regex:/^\d+:\d*:\d*$/'],
            'employee_ids' => ['nullable', 'array'], 'employee_ids.*' => ['integer'],
        ]);
        $entries = collect($data['candidates'] ?? [])->map(function ($v) {
            [$employee, $activity, $need] = array_pad(explode(':', $v), 3, '');

            return ['employee_id' => (int) $employee, 'idp_activity_id' => $activity !== '' ? (int) $activity : null, 'training_need_id' => $need !== '' ? (int) $need : null];
        })->concat(collect($data['employee_ids'] ?? [])->map(fn ($e) => ['employee_id' => (int) $e]))->all();
        if ($entries === []) {
            return back()->withErrors(['employees' => 'Pilih minimal satu peserta.']);
        }
        $added = $this->delivery->enroll($id, $entries, (int) auth()->id());

        return redirect('development/sessions/'.$id)->with('message', $added.' peserta didaftarkan.')->with('status', 'success');
    }

    public function participant(Request $request, int $participant)
    {
        $this->authorizeTask('is_edit');
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(TrainingDelivery::PARTICIPANT_STATUS))],
            'attendance_pct' => ['nullable', 'integer', 'between:0,100'],
            'pre_score' => ['nullable', 'numeric', 'between:0,100'], 'post_score' => ['nullable', 'numeric', 'between:0,100'],
            'certificate_no' => ['nullable', 'string', 'max:100'],
        ]);
        $this->delivery->updateParticipant($participant, $data);
        $session = (int) TenantScope::table('training_participants')->where('id', $participant)->value('session_id');

        return redirect('development/sessions/'.$session)->with('message', 'Data peserta tersimpan.')->with('status', 'success');
    }

    public function complete(Request $request, int $id)
    {
        $this->authorizeTask('is_edit');
        $data = $request->validate(['cost_total' => ['nullable', 'numeric', 'min:0', 'max:999999999999']]);
        $this->delivery->complete($id, isset($data['cost_total']) ? (float) $data['cost_total'] : null);

        return redirect('development/sessions/'.$id)->with('message', 'Kelas ditutup.')->with('status', 'success');
    }
}
