<?php

namespace Tests\Unit\Config;

use App\Support\Config\SecretSettings;
use RuntimeException;
use Tests\TestCase;

class SecretSettingsTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($this->path, "APP_NAME=HCMS\nMAPS_API_KEY=\"lama\"\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    public function test_mengganti_dan_menambah_variabel_dengan_escape(): void
    {
        SecretSettings::writeEnv(['MAPS_API_KEY' => 'baru"$x', 'AI_API_KEY' => 'abc\\d'], $this->path);

        $this->assertSame("APP_NAME=HCMS\nMAPS_API_KEY=\"baru\\\"\\\$x\"\nAI_API_KEY=\"abc\\\\d\"\n", file_get_contents($this->path));
        $this->assertSame('baru"$x', env('MAPS_API_KEY'));
    }

    public function test_menolak_baris_baru_dan_nama_tidak_valid(): void
    {
        $this->expectException(RuntimeException::class);
        SecretSettings::writeEnv(['MAPS_API_KEY' => "a\nAPP_KEY=x"], $this->path);
    }

    public function test_nama_variabel_tidak_valid_ditolak(): void
    {
        $this->expectException(RuntimeException::class);
        SecretSettings::writeEnv(['bad name' => 'x'], $this->path);
    }

    public function test_config_berisi_env_untuk_kunci_rahasia(): void
    {
        $this->assertSame("env('AI_API_KEY', '')", SecretSettings::exportValue(SecretSettings::AI, 'ai_api_key', 'rahasia'));
        $this->assertSame("'Aisi'", SecretSettings::exportValue(SecretSettings::AI, 'ai_name', 'Aisi'));
        $this->assertStringNotContainsString('rahasia', SecretSettings::exportValue(SecretSettings::SXIMO, 'cnf_maps', 'rahasia'));
    }
}
