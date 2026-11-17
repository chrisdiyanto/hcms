<?php

namespace App\Domain\Competency\Services;

use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;

/**
 * Riwayat hasil kompetensi yang hanya bertambah (prasyarat P1 laporan CBHRM).
 *
 * Tabel "terkini" (employee_competencies, competency_gaps, job_fit_results) tetap ditimpa
 * setiap asesmen sebagai jalan pintas laporan. Setiap penutupan asesmen dan setiap perhitungan
 * ulang JFI juga dicatat di sini, sehingga riwayat skor, tren gap, dan perbandingan
 * sebelum/sesudah intervensi dapat dihitung. Baris riwayat tidak dapat diubah (trigger MySQL).
 */
class CompetencyHistory
{
    public const TRIGGERS = ['assessment' => 'Penutupan asesmen', 'recalculation' => 'Hitung ulang', 'backfill' => 'Data awal'];

    /** Skor per kompetensi yang dihasilkan satu asesmen. */
    public function recordScore(object $assessment, int $competencyId, string $code, array $row): void
    {
        DB::table('employee_competency_history')->insertOrIgnore(TenantScope::fillForInsert('employee_competency_history', [
            'employee_id' => $assessment->employee_id, 'assessment_id' => $assessment->id,
            'competency_id' => $competencyId, 'competency_code' => $code,
            'score' => $row['score'], 'source' => $row['source'], 'assessed_at' => $row['assessed_at'],
            'valid_until' => $row['valid_until'], 'breakdown' => $row['breakdown'], 'created_at' => now(),
        ]));
    }

    /**
     * Satu perhitungan JFI beserta gap per kompetensi.
     *
     * @param  array{jfi: ?float, band: string, complete: bool, coverage?: float, items: array}  $fit
     */
    public function recordFit(int $employeeId, int $positionId, ?int $assessmentId, array $fit, string $trigger, $computedAt): ?int
    {
        // Hitung ulang berkala tidak menambah riwayat bila hasilnya sama dengan perhitungan terakhir
        if ($trigger === 'recalculation') {
            $last = TenantScope::table('job_fit_history')->where('employee_id', $employeeId)->where('position_id', $positionId)
                ->orderByDesc('computed_at')->orderByDesc('id')->first();
            $same = fn ($a, $b) => ($a === null && $b === null) || ($a !== null && $b !== null && abs((float) $a - (float) $b) < 0.005);
            if ($last && $last->band === $fit['band'] && (bool) $last->complete === $fit['complete']
                && $same($last->jfi, $fit['jfi'] === null ? null : round($fit['jfi'], 2)) && $same($last->coverage, round($fit['coverage'] ?? 0, 2))) {
                return null;
            }
        }
        $id = DB::table('job_fit_history')->insertGetId(TenantScope::fillForInsert('job_fit_history', [
            'employee_id' => $employeeId, 'position_id' => $positionId, 'assessment_id' => $assessmentId,
            'jfi' => $fit['jfi'] === null ? null : round($fit['jfi'], 2), 'coverage' => isset($fit['coverage']) ? round($fit['coverage'], 2) : null,
            'band' => $fit['band'], 'complete' => $fit['complete'],
            'trigger' => $trigger, 'computed_at' => $computedAt, 'created_at' => now(),
        ]));
        $rows = array_map(fn ($item) => TenantScope::fillForInsert('competency_gap_history', [
            'job_fit_history_id' => $id, 'employee_id' => $employeeId, 'position_id' => $positionId,
            'competency_id' => $item['competency_id'], 'competency_code' => $item['code'],
            'required_level' => (int) $item['required'], 'criticality' => (int) $item['weight'],
            'actual' => $item['actual'], 'gap' => $item['gap'] === null ? null : round($item['gap'], 2),
        ]), $fit['items']);
        if ($rows) {
            DB::table('competency_gap_history')->insert($rows);
        }

        return $id;
    }

    // ------------------------------------------------------------------ baca

    /** JFI yang dihasilkan sebuah asesmen (bukan hasil terkini pegawai). */
    public function fitForAssessment(int $assessmentId): ?object
    {
        return TenantScope::table('job_fit_history')->where('assessment_id', $assessmentId)
            ->whereIn('trigger', ['assessment', 'backfill'])->orderByDesc('computed_at')->orderByDesc('id')->first();
    }

    /** Gap per kompetensi pada satu perhitungan JFI, gap terbesar lebih dulu. */
    public function gaps(int $fitHistoryId)
    {
        return TenantScope::table('competency_gap_history')->join('competencies', 'competencies.id', '=', 'competency_gap_history.competency_id')
            ->where('competency_gap_history.job_fit_history_id', $fitHistoryId)->orderBy('competency_gap_history.gap')
            ->get(['competency_gap_history.*', 'competencies.name']);
    }

    /** Riwayat JFI pegawai (semua posisi), terlama lebih dulu. */
    public function fitTimeline(int $employeeId)
    {
        return TenantScope::table('job_fit_history')->join('positions', 'positions.id', '=', 'job_fit_history.position_id')
            ->leftJoin('competency_assessments', 'competency_assessments.id', '=', 'job_fit_history.assessment_id')
            ->where('job_fit_history.employee_id', $employeeId)->orderBy('job_fit_history.computed_at')->orderBy('job_fit_history.id')
            ->get(['job_fit_history.*', 'positions.title as position_title', 'competency_assessments.title as assessment_title']);
    }

    /**
     * Skor per kompetensi lintas asesmen: kode => [skor terlama … terbaru], dengan skor sebelumnya
     * untuk menghitung perubahan.
     *
     * @return array<string, array{name: string, points: array<int, array{date: string, score: float, source: string}>, latest: float, previous: ?float}>
     */
    public function scoreTimeline(int $employeeId): array
    {
        $rows = TenantScope::table('employee_competency_history')->join('competencies', 'competencies.id', '=', 'employee_competency_history.competency_id')
            ->where('employee_competency_history.employee_id', $employeeId)
            ->orderBy('employee_competency_history.assessed_at')->orderBy('employee_competency_history.id')
            ->get(['employee_competency_history.competency_code', 'employee_competency_history.score', 'employee_competency_history.source',
                'employee_competency_history.assessed_at', 'competencies.name']);

        return $rows->groupBy('competency_code')->map(function ($items) {
            $points = $items->map(fn ($r) => ['date' => (string) $r->assessed_at, 'score' => (float) $r->score, 'source' => $r->source])->values()->all();

            return [
                'name' => $items->last()->name, 'points' => $points,
                'latest' => end($points)['score'], 'previous' => count($points) > 1 ? $points[count($points) - 2]['score'] : null,
            ];
        })->sortKeys()->all();
    }

    // -------------------------------------------------------------- backfill

    /**
     * Isi riwayat dari tabel "terkini" yang belum tercatat (semua tenant, idempoten).
     * Hasil yang sudah tertimpa sebelum riwayat ada tidak dapat dipulihkan.
     *
     * @return array{scores: int, fits: int}
     */
    public static function backfill(): array
    {
        $scores = DB::table('employee_competencies as ec')
            ->whereNotExists(fn ($q) => $q->from('employee_competency_history as h')->whereColumn('h.assessment_id', 'ec.assessment_id')
                ->whereColumn('h.employee_id', 'ec.employee_id')->whereColumn('h.competency_code', 'ec.competency_code'))
            ->get();
        foreach ($scores->chunk(1000) as $chunk) {
            DB::table('employee_competency_history')->insert($chunk->map(fn ($r) => [
                'tenant_id' => $r->tenant_id, 'employee_id' => $r->employee_id, 'assessment_id' => $r->assessment_id,
                'competency_id' => $r->competency_id, 'competency_code' => $r->competency_code, 'score' => $r->score, 'source' => $r->source,
                'assessed_at' => $r->assessed_at, 'valid_until' => $r->valid_until, 'breakdown' => $r->breakdown, 'created_at' => now(),
            ])->values()->all());
        }

        $fits = DB::table('job_fit_results as f')
            ->whereNotExists(fn ($q) => $q->from('job_fit_history as h')->whereColumn('h.employee_id', 'f.employee_id')
                ->whereColumn('h.position_id', 'f.position_id')->whereColumn('h.computed_at', 'f.computed_at'))
            ->get();
        foreach ($fits->chunk(500) as $chunk) {
            $gaps = DB::table('competency_gaps')->whereIn('employee_id', $chunk->pluck('employee_id'))->get()
                ->groupBy(fn ($g) => $g->employee_id.':'.$g->position_id);
            DB::transaction(function () use ($chunk, $gaps) {
                foreach ($chunk as $f) {
                    $id = DB::table('job_fit_history')->insertGetId([
                        'tenant_id' => $f->tenant_id, 'employee_id' => $f->employee_id, 'position_id' => $f->position_id,
                        'assessment_id' => $f->assessment_id, 'jfi' => $f->jfi, 'coverage' => $f->coverage ?? null, 'band' => $f->band, 'complete' => $f->complete,
                        'trigger' => 'backfill', 'computed_at' => $f->computed_at, 'created_at' => now(),
                    ]);
                    $rows = $gaps->get($f->employee_id.':'.$f->position_id, collect())->map(fn ($g) => [
                        'tenant_id' => $g->tenant_id, 'job_fit_history_id' => $id, 'employee_id' => $g->employee_id, 'position_id' => $g->position_id,
                        'competency_id' => $g->competency_id, 'competency_code' => $g->competency_code, 'required_level' => $g->required_level,
                        'criticality' => $g->criticality, 'actual' => $g->actual, 'gap' => $g->gap,
                    ])->values()->all();
                    if ($rows) {
                        DB::table('competency_gap_history')->insert($rows);
                    }
                }
            });
        }

        return ['scores' => $scores->count(), 'fits' => $fits->count()];
    }
}
