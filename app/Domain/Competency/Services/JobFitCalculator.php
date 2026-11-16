<?php

namespace App\Domain\Competency\Services;

/**
 * Job Fit Index (blueprint 7.4).
 *
 *   gap_i   = Actual_i − Required_i
 *   skor_i  = min(Actual_i / Required_i, 1) × bobot_i     (kelebihan kompetensi dibatasi 100%)
 *   JFI     = Σ skor_i / Σ bobot_i × 100%
 *
 * Band kesiapan (dapat dikonfigurasi): ≥ 90% siap sekarang; 75–89% siap 1–2 tahun;
 * < 75% siap lebih dari 2 tahun. Kompetensi yang belum dinilai dihitung Actual = 0
 * dan hasilnya ditandai belum lengkap.
 */
final class JobFitCalculator
{
    public const READY_NOW = 'ready_now';

    public const READY_1_2 = 'ready_1_2';

    public const READY_LATER = 'ready_later';

    public const BAND_LABELS = [
        self::READY_NOW => 'Siap sekarang',
        self::READY_1_2 => 'Siap 1–2 tahun',
        self::READY_LATER => 'Siap > 2 tahun',
    ];

    /** @param  array{0: float, 1: float}  $bands  [batas siap sekarang, batas siap 1–2 tahun] */
    public function __construct(private array $bands = [90.0, 75.0]) {}

    /**
     * @param  array<int, array{code: string, weight: int|float, required: int|float, actual: int|float|null}>  $items
     * @return array{jfi: float, band: string, complete: bool, total_weight: float, total_score: float, items: array<int, array>}
     */
    public function calculate(array $items): array
    {
        $totalWeight = 0.0;
        $totalScore = 0.0;
        $complete = true;
        $rows = [];

        foreach ($items as $item) {
            $weight = (float) $item['weight'];
            $required = (float) $item['required'];
            $actual = $item['actual'] === null ? null : (float) $item['actual'];
            if ($weight <= 0 || $required <= 0) {
                continue;
            }
            if ($actual === null) {
                $complete = false;
            }

            $ratio = min(($actual ?? 0.0) / $required, 1.0);
            $score = $ratio * $weight;
            $totalWeight += $weight;
            $totalScore += $score;

            $rows[] = $item + [
                'gap' => ($actual ?? 0.0) - $required,
                'score' => $score,
            ];
        }

        $jfi = $totalWeight > 0 ? $totalScore / $totalWeight * 100 : 0.0;

        return [
            'jfi' => $jfi,
            'band' => $this->band($jfi),
            'complete' => $complete,
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
}
