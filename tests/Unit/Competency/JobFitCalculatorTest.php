<?php

namespace Tests\Unit\Competency;

use App\Domain\Competency\Services\JobFitCalculator;
use PHPUnit\Framework\TestCase;

class JobFitCalculatorTest extends TestCase
{
    /** Contoh tabel blueprint 7.4. */
    private function blueprintExample(): array
    {
        return [
            ['code' => 'RISK', 'weight' => 3, 'required' => 4, 'actual' => 3],
            ['code' => 'LEAD', 'weight' => 3, 'required' => 4, 'actual' => 2],
            ['code' => 'STRAT', 'weight' => 2, 'required' => 4, 'actual' => 4],
            ['code' => 'DATA', 'weight' => 1, 'required' => 3, 'actual' => 2],
        ];
    }

    public function test_contoh_blueprint_menghasilkan_jfi_71_3_persen(): void
    {
        $result = (new JobFitCalculator)->calculate($this->blueprintExample());

        $this->assertSame(71.3, round($result['jfi'], 1));
        $this->assertSame(9.0, $result['total_weight']);
        $this->assertSame(6.42, round($result['total_score'], 2));
        $this->assertSame([2.25, 1.5, 2.0, 0.67], array_map(fn ($i) => round($i['score'], 2), $result['items']));
        $this->assertSame([-1.0, -2.0, 0.0, -1.0], array_column($result['items'], 'gap'));
        $this->assertSame(JobFitCalculator::READY_LATER, $result['band']);
        $this->assertTrue($result['complete']);
        $this->assertSame(100.0, $result['coverage']);
        $this->assertSame('71,3%', JobFitCalculator::format($result['jfi']));
    }

    public function test_kelebihan_kompetensi_dibatasi_100_persen(): void
    {
        $result = (new JobFitCalculator)->calculate([
            ['code' => 'A', 'weight' => 3, 'required' => 3, 'actual' => 5],
            ['code' => 'B', 'weight' => 1, 'required' => 4, 'actual' => 2],
        ]);

        // (min(5/3,1)·3 + 2/4·1) / 4 = 3.5 / 4
        $this->assertSame(87.5, $result['jfi']);
        $this->assertSame(2.0, $result['items'][0]['gap']);
        $this->assertSame(JobFitCalculator::READY_1_2, $result['band']);
    }

    public function test_band_kesiapan_dan_batas_dapat_dikonfigurasi(): void
    {
        $calc = new JobFitCalculator;
        $this->assertSame(JobFitCalculator::READY_NOW, $calc->band(90.0));
        $this->assertSame(JobFitCalculator::READY_1_2, $calc->band(89.94));
        $this->assertSame(JobFitCalculator::READY_1_2, $calc->band(75.0));
        $this->assertSame(JobFitCalculator::READY_LATER, $calc->band(74.9));

        $this->assertSame(JobFitCalculator::READY_NOW, (new JobFitCalculator([85, 70]))->band(86));
    }

    /** Kamus metrik K1: di bawah 80% bobot berskor, JFI tidak dihitung (bukan nol). */
    public function test_cakupan_di_bawah_80_persen_jfi_data_belum_cukup(): void
    {
        $result = (new JobFitCalculator)->calculate([
            ['code' => 'A', 'weight' => 2, 'required' => 4, 'actual' => 4],
            ['code' => 'B', 'weight' => 2, 'required' => 4, 'actual' => null],
        ]);

        $this->assertNull($result['jfi']);
        $this->assertSame(JobFitCalculator::INSUFFICIENT, $result['band']);
        $this->assertSame(50.0, $result['coverage']);
        $this->assertFalse($result['complete']);
        $this->assertNull($result['items'][1]['gap'], 'kompetensi tanpa skor tidak punya gap');
        $this->assertSame('—', JobFitCalculator::format($result['jfi']));
    }

    /** K1: tepat 80% bobot berskor → JFI dihitung, yang belum dinilai bernilai 0 dan ditandai belum lengkap. */
    public function test_cakupan_80_persen_jfi_dihitung_dan_belum_lengkap(): void
    {
        $result = (new JobFitCalculator)->calculate([
            ['code' => 'A', 'weight' => 3, 'required' => 4, 'actual' => 4],
            ['code' => 'B', 'weight' => 1, 'required' => 4, 'actual' => 4],
            ['code' => 'C', 'weight' => 1, 'required' => 4, 'actual' => null],
        ]);

        $this->assertSame(80.0, $result['coverage']);
        $this->assertSame(80.0, $result['jfi']);
        $this->assertSame(JobFitCalculator::READY_1_2, $result['band']);
        $this->assertFalse($result['complete']);
    }

    /** K3: gap kritis = kritikalitas 3 dan gap ≤ −1. */
    public function test_gap_kritis(): void
    {
        $items = (new JobFitCalculator)->calculate([
            ['code' => 'A', 'weight' => 3, 'required' => 4, 'actual' => 3],
            ['code' => 'B', 'weight' => 3, 'required' => 4, 'actual' => 3.5],
            ['code' => 'C', 'weight' => 2, 'required' => 4, 'actual' => 2],
        ])['items'];

        $this->assertSame([true, false, false], array_column($items, 'critical'));
        $this->assertFalse(JobFitCalculator::isCriticalGap(3, null));
    }

    public function test_profil_kosong_tidak_membagi_nol(): void
    {
        $result = (new JobFitCalculator)->calculate([]);

        $this->assertNull($result['jfi']);
        $this->assertSame(JobFitCalculator::INSUFFICIENT, $result['band']);
    }
}
