<?php

namespace App\Domain\Performance\Services;

use InvalidArgumentException;

/**
 * Mesin skor kinerja (blueprint 8.2–8.7). Murni: tanpa database, semua aturan dari
 * konfigurasi kebijakan yang dikunci di periode (lihat PerformancePolicy::DEFAULTS).
 *
 *   capaian KPI a  : dinormalisasi per tipe, lalu a' = min(max(a, 0), cap)
 *   What           : Σ a'·bobot / Σ bobot
 *   How, Kontribusi: rata-rata skor 1–5 → tabel konversi 0–100 (interpolasi linear antar-titik)
 *   Skor akhir     : What·w1 + How·w2 + Kontribusi·w3 (bobot dalam %, total 100)
 *   Rating         : skor akhir dibatasi 100 lalu dicocokkan ke band; nilai mentah tetap disimpan
 *   Prorata        : Σ skor_j · d_j / D; penugasan < 90 hari digabung ke penugasan berikutnya,
 *                    total < batas minimum → "not rated"
 */
final class ScoreCalculator
{
    public const TYPES = [
        'higher' => 'Semakin tinggi semakin baik',
        'lower' => 'Semakin rendah semakin baik',
        'range' => 'Dalam rentang',
        'milestone' => 'Milestone',
        'qualitative' => 'Kualitatif (rubrik 1–5)',
    ];

    public function __construct(private array $config) {}

    // ------------------------------------------------------------ capaian KPI

    /**
     * Capaian satu KPI (0..cap). $kpi: kpi_type, target, range_min, range_max, milestones, cap.
     * $actual: angka realisasi; untuk milestone = daftar [{weight, done, late}]; untuk kualitatif = skor 1–5.
     */
    public function achievement(array $kpi, mixed $actual): float
    {
        $raw = match ($kpi['kpi_type']) {
            'higher' => $this->higher((float) $kpi['target'], (float) $actual),
            'lower' => $this->lower((float) $kpi['target'], (float) $actual),
            'range' => $this->range((float) $kpi['range_min'], (float) $kpi['range_max'], (float) $actual),
            'milestone' => $this->milestone(is_array($actual) ? $actual : []),
            'qualitative' => $this->convert((float) $actual),
            default => throw new InvalidArgumentException('Tipe KPI tidak dikenal: '.$kpi['kpi_type']),
        };

        $cap = (float) ($kpi['cap'] ?? null ?: $this->config['cap']);

        return min(max($raw, 0.0), $cap);
    }

    private function higher(float $target, float $actual): float
    {
        if ($target == 0.0) {
            return $actual >= 0 ? 100.0 : 0.0;
        }

        return $actual / $target * 100;
    }

    /** (2 − R/T) × 100; aman saat R = 0. Target 0 (mis. nol kecelakaan): tercapai = 100, lainnya 0. */
    private function lower(float $target, float $actual): float
    {
        if ($target == 0.0) {
            return $actual <= 0 ? 100.0 : 0.0;
        }

        return (2 - $actual / $target) * 100;
    }

    /** 100 di dalam rentang; di luar turun linear `range_penalty` poin per 1% simpangan dari batas terdekat. */
    private function range(float $min, float $max, float $actual): float
    {
        if ($actual >= $min && $actual <= $max) {
            return 100.0;
        }
        $bound = $actual < $min ? $min : $max;
        $deviationPct = $bound == 0.0 ? abs($actual) * 100 : abs($actual - $bound) / abs($bound) * 100;

        return 100 - $deviationPct * (float) $this->config['range_penalty'];
    }

    /** Σ bobot milestone selesai (dinormalisasi ke 100); milestone terlambat dikurangi faktor keterlambatan. */
    private function milestone(array $milestones): float
    {
        $total = array_sum(array_map(fn ($m) => (float) ($m['weight'] ?? 0), $milestones));
        if ($total <= 0) {
            return 0.0;
        }
        $done = 0.0;
        foreach ($milestones as $m) {
            if (! empty($m['done'])) {
                $done += (float) $m['weight'] * (! empty($m['late']) ? 1 - (float) $this->config['milestone_late_penalty'] : 1);
            }
        }

        return $done / $total * 100;
    }

    // ------------------------------------------------------------ dimensi

    /** @param  array<int, array{weight: int|float, achievement: int|float}>  $items */
    public function whatScore(array $items): float
    {
        $weights = array_sum(array_map(fn ($i) => (float) $i['weight'], $items));
        if ($weights <= 0) {
            return 0.0;
        }

        return array_sum(array_map(fn ($i) => (float) $i['achievement'] * (float) $i['weight'], $items)) / $weights;
    }

    /** Rata-rata skor perilaku 1–5 → 0–100 (dipakai untuk How dan Kontribusi tambahan). */
    public function behaviorScore(array $scores): ?float
    {
        $scores = array_values(array_filter($scores, fn ($s) => $s !== null && $s !== ''));
        if ($scores === []) {
            return null;
        }

        return $this->convert(array_sum(array_map('floatval', $scores)) / count($scores));
    }

    /** Tabel konversi 1–5 → 0–100, interpolasi linear untuk rata-rata pecahan. */
    public function convert(float $score): float
    {
        $table = $this->config['conversion'];
        ksort($table);
        $keys = array_map('intval', array_keys($table));
        $score = min(max($score, (float) min($keys)), (float) max($keys));
        $low = (int) floor($score);
        $high = (int) ceil($score);
        if ($low === $high) {
            return (float) $table[$low];
        }

        return (float) $table[$low] + ($score - $low) * ((float) $table[$high] - (float) $table[$low]);
    }

    // ------------------------------------------------------------ skor akhir

    /**
     * @param  array{what: float, how: float, contribution: float}  $weights  persen, total 100
     * @return array{raw: float, score: float, band: string}
     */
    public function finalScore(float $what, ?float $how, ?float $contribution, ?array $weights = null): array
    {
        $weights ??= $this->config['weights'];
        $raw = ($what * $weights['what'] + ($how ?? 0) * $weights['how'] + ($contribution ?? 0) * $weights['contribution']) / 100;
        $score = min($raw, 100.0);

        return ['raw' => round($raw, 2), 'score' => round($score, 2), 'band' => $this->band($score)];
    }

    /** Bobot dimensi untuk level jabatan tertentu (override kebijakan), atau default. */
    public function weightsFor(?int $jobLevelId): array
    {
        foreach ($this->config['weight_overrides'] ?? [] as $o) {
            if ($jobLevelId !== null && (int) $o['job_level_id'] === $jobLevelId) {
                return ['what' => (float) $o['what'], 'how' => (float) $o['how'], 'contribution' => (float) $o['contribution']];
            }
        }

        return array_map('floatval', $this->config['weights']);
    }

    public function band(float $score): string
    {
        $score = round(min($score, 100.0), 2);
        $bands = $this->config['bands'];
        usort($bands, fn ($a, $b) => $b['min'] <=> $a['min']);
        foreach ($bands as $band) {
            if ($score >= (float) $band['min']) {
                return $band['code'];
            }
        }

        return end($bands)['code'];
    }

    // ------------------------------------------------------------ prorata

    /**
     * @param  array<int, array{days: int, score: float}>  $segments  urut kronologis per penugasan
     * @return array{rated: bool, score: ?float, segments: array<int, array{days: int, score: float, weight: float}>}
     */
    public function prorate(array $segments): array
    {
        $minSegment = (int) $this->config['prorata']['min_segment_days'];
        $minRated = (int) $this->config['prorata']['min_rated_days'];

        // Penugasan pendek digabung ke penugasan berikutnya (yang terakhir ke sebelumnya)
        $merged = [];
        $carry = 0;
        $count = count($segments);
        foreach (array_values($segments) as $i => $s) {
            $days = (int) $s['days'] + $carry;
            if ($days < $minSegment && $i < $count - 1) {
                $carry = $days;

                continue;
            }
            $carry = 0;
            if ($days < $minSegment && $merged !== []) {
                $merged[array_key_last($merged)]['days'] += $days;

                continue;
            }
            $merged[] = ['days' => $days, 'score' => (float) $s['score']];
        }

        $total = array_sum(array_column($merged, 'days'));
        if ($total < $minRated || $total <= 0) {
            return ['rated' => false, 'score' => null, 'segments' => []];
        }

        $score = 0.0;
        foreach ($merged as &$m) {
            $m['weight'] = (float) $m['days'] / $total;
            $score += $m['score'] * $m['weight'];
        }

        return ['rated' => true, 'score' => round($score, 2), 'segments' => $merged];
    }
}
