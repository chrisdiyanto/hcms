<?php

namespace App\Http\Controllers\Competency;

use App\Domain\Competency\Services\CompetencyAssessment;
use App\Domain\Competency\Services\JobFitCalculator;
use App\Domain\Employee\Services\EmployeeScope;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Laporan kompetensi (blueprint 7.5): sebaran kesiapan (band JFI), gap terbesar,
 * heatmap gap unit × kompetensi, dan profil individu (radar wajib vs aktual).
 * Hanya hasil terhadap posisi primer pegawai saat ini, dibatasi data scope pengguna.
 */
class ReportsController extends Controller
{
    use AuthorizesHcmsModule;

    private const HEATMAP_COLUMNS = 15;

    public function __construct(private CompetencyAssessment $assessments)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'competencyreports';
    }

    public function index(Request $request)
    {
        $this->authorizeTask('is_view');
        $unitId = $request->integer('unit') ?: null;
        $band = array_key_exists((string) $request->query('band'), JobFitCalculator::BAND_LABELS) ? $request->query('band') : null;

        $fits = $this->current('job_fit_results', $unitId)
            ->join('employees', 'employees.id', '=', 'job_fit_results.employee_id')
            ->leftJoin('unit', 'unit.id_unit', '=', 'positions.unit_id')
            ->orderBy('job_fit_results.jfi', 'desc')
            ->get(['job_fit_results.*', 'employees.full_name', 'employees.employee_number', 'positions.title as position_title', 'unit.unit_name']);

        $bandCounts = collect(JobFitCalculator::BAND_LABELS)->map(fn ($l, $k) => $fits->where('band', $k)->count());

        $gaps = $this->current('competency_gaps', $unitId)
            ->join('competencies', 'competencies.id', '=', 'competency_gaps.competency_id')
            ->leftJoin('unit', 'unit.id_unit', '=', 'positions.unit_id')
            ->get(['competency_gaps.competency_code', 'competency_gaps.gap', 'competency_gaps.criticality', 'competencies.name', 'unit.unit_name']);

        $topGaps = $gaps->groupBy('competency_code')->map(fn ($g, $code) => (object) [
            'code' => $code, 'name' => $g->first()->name, 'employees' => $g->count(),
            'below' => $g->where('gap', '<', 0)->count(), 'avg_gap' => round((float) $g->avg('gap'), 2),
            'critical' => $g->where('criticality', 3)->where('gap', '<', 0)->count(),
        ])->sortBy([['below', 'desc'], ['avg_gap', 'asc']])->values();

        return view('competency.reports.index', [
            'pageTitle' => 'Laporan Kompetensi',
            'pageNote' => 'kesiapan, gap & heatmap',
            'units' => $this->unitOptions(),
            'unitId' => $unitId, 'band' => $band,
            'fits' => $band ? $fits->where('band', $band)->values() : $fits,
            'bandCounts' => $bandCounts, 'bands' => JobFitCalculator::BAND_LABELS,
            'topGaps' => $topGaps->take(10),
            'barChart' => [
                'type' => 'bar', 'labels' => array_values(JobFitCalculator::BAND_LABELS),
                'values' => $bandCounts->values()->all(), 'colors' => ['#12b76a', '#f79009', '#d92d20'],
            ],
            'heatmap' => $this->heatmap($gaps, $topGaps),
        ]);
    }

    public function employee(int $employee)
    {
        $this->authorizeTask('is_view');
        if (! EmployeeScope::canSee($employee)) {
            abort(404);
        }
        $row = TenantScope::table('employees')->where('id', $employee)->first() ?? abort(404);
        $positionId = $this->assessments->currentPosition($employee);
        $fit = $positionId ? $this->assessments->fitFor($employee, $positionId) : null;
        AuditLog::sensitiveViewed('employee_competencies', $employee, 'Melihat profil kompetensi '.$row->full_name);

        return view('competency.reports.employee', [
            'pageTitle' => 'Profil Kompetensi: '.$row->full_name,
            'pageNote' => $row->employee_number,
            'employee' => $row,
            'position' => $positionId ? TenantScope::table('positions')->where('id', $positionId)->first() : null,
            'fit' => $fit,
            'bands' => JobFitCalculator::BAND_LABELS,
            'radar' => $fit && count($fit['items']) >= 3 ? [
                'type' => 'radar',
                'indicators' => array_column($fit['items'], 'code'),
                'required' => array_column($fit['items'], 'required'),
                'actual' => array_map(fn ($i) => $i['actual'] ?? 0, $fit['items']),
            ] : null,
            'scores' => TenantScope::table('employee_competencies')->join('competencies', 'competencies.id', '=', 'employee_competencies.competency_id')
                ->where('employee_competencies.employee_id', $employee)->orderBy('employee_competencies.competency_code')
                ->get(['employee_competencies.*', 'competencies.name']),
            'history' => TenantScope::table('competency_assessments')->join('positions', 'positions.id', '=', 'competency_assessments.position_id')
                ->where('competency_assessments.employee_id', $employee)->orderByDesc('competency_assessments.id')
                ->get(['competency_assessments.*', 'positions.title as position_title']),
            'idp' => TenantScope::table('competency_idp_suggestions')->join('competencies', 'competencies.id', '=', 'competency_idp_suggestions.competency_id')
                ->where('competency_idp_suggestions.employee_id', $employee)->where('competency_idp_suggestions.status', 'draft')
                ->get(['competency_idp_suggestions.*', 'competencies.name']),
            'statuses' => CompetencyAssessment::STATUS,
        ]);
    }

    // ---------------------------------------------------------------- bantuan

    /**
     * Baris hasil ($table) yang posisinya = posisi primer aktif pegawai saat ini,
     * untuk pegawai dalam data scope; sudah di-join ke positions.
     */
    private function current(string $table, ?int $unitId): Builder
    {
        $today = now()->toDateString();

        return TenantScope::table($table)
            ->join('positions', 'positions.id', '=', $table.'.position_id')
            ->whereIn($table.'.employee_id', EmployeeScope::query()->where('employees.status', 'active')->select('employees.id'))
            ->whereExists(function ($q) use ($table, $today) {
                $q->selectRaw('1')->from('employee_assignments')
                    ->whereColumn('employee_assignments.employee_id', $table.'.employee_id')
                    ->whereColumn('employee_assignments.position_id', $table.'.position_id')
                    ->where('employee_assignments.type', 'primary')
                    ->where('employee_assignments.effective_from', '<=', $today)
                    ->where(fn ($w) => $w->whereNull('employee_assignments.effective_to')->orWhere('employee_assignments.effective_to', '>=', $today));
            })
            ->when($unitId, fn ($q) => $q->where('positions.unit_id', $unitId));
    }

    private function heatmap($gaps, $topGaps): ?array
    {
        $cols = $topGaps->take(self::HEATMAP_COLUMNS)->pluck('code')->values();
        $byUnit = $gaps->whereIn('competency_code', $cols)->groupBy(fn ($g) => $g->unit_name ?: 'Tanpa unit')->sortKeys();
        if ($cols->isEmpty() || $byUnit->isEmpty()) {
            return null;
        }

        $rows = $byUnit->keys()->values();
        $data = [];
        foreach ($rows as $y => $unit) {
            foreach ($cols as $x => $code) {
                $cell = $byUnit[$unit]->where('competency_code', $code);
                if ($cell->isNotEmpty()) {
                    $data[] = [$x, $y, round((float) $cell->avg('gap'), 2), $cell->count()];
                }
            }
        }

        return ['type' => 'heatmap', 'cols' => $cols->all(), 'rows' => $rows->all(), 'data' => $data, 'height' => max(220, 46 * $rows->count() + 110)];
    }

    private function unitOptions(): array
    {
        return TenantScope::table('unit')
            ->whereIn('id_unit', $this->current('job_fit_results', null)->select('positions.unit_id'))
            ->orderBy('unit_name')->pluck('unit_name', 'id_unit')->all();
    }
}
