<?php

namespace Tests\Concerns;

use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use App\User;
use Database\Seeders\TestCoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Database uji (hcms_test) + pembuat data tenant/company/pengguna.
 */
trait InteractsWithTenants
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestCoreSeeder::class;

    protected function setUpInteractsWithTenants(): void
    {
        TenantScope::flushCache();
    }

    protected function makeTenant(string $code, string $status = 'active'): int
    {
        return DB::table('tenants')->insertGetId([
            'uuid' => (string) Str::uuid7(),
            'code' => $code,
            'name' => 'Tenant '.$code,
            'status' => $status,
            'license_type' => 'saas',
            'data_region' => 'ID',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function makeCompany(int $tenantId, string $code, ?string $name = null): int
    {
        return DB::table('corporate')->insertGetId([
            'tenant_id' => $tenantId,
            'uuid' => (string) Str::uuid7(),
            'code' => $code,
            'slug_corporate' => Str::slug($code),
            'corporate_name' => $name ?? 'PT '.$code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Unit organisasi; company = $companyId atau company pertama tenant. */
    protected function makeUnit(int $tenantId, string $name, ?int $parent = null, ?int $companyId = null): int
    {
        $categoryId = DB::table('unit_category')->insertGetId(['tenant_id' => $tenantId, 'slug_unit_category' => Str::random(8), 'unit_category' => 'Kat', 'unit_category_code' => 'K', 'unit_category_explan' => '']);
        $typeId = DB::table('unit_type')->insertGetId(['tenant_id' => $tenantId, 'unit_type' => 'Tipe', 'keterangan' => '', 'status' => 1]);
        $companyId ??= DB::table('corporate')->where('tenant_id', $tenantId)->value('id_corporate') ?? $this->makeCompany($tenantId, 'C'.$tenantId);

        return DB::table('unit')->insertGetId([
            'tenant_id' => $tenantId, 'uuid' => (string) Str::uuid7(), 'slug_unit' => Str::slug($name).'-'.Str::random(4),
            'parent_id' => $parent, 'unit_name' => $name, 'unit_code' => strtoupper(Str::random(4)),
            'id_unit_category' => $categoryId, 'id_corporate' => $companyId, 'unit_addres' => '', 'unit_explan' => '',
            'id_unit_type' => $typeId, 'status' => 1,
        ]);
    }

    /** Beri grup hak tertentu pada modul (baris tb_groups_access). */
    protected function grantModule(string $module, int $groupId, array $tasks): void
    {
        $moduleId = DB::table('tb_module')->where('module_name', $module)->value('module_id');
        DB::table('tb_groups_access')->updateOrInsert(
            ['group_id' => $groupId, 'module_id' => $moduleId],
            ['access_data' => json_encode(array_fill_keys($tasks, '1'))]
        );
    }

    protected function makeUser(?int $tenantId, int $groupId = 2, ?string $username = null): User
    {
        $username ??= 'user'.Str::lower(Str::random(6));
        $id = DB::table('tb_users')->insertGetId([
            'tenant_id' => $tenantId,
            'username' => $username,
            'email' => $username.'@example.test',
            'first_name' => 'Uji',
            'last_name' => $username,
            'password' => bcrypt('rahasia123'),
            'avatar' => '',
            'active' => 1,
            'created_at' => now(),
        ]);
        DB::table('tb_users_groups')->insert(['user_id' => $id, 'group_id' => $groupId]);

        return User::findOrFail($id);
    }

    /**
     * Login sebagai pengguna dengan sesi Sximo (gid/uid) yang biasa diisi saat sign-in.
     * MFA dianggap sudah lolos kecuali $mfaPassed = false (tes MFA).
     */
    protected function actingAsSximo(User $user, int $groupId = 2, bool $mfaPassed = true): static
    {
        return $this->actingAs($user)->withSession([
            'mfa_passed' => $mfaPassed,
            'gid' => $groupId,
            'uid' => $user->id,
            'eid' => $user->email,
            'fid' => $user->first_name.' '.$user->last_name,
            'username' => $user->username,
            'level' => $groupId,
            'lang' => 'en',
        ]);
    }

    /** Jalankan kode non-HTTP sebagai tenant tertentu. */
    protected function asTenant(int $tenantId, callable $callback): mixed
    {
        $context = app(TenantContext::class);
        $context->set($tenantId);

        try {
            return $callback();
        } finally {
            $context->clear();
        }
    }
}
