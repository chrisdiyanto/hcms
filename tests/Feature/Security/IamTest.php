<?php

namespace Tests\Feature\Security;

use App\Domain\Organization\Services\OrgUnitClosure;
use App\Support\Security\DataScope;
use App\Support\Security\Roles;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class IamTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $company;

    /** @var array<string,int> */
    private array $units = [];

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();

        $this->tenant = $this->makeTenant('TA');
        $this->company = $this->makeCompany($this->tenant, 'A1');

        // Direktorat > Divisi > Seksi, plus Divisi Lain (akar terpisah)
        $this->units['dir'] = $this->makeUnit($this->tenant, 'Direktorat');
        $this->units['div'] = $this->makeUnit($this->tenant, 'Divisi', $this->units['dir']);
        $this->units['sek'] = $this->makeUnit($this->tenant, 'Seksi', $this->units['div']);
        $this->units['lain'] = $this->makeUnit($this->tenant, 'Divisi Lain');
        OrgUnitClosure::rebuild($this->tenant);
    }

    public function test_lima_belas_peran_blueprint_tersedia_dengan_akses_default(): void
    {
        $this->assertSame(15, DB::table('tb_groups')->whereNotNull('code')->count());
        $this->assertSame(1, Roles::id(Roles::SUPER_ADMIN));
        $this->assertSame(2, Roles::id(Roles::TENANT_ADMIN));
        $this->assertSame(3, Roles::id(Roles::EMPLOYEE));

        $expected = DB::table('tb_groups')->count() * DB::table('tb_module')->count();
        $this->assertSame($expected, DB::table('tb_groups_access')->count());
    }

    public function test_closure_table_berisi_seluruh_bawahan(): void
    {
        $this->assertEqualsCanonicalizing(
            [$this->units['dir'], $this->units['div'], $this->units['sek']],
            OrgUnitClosure::descendantsOf([$this->units['dir']])
        );
        $this->assertSame(2, (int) DB::table('org_unit_closure')->where('ancestor_id', $this->units['dir'])->where('descendant_id', $this->units['sek'])->value('depth'));
        $this->assertTrue(OrgUnitClosure::isSelfOrDescendant($this->units['div'], $this->units['sek']));
        $this->assertFalse(OrgUnitClosure::isSelfOrDescendant($this->units['div'], $this->units['dir']));
    }

    public function test_closure_tahan_terhadap_siklus_data_lama(): void
    {
        DB::table('unit')->where('id_unit', $this->units['dir'])->update(['parent_id' => $this->units['sek']]);

        OrgUnitClosure::rebuild($this->tenant);

        $this->assertGreaterThan(0, DB::table('org_unit_closure')->where('tenant_id', $this->tenant)->count());
    }

    public function test_data_scope_scoped_memakai_penugasan_dan_cakupan_tambahan(): void
    {
        $hr = $this->makeUser($this->tenant, Roles::id(Roles::HR_ADMIN));
        DB::table('user_unit')->insert(['id_user' => $hr->id, 'id_unit' => $this->units['div'], 'start_date' => now()]);
        DB::table('user_data_scopes')->insert([
            ['tenant_id' => $this->tenant, 'user_id' => $hr->id, 'unit_id' => $this->units['lain'], 'include_children' => 0, 'valid_from' => null, 'valid_to' => null],
            // Kedaluwarsa: diabaikan
            ['tenant_id' => $this->tenant, 'user_id' => $hr->id, 'unit_id' => $this->units['dir'], 'include_children' => 1, 'valid_from' => null, 'valid_to' => now()->subDay()->toDateString()],
        ]);

        $scope = $this->asTenant($this->tenant, fn () => DataScope::for($hr->id, Roles::id(Roles::HR_ADMIN)));

        $this->assertSame(DataScope::SCOPED, $scope->mode);
        $this->assertEqualsCanonicalizing([$this->units['div'], $this->units['sek'], $this->units['lain']], $scope->unitIds);
        $this->assertFalse($scope->allowsUnit($this->units['dir']));
    }

    public function test_data_scope_all_dan_own(): void
    {
        $admin = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $employee = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE));

        $this->asTenant($this->tenant, function () use ($admin, $employee) {
            $this->assertTrue(DataScope::for($admin->id, Roles::id(Roles::TENANT_ADMIN))->isAll());

            $own = DataScope::for($employee->id, Roles::id(Roles::EMPLOYEE));
            $this->assertSame(DataScope::OWN, $own->mode);

            // Pegawai hanya melihat baris miliknya sendiri
            $ids = $own->apply(DB::table('tb_users'), 'unit_id_tidak_dipakai', 'tb_users.id')->pluck('id')->all();
            $this->assertSame([$employee->id], array_map('intval', $ids));
            $this->assertStringContainsString('1 = 0', $own->sql('unit.id_unit'));
        });
    }

    public function test_halaman_cakupan_data_menambah_dan_mencabut(): void
    {
        $admin = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $this->grantUsersModule(Roles::id(Roles::TENANT_ADMIN));
        $target = $this->makeUser($this->tenant, Roles::id(Roles::HRBP));

        $this->actingAsSximo($admin, Roles::id(Roles::TENANT_ADMIN))
            ->post('/core/users/'.$target->id.'/scopes', ['unit_id' => $this->units['div'], 'include_children' => 1, 'reason' => 'HRBP Divisi'])
            ->assertRedirect('core/users/'.$target->id.'/scopes');

        $grant = DB::table('user_data_scopes')->where('user_id', $target->id)->first();
        $this->assertSame($this->tenant, (int) $grant->tenant_id);
        $this->assertSame('HRBP Divisi', $grant->reason);

        $this->get('/core/users/'.$target->id.'/scopes')->assertOk()->assertSee('Divisi')->assertSee('HRBP Divisi');

        $this->delete('/core/users/'.$target->id.'/scopes/'.$grant->id)->assertRedirect();
        $this->assertFalse(DB::table('user_data_scopes')->where('id', $grant->id)->exists());
    }

    public function test_cakupan_data_menolak_diri_sendiri_tenant_lain_dan_tanpa_izin(): void
    {
        $adminGroup = Roles::id(Roles::TENANT_ADMIN);
        $this->grantUsersModule($adminGroup);
        $admin = $this->makeUser($this->tenant, $adminGroup);

        // Diri sendiri (SoD)
        $this->actingAsSximo($admin, $adminGroup)
            ->post('/core/users/'.$admin->id.'/scopes', ['unit_id' => $this->units['dir'], 'reason' => 'x'])
            ->assertSessionHas('status', 'error');
        $this->assertSame(0, DB::table('user_data_scopes')->count());

        // Pengguna & unit tenant lain
        $other = $this->makeTenant('TB');
        $otherUser = $this->makeUser($other, Roles::id(Roles::HRBP));
        $otherUnit = $this->makeUnit($other, 'Unit B');
        $this->post('/core/users/'.$otherUser->id.'/scopes', ['unit_id' => $this->units['dir'], 'reason' => 'x'])->assertNotFound();

        $target = $this->makeUser($this->tenant, Roles::id(Roles::HRBP));
        $this->post('/core/users/'.$target->id.'/scopes', ['unit_id' => $otherUnit, 'reason' => 'x'])->assertSessionHasErrors('unit_id');

        // Peran tanpa izin edit Users
        $employee = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE));
        $this->actingAsSximo($employee, Roles::id(Roles::EMPLOYEE))
            ->get('/core/users/'.$target->id.'/scopes')->assertRedirect('dashboard');
    }

    public function test_riwayat_login_sukses_gagal_dan_login_lewat_username(): void
    {
        $user = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'budi.santoso');

        $this->post('/user/signin', ['email' => 'budi.santoso', 'password' => 'salah-sandi']);
        $this->post('/user/signin', ['email' => 'budi.santoso', 'password' => 'rahasia123']);

        $events = DB::table('login_histories')->where('user_id', $user->id)->orderBy('id')->pluck('event')->all();
        $this->assertSame(['failed', 'login'], $events);
        $this->assertSame($this->tenant, (int) DB::table('login_histories')->where('event', 'login')->value('tenant_id'));
        $this->assertSame(0, DB::table('login_histories')->where('login', 'like', '%rahasia%')->count());
    }

    public function test_parent_unit_tidak_boleh_membentuk_siklus(): void
    {
        $adminGroup = Roles::id(Roles::TENANT_ADMIN);
        $moduleId = DB::table('tb_module')->where('module_name', 'unit')->value('module_id');
        DB::table('tb_groups_access')->where('group_id', $adminGroup)->where('module_id', $moduleId)
            ->update(['access_data' => json_encode(['is_global' => '1', 'is_view' => '1', 'is_add' => '1', 'is_edit' => '1', 'is_detail' => '1'])]);
        $admin = $this->makeUser($this->tenant, $adminGroup);
        $dir = DB::table('unit')->where('id_unit', $this->units['dir'])->first();

        $response = $this->actingAsSximo($admin, $adminGroup)->post('/unit', [
            'action_task' => 'save', 'id_unit' => $dir->id_unit, 'parent_id' => $this->units['sek'],
            'unit_name' => $dir->unit_name, 'unit_code' => $dir->unit_code, 'slug_unit' => $dir->slug_unit,
            'id_corporate' => $dir->id_corporate, 'id_unit_category' => $dir->id_unit_category, 'id_unit_type' => $dir->id_unit_type,
            'unit_addres' => '-', 'unit_explan' => '-', 'status' => 1,
        ])->json();

        $this->assertSame('error', $response['status']);
        $this->assertStringContainsString('Parent unit tidak boleh', $response['message']);
        $this->assertNull(DB::table('unit')->where('id_unit', $this->units['dir'])->value('parent_id'));
    }

    private function grantUsersModule(int $groupId): void
    {
        $moduleId = DB::table('tb_module')->where('module_name', 'users')->value('module_id');
        DB::table('tb_groups_access')->where('group_id', $groupId)->where('module_id', $moduleId)
            ->update(['access_data' => json_encode(['is_global' => '1', 'is_view' => '1', 'is_detail' => '1', 'is_add' => '1', 'is_edit' => '1', 'is_remove' => '1'])]);
    }
}
