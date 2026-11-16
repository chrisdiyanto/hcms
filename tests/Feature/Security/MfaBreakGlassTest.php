<?php

namespace Tests\Feature\Security;

use App\Domain\Security\Services\BreakGlass;
use App\Domain\Security\Services\Mfa;
use App\Support\Security\Roles;
use App\User;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class MfaBreakGlassTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenantA;

    private int $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->tenantA = $this->makeTenant('TA');
        $this->tenantB = $this->makeTenant('TB');
        $this->makeCompany($this->tenantA, 'A1');
        $this->makeCompany($this->tenantB, 'B1');
    }

    private function enableMfa(int $userId): string
    {
        $secret = (new Google2FA)->generateSecretKey(32);
        app(Mfa::class)->enable($userId, $secret);

        return $secret;
    }

    private function otp(string $secret): string
    {
        return (new Google2FA)->getCurrentOtp($secret);
    }

    // ---------------------------------------------------------------- MFA

    public function test_peran_wajib_mfa_diarahkan_ke_pendaftaran(): void
    {
        $admin = $this->makeUser($this->tenantA, Roles::id(Roles::TENANT_ADMIN));

        $this->actingAsSximo($admin, Roles::id(Roles::TENANT_ADMIN), mfaPassed: false)
            ->get('/corporate')->assertRedirect('user/mfa/setup');

        $this->getJson('/corporate')->assertStatus(403)->assertJsonPath('redirect', url('user/mfa/setup'));
    }

    public function test_peran_tanpa_kewajiban_mfa_langsung_lolos(): void
    {
        $employee = $this->makeUser($this->tenantA, Roles::id(Roles::EMPLOYEE));

        $response = $this->actingAsSximo($employee, Roles::id(Roles::EMPLOYEE), mfaPassed: false)->get('/core/tenants');

        $this->assertStringNotContainsString('user/mfa', (string) $response->headers->get('Location'));
        $this->assertTrue(session(Mfa::SESSION_PASSED));
    }

    public function test_pendaftaran_mfa_menyimpan_secret_terenkripsi_dan_kode_pemulihan(): void
    {
        $admin = $this->makeUser($this->tenantA, Roles::id(Roles::TENANT_ADMIN));

        $this->actingAsSximo($admin, Roles::id(Roles::TENANT_ADMIN), mfaPassed: false)
            ->get('/user/mfa/setup')->assertOk()->assertSee('<svg', false);
        $secret = session('mfa_pending_secret');
        $this->assertNotEmpty($secret);

        $this->post('/user/mfa/setup', ['code' => '000000'])->assertSessionHasErrors('code');

        $response = $this->post('/user/mfa/setup', ['code' => $this->otp($secret)])->assertOk()->assertSee('Simpan kode pemulihan');
        $this->assertSame(Mfa::RECOVERY_CODE_COUNT, substr_count($response->getContent(), '<code class="d-block'));

        $row = DB::table('tb_users')->where('id', $admin->id)->first();
        $this->assertNotNull($row->mfa_enabled_at);
        $this->assertNotSame($secret, $row->mfa_secret);
        $this->assertTrue(session(Mfa::SESSION_PASSED));
        $this->get('/corporate')->assertOk();
    }

    public function test_tantangan_mfa_menolak_kode_salah_dan_pemakaian_ulang(): void
    {
        $admin = $this->makeUser($this->tenantA, Roles::id(Roles::TENANT_ADMIN));
        $secret = $this->enableMfa($admin->id);
        $code = $this->otp($secret);

        $this->actingAsSximo($admin, Roles::id(Roles::TENANT_ADMIN), mfaPassed: false)
            ->get('/corporate')->assertRedirect('user/mfa');

        $this->post('/user/mfa', ['code' => $code === '123456' ? '654321' : '123456'])->assertSessionHasErrors('code');
        $this->post('/user/mfa', ['code' => $code])->assertRedirect();
        $this->assertTrue(session(Mfa::SESSION_PASSED));

        // Kode yang sama tidak bisa dipakai lagi (sesi baru)
        $this->flushSession();
        $this->actingAsSximo($admin, Roles::id(Roles::TENANT_ADMIN), mfaPassed: false)
            ->post('/user/mfa', ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_kode_pemulihan_hanya_sekali_pakai(): void
    {
        $admin = $this->makeUser($this->tenantA, Roles::id(Roles::TENANT_ADMIN));
        $codes = app(Mfa::class)->enable($admin->id, (new Google2FA)->generateSecretKey(32));

        $this->assertTrue(app(Mfa::class)->verify($admin->id, strtolower($codes[0])));
        $this->assertFalse(app(Mfa::class)->verify($admin->id, $codes[0]));
        $this->assertSame(Mfa::RECOVERY_CODE_COUNT - 1, app(Mfa::class)->remainingRecoveryCodes($admin->id));
    }

    public function test_login_ulang_menghapus_status_mfa_di_sesi(): void
    {
        $user = $this->makeUser($this->tenantA, Roles::id(Roles::EMPLOYEE), 'siti.rahma');

        $this->withSession([Mfa::SESSION_PASSED => true])
            ->post('/user/signin', ['email' => 'siti.rahma', 'password' => 'rahasia123']);

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session(Mfa::SESSION_PASSED));
    }

    public function test_reset_mfa_oleh_admin_dengan_batas_hierarki(): void
    {
        $adminGroup = Roles::id(Roles::TENANT_ADMIN);
        $moduleId = DB::table('tb_module')->where('module_name', 'users')->value('module_id');
        DB::table('tb_groups_access')->where('module_id', $moduleId)->whereIn('group_id', [$adminGroup, Roles::id(Roles::SUPER_ADMIN)])
            ->update(['access_data' => json_encode(['is_view' => '1', 'is_edit' => '1'])]);

        $admin = $this->makeUser($this->tenantA, $adminGroup);
        $hrbp = $this->makeUser($this->tenantA, Roles::id(Roles::HRBP));
        $peer = $this->makeUser($this->tenantA, $adminGroup);
        $this->enableMfa($hrbp->id);
        $this->enableMfa($peer->id);

        $this->actingAsSximo($admin, $adminGroup)->post('/core/users/'.$hrbp->id.'/mfa-reset')->assertSessionHas('status', 'success');
        $this->assertNull(DB::table('tb_users')->where('id', $hrbp->id)->value('mfa_enabled_at'));

        $this->post('/core/users/'.$peer->id.'/mfa-reset')->assertSessionHas('status', 'error');
        $this->assertNotNull(DB::table('tb_users')->where('id', $peer->id)->value('mfa_enabled_at'));

        $this->post('/core/users/'.$admin->id.'/mfa-reset')->assertSessionHas('status', 'error');
    }

    // --------------------------------------------------------- Break-glass

    private function superAdmin(?int $tenantId): User
    {
        return $this->makeUser($tenantId, Roles::id(Roles::SUPER_ADMIN));
    }

    private function startBreakGlass(User $root, int $tenantId, array $overrides = [])
    {
        $uuid = DB::table('tenants')->where('id', $tenantId)->value('uuid');

        return $this->actingAsSximo($root, Roles::id(Roles::SUPER_ADMIN))
            ->post('/core/tenants/'.$uuid.'/break-glass', $overrides + [
                'reason' => 'Tiket dukungan #42: data pegawai tidak tampil',
                'minutes' => 15,
                'confirm' => 1,
            ]);
    }

    public function test_break_glass_membuka_data_tenant_tujuan_mencatat_dan_memberi_notifikasi(): void
    {
        $tenantAdminB = $this->makeUser($this->tenantB, Roles::id(Roles::TENANT_ADMIN));
        $root = $this->superAdmin($this->tenantA);

        $this->startBreakGlass($root, $this->tenantB)->assertRedirect('dashboard');
        $access = DB::table('break_glass_accesses')->first();
        $this->assertSame($root->id, (int) $access->user_id);
        $this->assertSame($this->tenantB, (int) $access->tenant_id);
        $this->assertSame(1, DB::table('tb_notification')->where('userid', $tenantAdminB->id)->count());

        $json = $this->post('/corporate', ['action_task' => '', 'draw' => 1, 'start' => 0, 'length' => 50])->json();
        $this->assertSame(['PT B1'], array_column($json['data'], 'corporate_name'));

        $this->get('/core/tenants')->assertSee('Mode break-glass');
        $this->assertGreaterThan(0, DB::table('tb_logs')->where('module', 'breakglass')->count());
        $this->assertGreaterThan(0, (int) DB::table('break_glass_accesses')->value('request_count'));

        $this->post('/core/break-glass/end')->assertRedirect('core/tenants');
        $this->assertNotNull(DB::table('break_glass_accesses')->value('ended_at'));
        $json = $this->post('/corporate', ['action_task' => '', 'draw' => 1, 'start' => 0, 'length' => 50])->json();
        $this->assertSame(['PT A1'], array_column($json['data'], 'corporate_name'));
    }

    public function test_break_glass_ditolak_tanpa_alasan_cukup_untuk_tenant_sendiri_dan_bukan_superadmin(): void
    {
        $root = $this->superAdmin($this->tenantA);

        $this->startBreakGlass($root, $this->tenantB, ['reason' => 'terlalu pendek'])->assertSessionHasErrors('reason');
        $this->startBreakGlass($root, $this->tenantA)->assertSessionHas('status', 'error');

        $admin = $this->makeUser($this->tenantA, Roles::id(Roles::TENANT_ADMIN));
        $uuid = DB::table('tenants')->where('id', $this->tenantB)->value('uuid');
        $this->actingAsSximo($admin, Roles::id(Roles::TENANT_ADMIN))
            ->post('/core/tenants/'.$uuid.'/break-glass', ['reason' => str_repeat('x', 30), 'minutes' => 15, 'confirm' => 1])
            ->assertRedirect('dashboard');

        $this->assertSame(0, DB::table('break_glass_accesses')->count());
    }

    public function test_break_glass_kedaluwarsa_kembali_ke_tenant_sendiri(): void
    {
        $root = $this->superAdmin($this->tenantA);
        $this->startBreakGlass($root, $this->tenantB);
        DB::table('break_glass_accesses')->update(['expires_at' => now()->subMinute()]);

        $json = $this->post('/corporate', ['action_task' => '', 'draw' => 1, 'start' => 0, 'length' => 50])->json();

        $this->assertSame(['PT A1'], array_column($json['data'], 'corporate_name'));
        $this->assertNull(session(BreakGlass::SESSION_KEY));
    }

    public function test_akun_platform_tanpa_tenant_butuh_break_glass(): void
    {
        $root = $this->superAdmin(null);

        $this->actingAsSximo($root, Roles::id(Roles::SUPER_ADMIN))->get('/corporate/1')->assertRedirect('core/tenants');
        $this->postJson('/corporate', ['action_task' => '', 'draw' => 1, 'start' => 0, 'length' => 50])->assertStatus(403);
        $this->get('/core/tenants')->assertOk()->assertSee('TB');

        $this->startBreakGlass($root, $this->tenantB);
        $json = $this->post('/corporate', ['action_task' => '', 'draw' => 1, 'start' => 0, 'length' => 50])->json();
        $this->assertSame(['PT B1'], array_column($json['data'], 'corporate_name'));
    }
}
