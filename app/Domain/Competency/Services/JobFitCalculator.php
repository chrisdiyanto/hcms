<?php

namespace App\Domain\Competency\Services;

/**
 * Job Fit Index (blueprint 7.4) dengan aturan kamus metrik CBHRM (docs/METRIK-CBHRM.md).
 *
 *   gap_i   = Actual_i − Required_i                         (kosong bila belum ada skor berlaku)
 *   skor_i  = min(Actual_i / Required_i, 1) × bobot_i       (kelebihan kompetensi dibatasi 100%)
 *   JFI     = Σ skor_i / Σ bobot_i × 100%
 *
 * K1: JFI hanya dihitung bila ≥ 80% bobot profil memiliki skor berlaku. Di bawah itu JFI kosong,
 *     band "data belum cukup", dan tidak masuk rata-rata. Di atas ambang, kompetensi yang belum
 *     dinilai tetap bernilai 0 di rumus (blueprint) dan hasil ditandai belum lengkap.
 * K2: skor kedaluwarsa diperlakukan sebagai tanpa skor (disaring pemanggil: hanya skor berlaku).
 * K3: gap kritis = kritikalitas 3 dan gap ≤ −1.
 *
 * Band kesiapan (dapat dikonfigurasi): ≥ 90% siap sekarang; 75–89% siap 1–2 tahun; < 75% siap > 2 tahun.
 */
final class JobFitCalculator
{
    public const READY_NOW = 'ready_now';

    public const READY_1_2 = 'ready_1_2';

    public const READY_LATER = 'ready_later';

    public const INSUFFICIENT = 'insufficient';

    public const BAND_LABELS = [
        self::READY_NOW => 'Siap sekarang',
        self::READY_1_2 => 'Siap 1–2 tahun',
        self::READY_LATER => 'Siap > 2 tahun',
        self::INSUFFICIENT => 'Data belum cukup',
    ];

    /** K1: persentase minimum bobot profil yang harus memiliki skor berlaku. */
    public const MIN_COVERAGE = 80.0;

    /** K3: gap kritis = kritikalitas CRITICAL_WEIGHT dan gap ≤ CRITICAL_GAP. */
    public const CRITICAL_WEIGHT = 3;

    public const CRITICAL_GAP = -1.0;

    /** @param  array{0: float, 1: float}  $bands  [batas siap sekarang, batas siap 1–2 tahun] */
    public function __construct(private array $bands = [90.0, 75.0]) {}

    /**
     * @param  array<int, array{code: string, weight: int|float, required: int|float, actual: int|float|null}>  $items
     * @return array{jfi: ?float, band: string, complete: bool, coverage: float, total_weight: float, total_score: float, items: array<int, array>}
     */
    public function calculate(array $items): array
    {
        $totalWeight = 0.0;
        $scoredWeight = 0.0;
        $totalScore = 0.0;
        $rows = [];

        foreach ($items as $item) {
            $weight = (float) $item['weight'];
            $required = (float) $item['required'];
            $actual = $item['actual'] === null ? null : (float) $item['actual'];
            if ($weight <= 0 || $required <= 0) {
                continue;
            }

            $score = min(($actual ?? 0.0) / $required, 1.0) * $weight;
            $totalWeight += $weight;
            $totalScore += $score;
            if ($actual !== null) {
                $scoredWeight += $weight;
            }

            $gap = $actual === null ? null : $actual - $required;
            $rows[] = $item + ['gap' => $gap, 'score' => $score, 'critical' => self::isCriticalGap((int) $weight, $gap)];
        }

        $coverage = $totalWeight > 0 ? $scoredWeight / $totalWeight * 100 : 0.0;
        $sufficient = $totalWeight > 0 && round($coverage, 2) >= self::MIN_COVERAGE;
        $jfi = $sufficient ? $totalScore / $totalWeight * 100 : null;

        return [
            'jfi' => $jfi,
            'band' => $jfi === null ? self::INSUFFICIENT : $this->band($jfi),
            'complete' => $totalWeight > 0 && $scoredWeight === $totalWeight,
            'coverage' => $coverage,
            'total_weight' => $totalWeight,
            'total_score' => $totalScore,
            'items' => $rows,
        ];
    }

    public function band(float $jfi): string
    {
        // Pembulatan 1 desimal agar 89,96% tidak tampil "90,0%" tapi masuk band bawah
        $jfi = round($jfi, 1);

        return match (true) {
            $jfi >= $this->bands[0] => self::READY_NOW,
            $jfi >= $this->bands[1] => self::READY_1_2,
            default => self::READY_LATER,
        };
    }

    /** Tampilan JFI: "71,3%" atau "—" bila data belum cukup (K1). */
    public static function format(?float $jfi): string
    {
        return $jfi === null ? '—' : number_format($jfi, 1, ',', '.').'%';
    }

    public static function isCriticalGap(int $criticality, ?float $gap): bool
    {
        return $gap !== null && $criticality >= self::CRITICAL_WEIGHT && $gap <= self::CRITICAL_GAP;
    }
}
