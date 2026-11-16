<?php

namespace App\Domain\Tenancy\Actions;

use App\Models\Tenant;
use App\Support\Security\Roles;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Onboarding tenant baru dalam satu transaksi:
 * tenant + company pertama + akun Tenant Administrator pertama.
 *
 * Company dan akun dibuat di dalam konteks tenant baru (runAs), sehingga
 * tenant_id selalu diisi oleh TenantScope, bukan oleh input.
 */
final class CreateTenant
{
    public function __construct(private TenantContext $context) {}

    /**
     * @param  array{code:string,name:string,legal_name?:?string,license_type:string,max_employees?:?int,contact_name?:?string,contact_email?:?string,contact_phone?:?string}  $tenant
     * @param  array{code:string,name:string,address?:?string}  $company
     * @param  array{first_name:string,last_name:string,username:string,email:string,password:string}  $admin
     */
    public function handle(array $tenant, array $company, array $admin, ?int $actorId = null): Tenant
    {
        return DB::transaction(function () use ($tenant, $company, $admin, $actorId) {
            $model = Tenant::create($tenant + [
                'status' => 'active',
                'data_region' => 'ID',
                'activated_at' => now(),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->context->runAs($model->id, null, function () use ($company, $admin) {
                $now = now();

                DB::table('corporate')->insert(TenantScope::fillForInsert('corporate', [
                    'code' => $company['code'],
                    'corporate_name' => $company['name'],
                    'slug_corporate' => Str::limit(Str::slug($company['name']), 90, '') ?: 'company',
                    'corporate_address' => $company['address'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));

                $userId = DB::table('tb_users')->insertGetId(TenantScope::fillForInsert('tb_users', [
                    'username' => $admin['username'],
                    'email' => $admin['email'],
                    'first_name' => $admin['first_name'],
                    'last_name' => $admin['last_name'],
                    'password' => Hash::make($admin['password']),
                    'avatar' => '',
                    'active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));

                DB::table('tb_users_groups')->insert([
                    'user_id' => $userId,
                    'group_id' => self::tenantAdminGroupId(),
                ]);
            });

            return $model;
        });
    }

    public static function tenantAdminGroupId(): int
    {
        return Roles::id(Roles::TENANT_ADMIN);
    }
}
