<?php

namespace App\Domain\Competency\Services;

/**
 * Skor aktual satu kompetensi dari banyak penilai (blueprint 7.3).
 *
 * - Rata-rata per kelompok penilai, lalu rata-rata tertimbang antar-kelompok.
 *   Bobot default: atasan 50%, rekan 20%, bawahan 20%, diri sendiri 10% (HR 0% = tidak dihitung).
 * - Kelompok rekan & bawahan hanya dihitung bila minimal 3 penilai (menjaga anonimitas);
 *   bobot kelompok yang gugur dibagi ulang secara proporsional ke kelompok lain.
 * - Hasil assessment center yang masih berlaku menggantikan semua metode lain.
 */
final class RaterAggregator
{
    public const DEFAULT_WEIGHTS = ['manager' => 50, 'peer' => 20, 'subordinate' => 20, 'self' => 10, 'hr' => 0];

    public const ANONYMOUS_GROUPS = ['peer', 'subordinate'];

    public const GROUP_LABELS = ['manager' => 'Atasan', 'peer' => 'Rekan', 'subordinate' => 'Bawahan', 'self' => 'Diri sendiri', 'hr' => 'HR'];

    public function __construct(
        private array $weights = self::DEFAULT_WEIGHTS,
        private int $minAnonymousRaters = 3,
    ) {}

    /**
     * @param  array<string, array<int, int|float>>  $scores  kelompok => daftar skor penilai
     * @return array{score: ?float, source: string, groups: array<string, array{mean: float, raters: int, weight: float, counted: bool}>}
     */
    public function aggregate(array $scores, ?float $assessmentCenter = null): array
    {
        $groups = [];
        foreach ($scores as $group => $values) {
            $values = array_values(array_filter($values, fn ($v) => $v !== null));
            if ($values === []) {
                continue;
            }
            $weight = (float) ($this->weights[$group] ?? 0);
            $enough = ! in_array($group, self::ANONYMOUS_GROUPS, true) || count($values) >= $this->minAnonymousRaters;
            $groups[$group] = [
                'mean' => (float) array_sum($values) / count($values),
                'raters' => count($values),
                'weight' => $weight,
                'counted' => $weight > 0 && $enough,
            ];
        }

        if ($assessmentCenter !== null) {
            return ['score' => round($assessmentCenter, 2), 'source' => 'assessment_center', 'groups' => $groups];
        }

        $counted = array_filter($groups, fn ($g) => $g['counted']);
        $totalWeight = array_sum(array_column($counted, 'weight'));
        if ($totalWeight <= 0) {
            return ['score' => null, 'source' => 'none', 'groups' => $groups];
        }

        $score = 0.0;
        foreach ($counted as $group) {
            $score += $group['mean'] * $group['weight'] / $totalWeight;
        }

        return ['score' => round($score, 2), 'source' => 'raters', 'groups' => $groups];
    }
}
