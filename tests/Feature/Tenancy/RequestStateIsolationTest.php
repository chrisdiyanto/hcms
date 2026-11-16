<?php

namespace Tests\Feature\Tenancy;

use App\Support\Security\Roles;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Review Octane (Fase 4): satu aplikasi melayani banyak request berurutan dan instance
 * controller disimpan di objek Route. State tenant, hak akses, dan data scope tidak boleh
 * terbawa dari request sebelumnya.
 */
class RequestStateIsolationTest extends TestCase
{
    use InteractsWithTenants;

    public function test_request_berurutan_beda_tenant_dan_peran_tidak_saling_bocor(): void
    {
        Roles::flushCache();
        $tenants = [];
        foreach (['TA', 'TB'] as $code) {
            $id = $this->makeTenant($code);
            DB::table('performance_periods')->insert(['tenant_id' => $id, 'uuid' => (string) Str::uuid7(), 'code' => 'KIN-'.$code,
                'name' => 'Periode milik '.$code, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'draft', 'created_at' => now()]);
            $tenants[$code] = $id;
        }
        $adminA = $this->makeUser($tenants['TA'], Roles::id(Roles::TENANT_ADMIN));
        $adminB = $this->makeUser($tenants['TB'], Roles::id(Roles::TENANT_ADMIN));
        $employeeA = $this->makeUser($tenants['TA'], Roles::id(Roles::EMPLOYEE));

        $controllers = [];
        foreach ([[$adminA, 'TA', 'TB', true], [$adminB, 'TB', 'TA', true], [$employeeA, 'TA', 'TB', false], [$adminA, 'TA', 'TB', true]] as [$user, $own, $other, $allowed]) {
            $group = $allowed ? Roles::id(Roles::TENANT_ADMIN) : Roles::id(Roles::EMPLOYEE);
            $response = $this->actingAsSximo($user, $group)->get('/performance/periods');
            if ($allowed) {
                $response->assertOk()->assertSee('Periode milik '.$own)->assertDontSee('Periode milik '.$other);
            } else {
                // Hak akses admin dari request sebelumnya tidak terbawa ke pegawai
                $response->assertRedirect('dashboard');
            }
            $controllers[] = spl_object_id(Route::getRoutes()->match(request()->create('/performance/periods'))->getController());
            // Konteks tenant (binding scoped) tidak tersisa di luar request
            $this->assertNull(app(TenantContext::class)->tenantId());
        }

        // Instance controller memang dipakai ulang antar-request (seperti di Octane)
        $this->assertCount(1, array_unique($controllers));
    }
}
