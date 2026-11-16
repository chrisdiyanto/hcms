<?php

namespace App\Http\Controllers\Performance;

use App\Domain\Performance\Services\PerformanceCalibration;
use App\Domain\Performance\Services\PerformancePeriods;
use App\Domain\Performance\Services\PerformancePolicy;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sesi kalibrasi: papan distribusi per band (drag & drop jQuery UI sortable, ringkasan Alpine.js).
 * Setiap pemindahan dikirim ke server dengan alasan; server menghitung ulang distribusi.
 */
class CalibrationController extends Controller
{
    use AuthorizesHcmsModule;

    public function __construct(private PerformanceCalibration $calibration, private PerformancePeriods $periods)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'perfperiods';
    }

    public function index(int $period)
    {
        $access = $this->authorizeTask('is_view');
        $row = $this->periods->find($period);

        return view('performance.calibration.index', [
            'pageTitle' => 'Kalibrasi: '.$row->name,
            'pageNote' => PerformancePeriods::STATUS[$row->status],
            'period' => $row,
            'sessions' => TenantScope::table('calibration_sessions')->leftJoin('unit', 'unit.id_unit', '=', 'calibration_sessions.unit_id')
                ->where('calibration_sessions.period_id', $row->id)->orderByDesc('calibration_sessions.id')
                ->get(['calibration_sessions.*', 'unit.unit_name']),
            'units' => TenantScope::table('unit')->orderBy('unit_name')->pluck('unit_name', 'id_unit')->all(),
            'canEdit' => ! empty($access['is_edit']) && $row->status === 'calibration',
        ]);
    }

    public function store(Request $request, int $period)
    {
        $this->authorizeTask('is_edit');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'unit_id' => ['nullable', 'integer', TenantScope::existsRule('unit', 'id_unit')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['name' => 'nama sesi']);
        $id = $this->calibration->create($period, $data['name'], $data['unit_id'] ?? null, $data['notes'] ?? null, (int) auth()->id());

        return redirect('performance/calibration/sessions/'.$id);
    }

    public function show(int $session)
    {
        $access = $this->authorizeTask('is_view');
        $row = $this->calibration->session($session);
        $period = $this->periods->find((int) $row->period_id);
        $members = $this->calibration->members($row);
        $bands = collect($period->policy['bands'])->sortByDesc('min')->values();

        return view('performance.calibration.show', [
            'pageTitle' => $row->name,
            'pageNote' => $period->name.($row->status === 'closed' ? ' · ditutup' : ''),
            'session' => $row, 'period' => $period, 'bands' => $bands,
            'members' => $members->groupBy('current_band'),
            'distribution' => $this->calibration->distribution($row, $members),
            'adjustments' => TenantScope::table('calibration_adjustments')
                ->join('performance_assessments', 'performance_assessments.id', '=', 'calibration_adjustments.assessment_id')
                ->join('performance_plans', 'performance_plans.id', '=', 'performance_assessments.plan_id')
                ->join('employees', 'employees.id', '=', 'performance_plans.employee_id')
                ->join('tb_users', 'tb_users.id', '=', 'calibration_adjustments.adjusted_by')
                ->where('calibration_adjustments.session_id', $row->id)->orderByDesc('calibration_adjustments.id')
                ->get(['calibration_adjustments.*', 'employees.full_name', 'tb_users.first_name', 'tb_users.last_name']),
            'bandLabels' => PerformancePolicy::bandLabels($period->policy),
            'canEdit' => ! empty($access['is_edit']) && $row->status === 'open' && $period->status === 'calibration',
        ]);
    }

    /** Endpoint JSON untuk papan drag & drop. */
    public function move(Request $request, int $session)
    {
        $this->authorizeTask('is_edit');
        $data = $request->validate([
            'assessment_id' => ['required', 'integer'],
            'to_band' => ['required', 'string', 'max:30'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [], ['reason' => 'alasan']);
        $distribution = $this->calibration->move($session, (int) $data['assessment_id'], $data['to_band'], $data['reason'], (int) auth()->id());
        $member = DB::table('performance_assessments')->where('id', $data['assessment_id'])->first(['calibrated_score', 'calibrated_band']);

        return response()->json(['distribution' => $distribution, 'score' => (float) $member->calibrated_score, 'band' => $member->calibrated_band]);
    }

    public function close(Request $request, int $session)
    {
        $this->authorizeTask('is_approve');
        $this->calibration->close($session);
        $this->logActivity($request, 'Sesi kalibrasi #'.$session.' ditutup');

        return redirect('performance/calibration/sessions/'.$session)->with('message', 'Sesi kalibrasi ditutup.')->with('status', 'success');
    }
}
