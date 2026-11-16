<?php

namespace Tests\Unit\Performance;

use App\Domain\Performance\Services\PerformancePolicy;
use App\Domain\Performance\Services\ScoreCalculator;
use PHPUnit\Framework\TestCase;

class ScoreCalculatorTest extends TestCase
{
    private function calc(array $override = []): ScoreCalculator
    {
        return new ScoreCalculator(array_replace_recursive(PerformancePolicy::DEFAULTS, $override));
    }

    public function test_higher_dan_lower_dengan_cap(): void
    {
        $c = $this->calc();
        $this->assertSame(90.0, $c->achievement(['kpi_type' => 'higher', 'target' => 100], 90));
        $this->assertSame(120.0, $c->achievement(['kpi_type' => 'higher', 'target' => 100], 150), 'dibatasi cap 120');
        $this->assertSame(130.0, $c->achievement(['kpi_type' => 'higher', 'target' => 100, 'cap' => 150], 130), 'cap per KPI');
        // Lower is better: (2 − R/T) × 100
        $this->assertEqualsWithDelta(80.0, $c->achievement(['kpi_type' => 'lower', 'target' => 5], 6), 0.001);
        $this->assertSame(120.0, $c->achievement(['kpi_type' => 'lower', 'target' => 5], 0), 'aman saat realisasi 0');
        $this->assertSame(0.0, $c->achievement(['kpi_type' => 'lower', 'target' => 5], 12), 'tidak negatif');
        $this->assertSame(100.0, $c->achievement(['kpi_type' => 'lower', 'target' => 0], 0), 'target nol tercapai');
        $this->assertSame(0.0, $c->achievement(['kpi_type' => 'lower', 'target' => 0], 1));
    }

    public function test_range_milestone_dan_kualitatif(): void
    {
        $c = $this->calc();
        $range = ['kpi_type' => 'range', 'range_min' => 60, 'range_max' => 65];
        $this->assertSame(100.0, $c->achievement($range, 62));
        // 70 di atas 65: simpangan 5/65 = 7,69% → 100 − 7,69
        $this->assertEqualsWithDelta(92.31, $c->achievement($range, 70), 0.01);
        $this->assertEqualsWithDelta(90.0, $c->achievement($range, 54), 0.01);

        $milestones = [['weight' => 50, 'done' => true], ['weight' => 30, 'done' => true, 'late' => true], ['weight' => 20, 'done' => false]];
        // 50 + 30 × 0,75 = 72,5
        $this->assertEqualsWithDelta(72.5, $c->achievement(['kpi_type' => 'milestone'], $milestones), 0.001);

        $this->assertSame(85.0, $c->achievement(['kpi_type' => 'qualitative'], 4));
    }

    public function test_konversi_perilaku_dan_interpolasi(): void
    {
        $c = $this->calc();
        $this->assertSame([50.0, 65.0, 75.0, 85.0, 95.0], array_map(fn ($s) => $c->convert($s), [1, 2, 3, 4, 5]));
        $this->assertEqualsWithDelta(80.0, $c->convert(3.5), 0.001);
        $this->assertEqualsWithDelta(80.0, $c->behaviorScore([3, 4, null]), 0.001);
        $this->assertNull($c->behaviorScore([]));
    }

    public function test_skor_akhir_60_30_10_dan_band(): void
    {
        $c = $this->calc();
        $what = $c->whatScore([
            ['weight' => 40, 'achievement' => 110],
            ['weight' => 30, 'achievement' => 90],
            ['weight' => 30, 'achievement' => 80],
        ]);
        $this->assertEqualsWithDelta(95.0, $what, 0.001);

        // 95×0,6 + 85×0,3 + 75×0,1 = 57 + 25,5 + 7,5 = 90
        $result = $c->finalScore($what, 85, 75);
        $this->assertSame(['raw' => 90.0, 'score' => 90.0, 'band' => 'outstanding'], $result);

        // Skor mentah > 100 disimpan, rating memakai 100
        $over = $c->finalScore(120, 95, 95);
        $this->assertSame(110.0, $over['raw']);
        $this->assertSame(100.0, $over['score']);

        $this->assertSame('exceeds', $c->band(89.99));
        $this->assertSame('meets', $c->band(70));
        $this->assertSame('unsatisfactory', $c->band(12));
    }

    public function test_bobot_per_level_jabatan(): void
    {
        $c = $this->calc(['weight_overrides' => [['job_level_id' => 7, 'what' => 50, 'how' => 40, 'contribution' => 10]]]);
        $this->assertSame(['what' => 50.0, 'how' => 40.0, 'contribution' => 10.0], $c->weightsFor(7));
        $this->assertSame(['what' => 60.0, 'how' => 30.0, 'contribution' => 10.0], $c->weightsFor(3));
        $this->assertSame(81.0, $c->finalScore(80, 85, 70, $c->weightsFor(7))['score']);
    }

    public function test_prorata_menggabung_penugasan_pendek(): void
    {
        $c = $this->calc();
        // 200 hari skor 80, lalu 165 hari skor 90 → (80×200 + 90×165) / 365
        $r = $c->prorate([['days' => 200, 'score' => 80], ['days' => 165, 'score' => 90]]);
        $this->assertTrue($r['rated']);
        $this->assertEqualsWithDelta(84.52, $r['score'], 0.01);

        // 60 hari pertama digabung ke penugasan berikutnya (memakai skor penugasan berikutnya)
        $r = $c->prorate([['days' => 60, 'score' => 50], ['days' => 305, 'score' => 90]]);
        $this->assertSame([['days' => 365, 'score' => 90.0, 'weight' => 1.0]], $r['segments']);

        // Penugasan terakhir yang pendek digabung ke sebelumnya
        $r = $c->prorate([['days' => 300, 'score' => 70], ['days' => 65, 'score' => 100]]);
        $this->assertSame(70.0, $r['score']);

        // Total di bawah batas minimum → not rated
        $this->assertFalse($c->prorate([['days' => 45, 'score' => 90]])['rated']);
    }

    public function test_validasi_kebijakan(): void
    {
        $policy = new PerformancePolicy;
        $this->assertSame([], $policy->problems(PerformancePolicy::DEFAULTS));

        $bad = PerformancePolicy::DEFAULTS;
        $bad['weights']['contribution'] = 20;
        $bad['kpi']['max_count'] = 2;
        $bad['conversion'][3] = 60;
        $problems = $policy->problems($bad);
        $this->assertCount(4, $problems, implode(' | ', $problems));
    }
}
