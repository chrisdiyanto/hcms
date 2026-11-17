<?php

namespace App\Http\Controllers\Development;

use App\Domain\Development\TrainingNeeds;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Privacy\SmallGroups;
use Illuminate\Http\Request;

/**
 * Laporan TNA (laporan CBHRM 5): kebutuhan yang DISETUJUI menurut sumber, prioritas, unit, dan
 * kompetensi; kebutuhan terbuka; kebutuhan berulang; perkiraan biaya. Saran otomatis dan
 * pengajuan yang belum ditinjau ditampilkan terpisah, tidak dihitung sebagai TNA.
 */
class NeedsReportController extends Controller
{
    use AuthorizesHcmsModule;

    /** Status yang dihitung sebagai TNA (sudah disetujui). */
    private const APPROVED = ['approved', 'planned', 'closed'];

    public function __construct(private TrainingNeeds $needs)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'tnareport';
    }

    public function index(Request $request)
    {
        $this->authorizeTask('is_view');
        $year = (int) $request->input('year', now()->year);
        $year = $year >= 2020 && $year <= 2100 ? $year : now()->year;
        $today = now()->toDateString();

        $base = $this->needs->visible()->where('training_needs.period_year', $year);
        $statusCounts = (clone $base)->selectRaw('training_needs.status, COUNT(*) n')->groupBy('training_needs.status')->pluck('n', 'status');
        $approved = (clone $base)->whereIn('training_needs.status', self::APPROVED);
        $group = fn (string $column) => (clone $approved)->selectRaw("{$column} as label, COUNT(*) as n")->groupBy('label')->pluck('n', 'label');

        $bySource = $group('training_needs.source');
        $byPriority = $group('training_needs.priority');
        $byUnit = (clone $approved)
            ->leftJoin('employee_assignments as a', function ($j) use ($today) {
                $j->on('a.employee_id', '=', 'employees.id')->where('a.type', 'primary')->where('a.effective_from', '<=', $today)
                    ->where(fn ($w) => $w->whereNull('a.effective_to')->orWhere('a.effective_to', '>=', $today));
            })
            ->leftJoin('positions as p', 'p.id', '=', 'a.position_id')->leftJoin('unit as u', 'u.id_unit', '=', 'p.unit_id')
            ->selectRaw("COALESCE(u.unit_name, 'Tanpa unit') as label, COUNT(DISTINCT training_needs.id) as n")->groupBy('label')->orderByDesc('n')->get()
            ->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->n]);
        $byTopic = (clone $approved)
            ->selectRaw('COALESCE(training_needs.competency_code, training_needs.topic) as k, MAX(training_needs.competency_code) as code, MAX(training_needs.topic) as topic, COUNT(*) as n')
            ->groupBy('k')->orderByDesc('n')->limit(15)->get()
            ->map(fn ($r) => ['label' => $r->code ? $r->code.' — '.$r->topic : $r->topic, 'value' => (int) $r->n]);

        // Berulang: pegawai × kompetensi yang juga disetujui pada tahun sebelumnya
        $recurring = (clone $approved)->whereNotNull('training_needs.competency_code')
            ->whereExists(fn ($q) => $q->from('training_needs as prev')->whereColumn('prev.employee_id', 'training_needs.employee_id')
                ->whereColumn('prev.competency_code', 'training_needs.competency_code')->where('prev.period_year', '<', $year)
                ->whereIn('prev.status', self::APPROVED))->count();
        $open = (clone $base)->where('training_needs.status', 'approved');
        $topOpen = (clone $open)->orderByRaw("FIELD(training_needs.priority, 'high', 'medium', 'low')")->orderByDesc('training_needs.priority_score')
            ->limit(10)->get(['training_needs.id', 'training_needs.topic', 'training_needs.competency_code', 'training_needs.priority', 'training_needs.due_date',
                'training_needs.suggested_intervention', 'employees.full_name']);

        return view('development.needs.report', [
            'pageTitle' => 'Laporan TNA', 'year' => $year,
            'mask' => app(SmallGroups::class)->masks('trainingneeds'),
            'approvedTotal' => (int) $statusCounts->only(self::APPROVED)->sum(),
            'openTotal' => (int) ($statusCounts['approved'] ?? 0),
            'plannedTotal' => (int) ($statusCounts['planned'] ?? 0),
            'pendingSuggested' => (int) ($statusCounts['suggested'] ?? 0),
            'pendingProposed' => (int) ($statusCounts['proposed'] ?? 0),
            'rejected' => (int) ($statusCounts['rejected'] ?? 0),
            'highTotal' => (int) ($byPriority['high'] ?? 0),
            'people' => (int) (clone $approved)->distinct()->count('training_needs.employee_id'),
            'cost' => (float) (clone $approved)->sum('training_needs.estimated_cost'),
            'recurring' => $recurring,
            'bySource' => collect(TrainingNeeds::SOURCES)->map(fn ($l, $k) => ['label' => $l, 'value' => (int) ($bySource[$k] ?? 0)])->filter(fn ($i) => $i['value'] > 0)->values(),
            'byPriority' => collect(TrainingNeeds::PRIORITIES)->map(fn ($l, $k) => ['label' => $l, 'value' => (int) ($byPriority[$k] ?? 0)])->filter(fn ($i) => $i['value'] > 0)->values(),
            'byUnit' => $byUnit, 'byTopic' => $byTopic, 'topOpen' => $topOpen,
            'priorities' => TrainingNeeds::PRIORITIES,
        ]);
    }
}
