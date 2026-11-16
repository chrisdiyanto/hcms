<?php

namespace Tests\Unit\Tenancy;

use App\Support\Tenancy\Exceptions\MissingTenantContext;
use App\Support\Tenancy\TenantContext;
use PHPUnit\Framework\TestCase;

class TenantContextTest extends TestCase
{
    public function test_kosong_secara_default_dan_require_gagal(): void
    {
        $ctx = new TenantContext;

        $this->assertFalse($ctx->has());
        $this->expectException(MissingTenantContext::class);
        $ctx->requireTenantId();
    }

    public function test_set_dan_clear(): void
    {
        $ctx = new TenantContext;
        $ctx->set(7, 3);

        $this->assertSame(7, $ctx->requireTenantId());
        $this->assertSame(3, $ctx->companyId());

        $ctx->clear();
        $this->assertFalse($ctx->has());
        $this->assertNull($ctx->companyId());
    }

    public function test_run_as_mengembalikan_konteks_semula_walau_gagal(): void
    {
        $ctx = new TenantContext;
        $ctx->set(1, 10);

        $this->assertSame(2, $ctx->runAs(2, 20, fn () => $ctx->tenantId()));
        $this->assertSame([1, 10], [$ctx->tenantId(), $ctx->companyId()]);

        try {
            $ctx->runAs(3, null, fn () => throw new \RuntimeException('x'));
        } catch (\RuntimeException) {
        }
        $this->assertSame([1, 10], [$ctx->tenantId(), $ctx->companyId()]);
    }
}
