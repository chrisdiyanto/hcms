<?php

namespace App\Http\Controllers\Competency;

use App\Domain\Competency\Services\JobFitCalculator;
use App\Domain\Competency\Services\PositionReadiness;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Organization\PositionsController;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Kesiapan Posisi (laporan CBHRM 4): kandidat internal terhadap posisi sasaran. */
class ReadinessController extends Controller
{
    use AuthorizesHcmsModule;

    private const PER_PAGE = 50;

    public function __construct(private PositionReadiness $readiness)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'positionreadiness';
    }

    public function index(Request $request)
    {
        $this->authorizeTask('is_view');
        $positions = TenantScope::table('positions')->whereNull('deleted_at')->where('status', 'active')
            ->orderByDesc('is_critical')->orderBy('code')->get(['id', 'code', 'title', 'is_critical', 'critical_impact']);
        $positionId = (int) $request->query('position', 0);
        $target = $positions->firstWhere('id', $positionId);
        $unitId = $request->filled('unit') ? (int) $request->query('unit') : null;
        $horizon = array_key_exists((string) $request->query('horizon'), PositionReadiness::HORIZONS) ? (string) $request->query('horizon') : null;

        $result = $target ? $this->readiness->forPosition((int) $target->id, $unitId) : ['profile' => collect(), 'candidates' => collect()];
        $candidates = $result['candidates'];
        $counts = collect(PositionReadiness::HORIZONS)->map(fn ($l, $k) => $candidates->where('horizon', $k)->count())->all()
            + ['none' => $candidates->whereNull('horizon')->count()];
        $filtered = $horizon ? $candidates->where('horizon', $horizon)->values() : $candidates;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator($filtered->forPage($page, self::PER_PAGE)->values(), $filtered->count(), self::PER_PAGE, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        if ($target) {
            // Daftar berisi JFI individual: setiap akses tercatat (P5)
            AuditLog::sensitiveViewed('positions', $target->id, 'Melihat kesiapan kandidat untuk posisi '.$target->code.' — '.$target->title);
        }

        return view('competency.readiness.index', [
            'pageTitle' => 'Kesiapan Posisi',
            'pageNote' => $target ? $target->title : 'pilih posisi sasaran',
            'positions' => $positions, 'target' => $target, 'profile' => $result['profile'],
            'units' => TenantScope::table('unit')->orderBy('unit_name')->pluck('unit_name', 'id_unit'),
            'unitId' => $unitId, 'horizon' => $horizon, 'counts' => $counts, 'total' => $candidates->count(),
            'candidates' => $paginator,
            'horizons' => PositionReadiness::HORIZONS, 'bands' => JobFitCalculator::BAND_LABELS,
            'impacts' => PositionsController::IMPACTS,
        ]);
    }
}
