<?php

namespace Tests\Feature;

use App\Support\Tenancy\TenantContext;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_health_check_ok(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_halaman_backend_mengarahkan_tamu_ke_login(): void
    {
        $this->get('/corporate')->assertRedirect(url('user/login'));
    }

    public function test_tenant_context_terdaftar_sebagai_scoped(): void
    {
        $a = $this->app->make(TenantContext::class);
        $a->set(5);
        $this->assertSame($a, $this->app->make(TenantContext::class));

        // forgetScopedInstances() dipanggil Octane/queue di antara request/job
        $this->app->forgetScopedInstances();
        $this->assertFalse($this->app->make(TenantContext::class)->has());
    }
}
