<?php

namespace App\Http\Controllers\Competency;

use App\Domain\Competency\Services\CompetencyAssessment;
use App\Domain\Competency\Services\CompetencyHistory;
use App\Domain\Competency\Services\JobFitCalculator;
use App\Domain\Employee\Services\EmployeeScope;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLog;
use App\Support\Security\DataScope;
use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Laporan kompetensi (blueprint 7.5): sebaran kesiapan (band JFI), gap terbesar,
 * heatmap gap unit × kompetensi, dan profil individu (radar wajib vs aktual).
 * Hanya hasil terhadap posisi primer pegawai saat ini, dibatasi data scope pengguna.
 */
class ReportsController extends Controller
{
    use AuthorizesHcmsModule;

    private const HEATMAP_COLUMNS = 15;

    private const HEATMAP_ROWS = 25;

    public function __construct(private CompetencyAssessment $assessments, private CompetencyHistory $history)
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

        // Agregat di-cache per tenant + cakupan data + filter; versi naik setiap gap/JFI dihitung ulang
        // (uji beban 10.000 pegawai: agregasi 25.000 gap ± 1 dtk tanpa cache)
        $scope = DataScope::current();
        $key = 'competency-report:'.TenantScope::tenantId().':v'.CompetencyAssessment::reportVersion().':'
            .($scope->isAll() ? 'all' : md5($scope->mode.'|'.$scope->userId.'|'.implode(',', $scope->unitIds))).':u'.($unitId ?? 0);
        $agg = Cache::remember($key, 600, fn () => $this->aggregates($unitId));

        $fits = $this->current('job_fit_results', $unitId)
            ->join('employees', 'employees.id', '=', 'job_fit_results.employee_id')
            ->leftJoin('unit', 'unit.id_unit', '=', 'positions.unit_id')
            ->when($band, fn ($q) => $q->where('job_fit_results.band', $band))
            ->orderByDesc('job_fit_results.jfi')->orderBy('job_fit_results.id')
            ->select(['job_fit_results.*', 'employees.full_name', 'employees.employee_number', 'positions.title as position_title', 'unit.unit_name'])
            ->paginate(50)->withQueryString();

        return view('competency.reports.index', [
            'pageTitle' => 'Laporan Kompetensi',
            'pageNote' => 'kesiapan, gap & heatmap',
            'units' => $agg['units'],
            'unitId' => $unitId, 'band' => $band,
            'fits' => $fits,
            'bandCounts' => $agg['bandCounts'], 'bands' => JobFitCalculator::BAND_LABELS,
            'topGaps' => $agg['topGaps']->take(10),
            'barChart' => [
                'type' => 'bar', 'labels' => array_values(JobFitCalculator::BAND_LABELS),
                'values' => $agg['bandCounts']->values()->all(), 'colors' => ['#12b76a', '#f79009', '#d92d20'],
            ],
            'heatmap' => $agg['heatmap'],
        ]);
    }

    /** @return array{bandCounts: Collection, topGaps: Collection, heatmap: ?array, units: array} */
    private function aggregates(?int $unitId): array
    {
        $counted = $this->current('job_fit_results', $unitId)->selectRaw('job_fit_results.band, COUNT(*) n')->groupBy('job_fit_results.band')->pluck('n', 'band');
        $bandCounts = collect(JobFitCalculator::BAND_LABELS)->map(fn ($l, $k) => (int) ($counted[$k] ?? 0));

        $topGaps = $this->current('competency_gaps', $unitId)
            ->join('competencies', 'competencies.id', '=', 'competency_gaps.competency_id')
            ->groupBy('competency_gaps.competency_code')
            ->selectRaw('competency_gaps.competency_code as code, MAX(competencies.name) as name, COUNT(*) as employees,
                SUM(competency_gaps.gap < 0) as below, ROUND(AVG(competency_gaps.gap), 2) as avg_gap,
                SUM(competency_gaps.criticality = 3 AND competency_gaps.gap < 0) as critical')
            ->orderByDesc('below')->orderBy('avg_gap')->limit(self::HEATMAP_COLUMNS)->get()
            ->map(function ($g) {
                foreach (['employees', 'below', 'critical'] as $k) {
                    $g->{$k} = (int) $g->{$k};
                }
                $g->avg_gap = (float) $g->avg_gap;

                return $g;
            });

        return [
            'bandCounts' => $bandCounts,
            'topGaps' => $topGaps,
            'heatmap' => $this->heatmap($unitId, $topGaps->pluck('code')->all()),
            'units' => $this->unitOptions(),
        ];
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
            // Riwayat (prasyarat P1 CBHRM): skor lintas asesmen & setiap perhitungan JFI
            'timeline' => $this->history->scoreTimeline($employee),
            'fitTimeline' => $this->history->fitTimeline($employee),
            'triggers' => CompetencyHistory::TRIGGERS,
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

        // JOIN (bukan EXISTS + IN): satu penugasan primer aktif per pegawai, jadi tidak menggandakan baris
        $query = TenantScope::table($table)
            ->join('positions', 'positions.id', '=', $table.'.position_id')
            ->join('employee_assignments as cur_a', function ($j) use ($table, $today) {
                $j->on('cur_a.employee_id', '=', $table.'.employee_id')->on('cur_a.position_id', '=', $table.'.position_id')
                    ->where('cur_a.type', 'primary')->where('cur_a.effective_from', '<=', $today)
                    ->where(fn ($w) => $w->whereNull('cur_a.effective_to')->orWhere('cur_a.effective_to', '>=', $today));
            })
            ->join('employees as cur_e', function ($j) use ($table) {
                $j->on('cur_e.id', '=', $table.'.employee_id')->whereNull('cur_e.deleted_at')->where('cur_e.status', 'active');
            })
            ->when($unitId, fn ($q) => $q->where('positions.unit_id', $unitId));

        return EmployeeScope::apply($query, $table.'.employee_id');
    }

    /** Rata-rata gap unit × kompetensi (agregasi SQL); baris dibatasi unit dengan gap rata-rata terburuk. */
    private function heatmap(?int $unitId, array $codes): ?array
    {
        if ($codes === []) {
            return null;
        }
        $cells = $this->current('competency_gaps', $unitId)
            ->leftJoin('unit', 'unit.id_unit', '=', 'positions.unit_id')
            ->whereIn('competency_gaps.competency_code', $codes)
            ->groupBy('unit.unit_name', 'competency_gaps.competency_code')
            ->selectRaw("COALESCE(unit.unit_name, 'Tanpa unit') as unit_name, competency_gaps.competency_code as code, AVG(competency_gaps.gap) as avg_gap, COUNT(*) as n")
            ->get();
        if ($cells->isEmpty()) {
            return null;
        }

        $rows = $cells->groupBy('unit_name')->map(fn ($c) => $c->avg('avg_gap'))->sort()->take(self::HEATMAP_ROWS)->keys()->sort()->values();
        $x = array_flip($codes);
        $y = $rows->flip();
        $data = $cells->filter(fn ($c) => $y->has($c->unit_name))
            ->map(fn ($c) => [$x[$c->code], $y[$c->unit_name], round((float) $c->avg_gap, 2), (int) $c->n])->values()->all();

        return ['type' => 'heatmap', 'cols' => $codes, 'rows' => $rows->all(), 'data' => $data, 'height' => max(220, 46 * $rows->count() + 110),
            'truncated' => $cells->pluck('unit_name')->unique()->count() > self::HEATMAP_ROWS];
    }

    private function unitOptions(): array
    {
        return TenantScope::table('unit')
            ->whereIn('id_unit', $this->current('job_fit_results', null)->select('positions.unit_id'))
            ->orderBy('unit_name')->pluck('unit_name', 'id_unit')->all();
    }
}
