<?php

namespace Tests\Feature\Tenancy;

use App\Support\Tenancy\TenantContext;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TenantHttpTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenantA;

    private int $tenantB;

    private int $companyA;

    private int $companyB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = $this->makeTenant('TA');
        $this->tenantB = $this->makeTenant('TB');
        $this->companyA = $this->makeCompany($this->tenantA, 'A1');
        $this->companyB = $this->makeCompany($this->tenantB, 'B1');
    }

    public function test_middleware_mengisi_tenant_dari_akun_bukan_dari_sesi(): void
    {
        $user = $this->makeUser($this->tenantA);

        // Konteks diperiksa di dalam request (setelah respons, middleware membersihkannya)
        Route::middleware('web')->get('/__tenant-context', fn (TenantContext $c) => ['tenant' => $c->tenantId(), 'company' => $c->companyId()]);

        // cid milik tenant lain diabaikan
        $this->actingAsSximo($user)->withSession(['cid' => $this->companyB])->get('/up')->assertOk();
        $this->get('/corporate')->assertOk();
        $this->getJson('/__tenant-context')->assertOk()->assertExactJson(['tenant' => $this->tenantA, 'company' => null]);

        $this->assertNull(app(TenantContext::class)->tenantId(), 'konteks dibersihkan setelah request');
    }

    public function test_grid_company_hanya_menampilkan_tenant_sendiri(): void
    {
        $user = $this->makeUser($this->tenantA);

        $json = $this->actingAsSximo($user)
            ->post('/corporate', ['action_task' => '', 'draw' => 1, 'start' => 0, 'length' => 50])
            ->assertOk()
            ->json();

        $this->assertSame(1, (int) $json['recordsTotal']);
        $this->assertSame(['PT A1'], array_column($json['data'], 'corporate_name'));
    }

    public function test_detail_company_tenant_lain_tidak_ditemukan(): void
    {
        $user = $this->makeUser($this->tenantA);

        $this->actingAsSximo($user)->get('/corporate/'.$this->companyB)
            ->assertRedirect('corporate')
            ->assertSessionHas('message', 'Record Not Found !');
    }

    public function test_simpan_company_kode_unik_per_tenant(): void
    {
        $user = $this->makeUser($this->tenantA);
        $save = fn (array $data) => $this->actingAsSximo($user)->post('/corporate', $data + ['action_task' => 'save'])->json();

        // Kode B1 dipakai tenant lain: tetap boleh di tenant A
        $this->assertSame('success', $save(['code' => 'B1', 'corporate_name' => 'PT Kembar'])['status']);
        $this->assertSame($this->tenantA, (int) DB::table('corporate')->where('corporate_name', 'PT Kembar')->value('tenant_id'));

        // Kode A1 sudah dipakai di tenant A sendiri: ditolak
        $this->assertSame('error', $save(['code' => 'A1', 'corporate_name' => 'PT Duplikat'])['status']);
    }

    public function test_ubah_company_tenant_lain_lewat_form_ditolak(): void
    {
        $user = $this->makeUser($this->tenantA);

        $this->actingAsSximo($user)
            ->post('/corporate', ['action_task' => 'save', 'id_corporate' => $this->companyB, 'code' => 'B1', 'corporate_name' => 'DIBAJAK'])
            ->assertNotFound();

        $this->assertSame('PT B1', DB::table('corporate')->where('id_corporate', $this->companyB)->value('corporate_name'));
    }

    public function test_tenant_ditangguhkan_membuat_pengguna_logout(): void
    {
        $suspended = $this->makeTenant('TS', 'suspended');
        $user = $this->makeUser($suspended);

        $this->actingAsSximo($user)->get('/dashboard')->assertRedirect('user/login');
        $this->assertGuest();
    }

    public function test_halaman_tenant_hanya_untuk_superadmin(): void
    {
        $admin = $this->makeUser($this->tenantA, 2);
        $this->actingAsSximo($admin, 2)->get('/core/tenants')->assertRedirect('dashboard');

        $root = $this->makeUser($this->tenantA, 1);
        $this->actingAsSximo($root, 1)->get('/core/tenants')
            ->assertOk()
            ->assertSee('TA')
            ->assertSee('TB');
    }

    public function test_superadmin_membuat_tenant_beserta_company_dan_admin(): void
    {
        $root = $this->makeUser($this->tenantA, 1);

        $this->actingAsSximo($root, 1)->post('/core/tenants', [
            'code' => 'baru',
            'name' => 'Grup Baru',
            'license_type' => 'saas',
            'company_code' => 'GB',
            'company_name' => 'PT Grup Baru',
            'admin_first_name' => 'Ani',
            'admin_last_name' => 'Admin',
            'admin_username' => 'ani.admin',
            'admin_email' => 'ani@grupbaru.test',
            'admin_password' => 'rahasia123',
            'admin_password_confirmation' => 'rahasia123',
        ])->assertRedirect('core/tenants')->assertSessionHas('status', 'success');

        $tenant = DB::table('tenants')->where('code', 'BARU')->first();
        $this->assertNotNull($tenant);
        $this->assertSame('active', $tenant->status);

        $company = DB::table('corporate')->where('code', 'GB')->first();
        $this->assertSame($tenant->id, (int) $company->tenant_id);
        $this->assertNotEmpty($company->uuid);

        $admin = User::where('email', 'ani@grupbaru.test')->firstOrFail();
        $this->assertSame($tenant->id, (int) $admin->tenant_id);
        $this->assertSame(2, (int) DB::table('tb_users_groups')->where('user_id', $admin->id)->value('group_id'));

        // Admin tenant baru hanya melihat company-nya sendiri
        $json = $this->actingAsSximo($admin, 2)->post('/corporate', ['action_task' => '', 'draw' => 1, 'start' => 0, 'length' => 50])->json();
        $this->assertSame(1, (int) $json['recordsTotal']);
        $this->assertSame(['PT Grup Baru'], array_column($json['data'], 'corporate_name'));
    }

    public function test_superadmin_tidak_bisa_menangguhkan_tenant_sendiri(): void
    {
        $root = $this->makeUser($this->tenantA, 1);
        $uuid = DB::table('tenants')->where('id', $this->tenantA)->value('uuid');

        $this->actingAsSximo($root, 1)->post('/core/tenants/'.$uuid.'/status', ['status' => 'suspended'])
            ->assertRedirect('core/tenants')->assertSessionHas('status', 'error');
        $this->assertSame('active', DB::table('tenants')->where('id', $this->tenantA)->value('status'));

        $uuidB = DB::table('tenants')->where('id', $this->tenantB)->value('uuid');
        $this->post('/core/tenants/'.$uuidB.'/status', ['status' => 'suspended'])->assertSessionHas('status', 'success');
        $this->assertSame('suspended', DB::table('tenants')->where('id', $this->tenantB)->value('status'));
    }

    public function test_hapus_pengguna_tenant_lain_tidak_berpengaruh(): void
    {
        $admin = $this->makeUser($this->tenantA, 1);
        $victim = $this->makeUser($this->tenantB, 3);

        $this->actingAsSximo($admin, 1)->post('/core/users/deluser', ['user_id' => $victim->id]);

        $this->assertTrue(DB::table('tb_users')->where('id', $victim->id)->exists());
    }
}
