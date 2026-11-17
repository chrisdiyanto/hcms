<?php

namespace App\Domain\Development;

use App\Domain\Employee\Services\EmployeeScope;
use App\Support\Tenancy\TenantScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Efektivitas pelatihan per intervensi (laporan CBHRM 7; kamus metrik §7, K6).
 *
 * - Penyelesaian = lulus ÷ peserta (tanpa yang batal). Biaya per peserta = biaya kelas ÷ lulus.
 * - Tingkat 1 (reaksi): rata-rata skor 1–5; dilaporkan hanya bila respons ≥ 50% peserta hadir/lulus.
 * - Tingkat 2 (pembelajaran): rata-rata post − pre peserta yang punya keduanya.
 * - Tingkat 3 (penerapan): skor kompetensi sasaran pada asesmen 3–6 bulan setelah kelas berakhir
 *   dibanding skor terakhir sebelum kelas dimulai (riwayat P1).
 * - Tingkat 4 (dampak): skor rating final periode pertama yang berakhir setelah kelas dibanding
 *   rating final terakhir yang periodenya berakhir sebelum kelas dimulai. Tanpa klaim sebab-akibat.
 * - Indikasi program: hentikan / perbaiki / lanjutkan (aturan di indication()); keputusan tetap pada pengelola.
 */
class TrainingEffectiveness
{
    public const MIN_RESPONSE_RATE = 50.0;

    public const L3_FROM_MONTHS = 3;

    public const L3_TO_MONTHS = 6;

    public const INDICATIONS = ['continue' => 'Lanjutkan', 'improve' => 'Perbaiki', 'stop' => 'Pertimbangkan dihentikan', 'insufficient' => 'Data belum cukup'];

    /** @return Collection<int, object> satu baris per intervensi yang memiliki kelas pada tahun itu */
    public function byIntervention(int $year): Collection
    {
        $visible = EmployeeScope::query()->select('employees.id');
        $sessions = TenantScope::table('training_sessions')->join('development_interventions as i', 'i.id', '=', 'training_sessions.intervention_id')
            ->whereYear('training_sessions.start_date', $year)->where('training_sessions.status', '!=', 'cancelled')
            ->get(['training_sessions.*', 'i.name as intervention_name', 'i.type', 'i.competency_id']);
        if ($sessions->isEmpty()) {
            return collect();
        }
        $participants = TenantScope::table('training_participants')->whereIn('session_id', $sessions->pluck('id'))
            ->whereIn('employee_id', $visible)->where('status', '!=', 'cancelled')->get()->groupBy('session_id');
        $codes = TenantScope::table('competencies')->whereIn('id', $sessions->pluck('competency_id')->filter())->pluck('code', 'id');

        return $sessions->groupBy('intervention_id')->map(function ($group) use ($participants, $codes) {
            $first = $group->first();
            $people = $group->flatMap(fn ($s) => ($participants[$s->id] ?? collect())->map(fn ($p) => (object) ((array) $p + ['start' => $s->start_date, 'end' => $s->end_date])));
            $completed = $people->where('status', 'completed');
            $present = $people->whereIn('status', ['attended', 'completed']);
            $responses = $present->whereNotNull('reaction_score');
            $responseRate = $present->count() ? $responses->count() / $present->count() * 100 : 0.0;
            $tested = $people->filter(fn ($p) => $p->pre_score !== null && $p->post_score !== null);
            $l3 = $this->competencyChange($completed, $codes[$first->competency_id] ?? null);
            $l4 = $this->performanceChange($completed);
            $cost = (float) $group->sum('cost_total');

            $row = (object) [
                'intervention_id' => (int) $first->intervention_id, 'name' => $first->intervention_name, 'type' => $first->type,
                'sessions' => $group->count(), 'participants' => $people->count(), 'completed' => $completed->count(),
                'completion' => $people->count() ? round($completed->count() / $people->count() * 100, 1) : null,
                'cost' => $cost, 'cost_per_completed' => $completed->count() && $cost > 0 ? round($cost / $completed->count()) : null,
                'l1_n' => $responses->count(), 'l1_rate' => round($responseRate, 1),
                'l1' => $responseRate >= self::MIN_RESPONSE_RATE && $responses->isNotEmpty() ? round((float) $responses->avg('reaction_score'), 2) : null,
                'l2_n' => $tested->count(), 'l2' => $tested->isNotEmpty() ? round((float) $tested->avg(fn ($p) => $p->post_score - $p->pre_score), 2) : null,
                'l3_n' => $l3['n'], 'l3' => $l3['avg'], 'l4_n' => $l4['n'], 'l4' => $l4['avg'],
            ];
            $row->indication = self::indication($row);

            return $row;
        })->sortBy('name')->values();
    }

    /**
     * Aturan indikasi (default; dapat disesuaikan pemilik produk):
     * hentikan bila reaksi < 3,0 atau rata-rata kenaikan post−pre ≤ 0 (≥ 3 peserta teruji);
     * perbaiki bila reaksi < 3,5 atau penyelesaian < 70%; data belum cukup bila belum ada peserta lulus.
     */
    public static function indication(object $r): string
    {
        if ($r->completed === 0) {
            return 'insufficient';
        }
        if (($r->l1 !== null && $r->l1 < 3.0) || ($r->l2 !== null && $r->l2_n >= 3 && $r->l2 <= 0)) {
            return 'stop';
        }
        if (($r->l1 !== null && $r->l1 < 3.5) || ($r->completion !== null && $r->completion < 70)) {
            return 'improve';
        }

        return 'continue';
    }

    /** Tingkat 3: perubahan skor kompetensi sasaran (asesmen 3–6 bulan setelah kelas vs sebelum kelas). */
    private function competencyChange(Collection $completed, ?string $code): array
    {
        if (! $code || $completed->isEmpty()) {
            return ['n' => 0, 'avg' => null];
        }
        $history = TenantScope::table('employee_competency_history')->whereIn('employee_id', $completed->pluck('employee_id'))
            ->where('competency_code', $code)->orderBy('assessed_at')->get(['employee_id', 'score', 'assessed_at'])->groupBy('employee_id');
        $deltas = $completed->map(function ($p) use ($history) {
            $rows = $history->get($p->employee_id, collect());
            $before = $rows->filter(fn ($h) => $h->assessed_at <= $p->start)->last();
            $from = Carbon::parse($p->end)->addMonthsNoOverflow(self::L3_FROM_MONTHS)->toDateString();
            $to = Carbon::parse($p->end)->addMonthsNoOverflow(self::L3_TO_MONTHS)->toDateString();
            $after = $rows->first(fn ($h) => $h->assessed_at >= $from && $h->assessed_at <= $to);

            return $before && $after ? (float) $after->score - (float) $before->score : null;
        })->filter(fn ($d) => $d !== null);

        return ['n' => $deltas->count(), 'avg' => $deltas->isNotEmpty() ? round($deltas->avg(), 2) : null];
    }

    /** Tingkat 4: rating final periode berikutnya vs periode sebelum kelas. */
    private function performanceChange(Collection $completed): array
    {
        if ($completed->isEmpty()) {
            return ['n' => 0, 'avg' => null];
        }
        $ratings = TenantScope::table('performance_ratings')->join('performance_periods as pp', 'pp.id', '=', 'performance_ratings.period_id')
            ->whereIn('performance_ratings.employee_id', $completed->pluck('employee_id'))->where('performance_ratings.status', 'final')
            ->where('performance_ratings.is_current', true)->where('performance_ratings.rated', true)
            ->orderBy('pp.end_date')->get(['performance_ratings.employee_id', 'performance_ratings.final_score', 'pp.end_date'])->groupBy('employee_id');
        $deltas = $completed->map(function ($p) use ($ratings) {
            $rows = $ratings->get($p->employee_id, collect());
            $before = $rows->filter(fn ($r) => $r->end_date < $p->start)->last();
            $after = $rows->first(fn ($r) => $r->end_date > $p->end);

            return $before && $after ? (float) $after->final_score - (float) $before->final_score : null;
        })->filter(fn ($d) => $d !== null);

        return ['n' => $deltas->count(), 'avg' => $deltas->isNotEmpty() ? round($deltas->avg(), 2) : null];
    }
}
