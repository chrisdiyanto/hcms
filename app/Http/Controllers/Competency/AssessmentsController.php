<?php

namespace App\Http\Controllers\Competency;

use App\Domain\Competency\Services\CompetencyAssessment;
use App\Domain\Competency\Services\CompetencyFramework;
use App\Domain\Competency\Services\CompetencyHistory;
use App\Domain\Competency\Services\JobFitCalculator;
use App\Domain\Competency\Services\RaterAggregator;
use App\Domain\Employee\Services\EmployeeScope;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;

/**
 * Pengelolaan asesmen kompetensi oleh HR / asesor: buka asesmen, pantau penilai,
 * input hasil assessment center, tutup (hitung skor, gap, JFI, draf IDP) atau batalkan.
 * Daftar dibatasi data scope pegawai pengguna.
 */
class AssessmentsController extends Controller
{
    use AuthorizesHcmsModule;

    public function __construct(private CompetencyAssessment $assessments, private CompetencyFramework $framework, private CompetencyHistory $history)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'assessments';
    }

    public function index(Request $request)
    {
        $access = $this->authorizeTask('is_view');
        $status = $request->query('status', 'open');

        $rows = TenantScope::table('competency_assessments')
            ->join('employees', 'employees.id', '=', 'competency_assessments.employee_id')
            ->join('positions', 'positions.id', '=', 'competency_assessments.position_id')
            // JFI hasil asesmen itu sendiri (riwayat), bukan hasil terkini yang tertimpa asesmen berikutnya
            ->leftJoin('job_fit_history', function ($j) {
                $j->on('job_fit_history.assessment_id', '=', 'competency_assessments.id')
                    ->whereIn('job_fit_history.trigger', ['assessment', 'backfill']);
            })
            ->whereIn('competency_assessments.employee_id', EmployeeScope::query()->select('employees.id'))
            ->when(array_key_exists($status, CompetencyAssessment::STATUS), fn ($q) => $q->where('competency_assessments.status', $status))
            ->orderByDesc('competency_assessments.id')
            ->select(['competency_assessments.*', 'employees.full_name', 'employees.employee_number', 'positions.title as position_title',
                'job_fit_history.jfi', 'job_fit_history.band'])
            ->paginate(25)->withQueryString();

        $counts = TenantScope::table('assessment_raters')->whereIn('assessment_id', $rows->pluck('id'))->where('rater_type', '!=', 'assessment_center')
            ->selectRaw("assessment_id, COUNT(*) total, SUM(status = 'submitted') done")->groupBy('assessment_id')->get()->keyBy('assessment_id');

        return view('competency.assessments.index', [
            'pageTitle' => 'Asesmen Kompetensi',
            'pageNote' => 'penilaian multi-rater & assessment center',
            'rows' => $rows, 'counts' => $counts, 'status' => $status, 'access' => $access,
            'statuses' => CompetencyAssessment::STATUS, 'bands' => JobFitCalculator::BAND_LABELS,
        ]);
    }

    public function create()
    {
        $this->authorizeTask('is_add');

        return view('competency.assessments.form', [
            'pageTitle' => 'Buka Asesmen Kompetensi',
            'pageNote' => ($v = $this->framework->published()) ? 'mengacu '.$v->name : 'belum ada kamus yang berlaku',
            'hasFramework' => (bool) $v,
            'employees' => EmployeeScope::query()->where('employees.status', 'active')->orderBy('employees.full_name')
                ->get(['employees.id', 'employees.full_name', 'employees.employee_number'])
                ->mapWithKeys(fn ($e) => [$e->id => $e->full_name.' ('.$e->employee_number.')'])->all(),
            'users' => $this->userOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTask('is_add');
        $userRule = TenantScope::existsRule('tb_users', 'id')->where('active', 1);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:150'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'include_self' => ['nullable', 'boolean'],
            'include_manager' => ['nullable', 'boolean'],
            'peers' => ['nullable', 'array', 'max:20'], 'peers.*' => ['integer', 'distinct', $userRule],
            'subordinates' => ['nullable', 'array', 'max:20'], 'subordinates.*' => ['integer', 'distinct', $userRule],
            'hr' => ['nullable', 'array', 'max:5'], 'hr.*' => ['integer', 'distinct', $userRule],
        ], [], ['employee_id' => 'pegawai', 'due_date' => 'batas waktu', 'peers' => 'rekan', 'subordinates' => 'bawahan']);

        if (! EmployeeScope::canSee((int) $data['employee_id'])) {
            return back()->withInput()->withErrors(['employee_id' => 'Pegawai di luar cakupan data Anda.']);
        }

        $id = $this->assessments->open((int) $data['employee_id'], [
            'title' => $data['title'] ?? null, 'due_date' => $data['due_date'] ?? null,
            'include_self' => $request->boolean('include_self'), 'include_manager' => $request->boolean('include_manager'),
            'peers' => $data['peers'] ?? [], 'subordinates' => $data['subordinates'] ?? [], 'hr' => $data['hr'] ?? [],
        ], auth()->id());
        $this->logActivity($request, 'Membuka asesmen kompetensi #'.$id);

        return redirect('competency/assessments/'.$id)->with('message', 'Asesmen dibuka dan penilai telah diberi notifikasi.')->with('status', 'success');
    }

    public function show(int $assessment)
    {
        $access = $this->authorizeTask('is_view');
        $row = $this->visible($assessment);

        $raters = TenantScope::table('assessment_raters')->join('tb_users', 'tb_users.id', '=', 'assessment_raters.rater_user_id')
            ->where('assessment_raters.assessment_id', $row->id)
            ->orderByRaw("FIELD(assessment_raters.rater_type, 'self', 'manager', 'peer', 'subordinate', 'hr', 'assessment_center')")
            ->get(['assessment_raters.*', 'tb_users.first_name', 'tb_users.last_name']);

        $competencies = $this->assessments->competencies($row);
        // Hasil sementara/akhir per kelompok (rata-rata kelompok saja, menjaga anonimitas rekan & bawahan)
        $aggregate = $this->assessments->aggregate($row);

        $centerScores = TenantScope::table('competency_assessment_details')
            ->join('assessment_raters', 'assessment_raters.id', '=', 'competency_assessment_details.rater_id')
            ->where('assessment_raters.assessment_id', $row->id)->where('assessment_raters.rater_type', 'assessment_center')
            ->get(['competency_assessment_details.competency_id', 'competency_assessment_details.score', 'competency_assessment_details.evidence'])
            ->keyBy('competency_id');

        // Hasil asesmen ini dari riwayat: asesmen lama tetap menampilkan JFI & gap miliknya sendiri
        $fit = $row->status === 'closed' ? $this->history->fitForAssessment($row->id) : null;

        return view('competency.assessments.show', [
            'pageTitle' => $row->title,
            'pageNote' => CompetencyAssessment::STATUS[$row->status],
            'assessment' => $row,
            'employee' => TenantScope::table('employees')->where('id', $row->employee_id)->first(),
            'position' => TenantScope::table('positions')->where('id', $row->position_id)->first(),
            'raters' => $raters, 'competencies' => $competencies, 'aggregate' => $aggregate, 'centerScores' => $centerScores,
            'fit' => $fit,
            'gaps' => $fit ? $this->history->gaps($fit->id) : collect(),
            'idp' => TenantScope::table('competency_idp_suggestions')->join('competencies', 'competencies.id', '=', 'competency_idp_suggestions.competency_id')
                ->where('competency_idp_suggestions.assessment_id', $row->id)->get(['competency_idp_suggestions.*', 'competencies.name']),
            'raterTypes' => CompetencyAssessment::RATER_TYPES, 'groupLabels' => RaterAggregator::GROUP_LABELS,
            'weights' => $this->assessments->raterWeights(), 'bands' => JobFitCalculator::BAND_LABELS,
            'canEdit' => ! empty($access['is_edit']) && $row->status === 'open',
        ]);
    }

    public function assessmentCenter(Request $request, int $assessment)
    {
        $this->authorizeTask('is_edit');
        $row = $this->visible($assessment);
        $data = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*.score' => ['nullable', 'integer', 'between:1,5'],
            'scores.*.evidence' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->assessments->recordAssessmentCenter($row->id, (int) auth()->id(), $data['scores']);
        $this->logActivity($request, 'Input hasil assessment center asesmen #'.$row->id);

        return redirect('competency/assessments/'.$row->id)->with('message', 'Hasil assessment center disimpan.')->with('status', 'success');
    }

    public function close(Request $request, int $assessment)
    {
        $this->authorizeTask('is_edit');
        $row = $this->visible($assessment);
        $result = $this->assessments->close($row->id, (int) auth()->id());
        $this->logActivity($request, 'Menutup asesmen kompetensi #'.$row->id);

        return redirect('competency/assessments/'.$row->id)
            ->with('message', sprintf('Asesmen ditutup. JFI %s%% — %s%s.', number_format($result['jfi'], 1, ',', '.'),
                JobFitCalculator::BAND_LABELS[$result['band']], $result['complete'] ? '' : ' (belum lengkap)'))
            ->with('status', 'success');
    }

    public function cancel(Request $request, int $assessment)
    {
        $this->authorizeTask('is_edit');
        $row = $this->visible($assessment);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'alasan']);
        $this->assessments->cancel($row->id, $data['reason']);
        $this->logActivity($request, 'Membatalkan asesmen kompetensi #'.$row->id);

        return redirect('competency/assessments/'.$row->id)->with('message', 'Asesmen dibatalkan.')->with('status', 'success');
    }

    // ---------------------------------------------------------------- bantuan

    private function visible(int $id): object
    {
        $row = $this->assessments->assessment($id);
        if (! EmployeeScope::canSee((int) $row->employee_id)) {
            abort(404);
        }

        return $row;
    }

    /** Pengguna aktif tenant, diberi nama pegawai bila tertaut. */
    private function userOptions(): array
    {
        return TenantScope::table('tb_users')->leftJoin('employees', 'employees.user_id', '=', 'tb_users.id')
            ->where('tb_users.active', 1)->orderBy('tb_users.first_name')
            ->get(['tb_users.id', 'tb_users.first_name', 'tb_users.last_name', 'tb_users.username', 'employees.full_name'])
            ->mapWithKeys(fn ($u) => [$u->id => ($u->full_name ?: trim($u->first_name.' '.$u->last_name)).' — '.$u->username])->all();
    }
}
