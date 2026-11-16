<?php

namespace Tests\Unit\Competency;

use App\Domain\Competency\Services\RaterAggregator;
use PHPUnit\Framework\TestCase;

class RaterAggregatorTest extends TestCase
{
    public function test_bobot_default_50_20_20_10(): void
    {
        $result = (new RaterAggregator)->aggregate([
            'manager' => [4],
            'peer' => [3, 3, 3],
            'subordinate' => [2, 2, 2],
            'self' => [5],
        ]);

        // 4·0.5 + 3·0.2 + 2·0.2 + 5·0.1 = 3.5
        $this->assertSame(3.5, $result['score']);
        $this->assertSame('raters', $result['source']);
    }

    public function test_rekan_dan_bawahan_kurang_dari_tiga_tidak_dihitung_dan_bobot_dibagi_ulang(): void
    {
        $result = (new RaterAggregator)->aggregate([
            'manager' => [4],
            'peer' => [1, 1],            // hanya 2 penilai → gugur
            'subordinate' => [3, 3, 3],
            'self' => [4],
        ]);

        // bobot tersisa 50/20/10 → (4·50 + 3·20 + 4·10) / 80 = 3.75
        $this->assertSame(3.75, $result['score']);
        $this->assertFalse($result['groups']['peer']['counted']);
        $this->assertSame(2, $result['groups']['peer']['raters']);
    }

    public function test_rata_rata_per_kelompok_lebih_dulu(): void
    {
        $result = (new RaterAggregator)->aggregate(['manager' => [3, 5], 'self' => [2]]);

        // atasan rata-rata 4, bobot 50/10 → (4·50 + 2·10) / 60 = 3.67
        $this->assertSame(3.67, $result['score']);
        $this->assertSame(4.0, $result['groups']['manager']['mean']);
    }

    public function test_assessment_center_menggantikan_metode_lain(): void
    {
        $result = (new RaterAggregator)->aggregate(['manager' => [2], 'self' => [5]], 4.0);

        $this->assertSame(4.0, $result['score']);
        $this->assertSame('assessment_center', $result['source']);
    }

    public function test_tanpa_penilai_yang_dihitung_hasilnya_null(): void
    {
        $this->assertNull((new RaterAggregator)->aggregate([])['score']);
        $this->assertNull((new RaterAggregator)->aggregate(['peer' => [4, 4]])['score']);
        $this->assertNull((new RaterAggregator)->aggregate(['hr' => [4]])['score'], 'HR berbobot 0 secara default');
    }

    public function test_bobot_dapat_dikonfigurasi(): void
    {
        $result = (new RaterAggregator(['manager' => 70, 'hr' => 30]))->aggregate(['manager' => [3], 'hr' => [5], 'self' => [1]]);

        $this->assertSame(3.6, $result['score']);
        $this->assertFalse($result['groups']['self']['counted']);
    }
}
