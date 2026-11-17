<?php

namespace App\Http\Controllers\Development;

use App\Domain\Development\Idps;
use App\Domain\Development\TrainingEffectiveness;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Privacy\SmallGroups;
use Illuminate\Http\Request;

/** Laporan CBHRM 7 — pelaksanaan & efektivitas pelatihan per intervensi (evaluasi empat tingkat). */
class EffectivenessController extends Controller
{
    use AuthorizesHcmsModule;

    public function __construct(private TrainingEffectiveness $effectiveness)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'trainingeffect';
    }

    public function index(Request $request)
    {
        $this->authorizeTask('is_view');
        $year = (int) $request->input('year', now()->year);
        $year = $year >= 2020 && $year <= 2100 ? $year : now()->year;
        $rows = $this->effectiveness->byIntervention($year);
        $participants = (int) $rows->sum('participants');
        $completed = (int) $rows->sum('completed');
        $cost = (float) $rows->sum('cost');
        $weighted = fn (string $value, string $n) => ($w = $rows->filter(fn ($r) => $r->{$value} !== null)->sum($n)) > 0
            ? round($rows->filter(fn ($r) => $r->{$value} !== null)->sum(fn ($r) => $r->{$value} * $r->{$n}) / $w, 2) : null;

        return view('development.effectiveness', [
            'pageTitle' => 'Efektivitas Pelatihan', 'year' => $year, 'rows' => $rows,
            'mask' => app(SmallGroups::class)->masks('trainingeffect'),
            'sessions' => (int) $rows->sum('sessions'), 'participants' => $participants, 'completed' => $completed, 'cost' => $cost,
            'completion' => $participants ? round($completed / $participants * 100, 1) : null,
            'costPerCompleted' => $completed && $cost > 0 ? round($cost / $completed) : null,
            'l1' => $weighted('l1', 'l1_n'), 'l2' => $weighted('l2', 'l2_n'), 'l3' => $weighted('l3', 'l3_n'), 'l4' => $weighted('l4', 'l4_n'),
            'l3n' => (int) $rows->sum('l3_n'), 'l4n' => (int) $rows->sum('l4_n'),
            'types' => Idps::TYPES, 'indications' => TrainingEffectiveness::INDICATIONS,
        ]);
    }
}
