<?php

namespace App\Http\Controllers\Competency;

use App\Domain\Competency\Services\CompetencyAssessment;
use App\Domain\Competency\Services\CompetencyFramework;
use App\Domain\Competency\Services\JobFitCalculator;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;

/**
 * Layanan mandiri kompetensi:
 * - daftar penilaian yang harus/sudah diisi pengguna sebagai penilai;
 * - form penilaian (skor 1–5 + bukti perilaku) dengan deskripsi level dari kamus;
 * - profil kompetensi & JFI pegawai sendiri (hanya hasil gabungan, bukan skor per penilai).
 * Akses dicek per baris (penilai = pengguna yang login), tanpa izin modul.
 */
class MyAssessmentsController extends Controller
{
    public function __construct(private CompetencyAssessment $assessments)
    {
        parent::__construct();
    }

    public function index()
    {
        $userId = (int) auth()->id();
        $tasks = TenantScope::table('assessment_raters')
            ->join('competency_assessments', 'competency_assessments.id', '=', 'assessment_raters.assessment_id')
            ->join('employees', 'employees.id', '=', 'competency_assessments.employee_id')
            ->where('assessment_raters.rater_user_id', $userId)->where('assessment_raters.rater_type', '!=', 'assessment_center')
            ->where(fn ($q) => $q->where('competency_assessments.status', 'open')->orWhere('assessment_raters.status', 'submitted'))
            ->orderByRaw("assessment_raters.status = 'submitted'")->orderBy('competency_assessments.due_date')
            ->limit(100)
            ->get(['assessment_raters.id', 'assessment_raters.rater_type', 'assessment_raters.status', 'assessment_raters.submitted_at',
                'competency_assessments.title', 'competency_assessments.due_date', 'competency_assessments.status as assessment_status',
                'employees.full_name', 'employees.employee_number']);

        $employee = TenantScope::table('employees')->where('user_id', $userId)->whereNull('deleted_at')->first();
        $profile = $fit = null;
        if ($employee && ($positionId = $this->assessments->currentPosition($employee->id))) {
            $fit = $this->assessments->fitFor($employee->id, $positionId);
            $profile = TenantScope::table('positions')->where('id', $positionId)->first();
        }

        return view('competency.my.index', [
            'pageTitle' => 'Asesmen Saya',
            'pageNote' => 'penilaian yang perlu Anda isi & profil kompetensi Anda',
            'tasks' => $tasks,
            'employee' => $employee,
            'position' => $profile,
            'fit' => $fit,
            'scores' => $employee ? TenantScope::table('employee_competencies')->where('employee_id', $employee->id)->get()->keyBy('competency_code') : collect(),
            'raterTypes' => CompetencyAssessment::RATER_TYPES,
            'bands' => JobFitCalculator::BAND_LABELS,
        ]);
    }

    public function edit(int $rater)
    {
        [$row, $assessment] = $this->rater($rater);

        $competencies = $this->assessments->competencies($assessment);
        $levels = TenantScope::table('competency_levels')->whereIn('competency_id', $competencies->pluck('competency_id'))
            ->orderBy('level')->get();
        $indicators = TenantScope::table('competency_indicators')->whereIn('competency_level_id', $levels->pluck('id'))
            ->orderBy('sort_order')->get()->groupBy('competency_level_id');
        $levels = $levels->groupBy('competency_id');
        $existing = TenantScope::table('competency_assessment_details')->where('rater_id', $row->id)->get()->keyBy('competency_id');

        return view('competency.my.form', [
            'pageTitle' => 'Penilaian: '.TenantScope::table('employees')->where('id', $assessment->employee_id)->value('full_name'),
            'pageNote' => $assessment->title.' · sebagai '.mb_strtolower(CompetencyAssessment::RATER_TYPES[$row->rater_type]),
            'rater' => $row, 'assessment' => $assessment,
            'competencies' => $competencies, 'levels' => $levels, 'indicators' => $indicators, 'existing' => $existing,
            'levelNames' => CompetencyFramework::LEVELS,
            'readOnly' => $assessment->status !== 'open',
        ]);
    }

    public function update(Request $request, int $rater)
    {
        [$row] = $this->rater($rater);
        $data = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*.score' => ['nullable', 'integer', 'between:1,5'],
            'scores.*.evidence' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->assessments->submit($row->id, (int) auth()->id(), $data['scores']);

        return redirect('competency/my-assessments')->with('message', 'Penilaian terkirim. Terima kasih.')->with('status', 'success');
    }

    /** @return array{0: object, 1: object} */
    private function rater(int $id): array
    {
        $row = TenantScope::table('assessment_raters')->where('id', $id)->where('rater_user_id', (int) auth()->id())
            ->where('rater_type', '!=', 'assessment_center')->first() ?? abort(404);

        return [$row, $this->assessments->assessment((int) $row->assessment_id)];
    }
}
