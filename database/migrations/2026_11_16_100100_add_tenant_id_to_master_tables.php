<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Tambah tenant_id ke tabel master yang sudah ada dan isi dengan tenant DEFAULT.
 *
 * - corporate (= company), unit, unit_type, unit_category: tenant_id wajib.
 * - tb_users: tenant_id nullable (null = akun platform tanpa akses data tenant).
 * - Unique index kode/slug menjadi per tenant (blueprint 17.4).
 * - corporate & unit mendapat kolom uuid; corporate mendapat kolom settings.
 *
 * user_unit dan tb_notification belum diberi tenant_id: barisnya selalu diakses
 * lewat id pengguna yang sudah terikat ke satu tenant.
 */
return new class extends Migration
{
    private array $required = ['corporate', 'unit', 'unit_type', 'unit_category'];

    public function up(): void
    {
        $tenantId = DB::table('tenants')->where('code', 'DEFAULT')->value('id');

        foreach (array_merge($this->required, ['tb_users']) as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->first();
            });
            DB::table($name)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        }

        foreach ($this->required as $name) {
            DB::statement("ALTER TABLE `{$name}` MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL");
        }

        foreach (array_merge($this->required, ['tb_users']) as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                // index dulu, supaya MySQL tidak membuat index ganda untuk foreign key
                $table->index('tenant_id', "{$name}_tenant_id_index");
                $table->foreign('tenant_id', "{$name}_tenant_id_foreign")->references('id')->on('tenants');
            });
        }

        // uuid untuk ID publik
        foreach (['corporate' => 'id_corporate', 'unit' => 'id_unit'] as $name => $key) {
            Schema::table($name, function (Blueprint $table) use ($key) {
                $table->uuid('uuid')->nullable()->after($key);
            });
            foreach (DB::table($name)->whereNull('uuid')->pluck($key) as $id) {
                DB::table($name)->where($key, $id)->update(['uuid' => (string) Str::uuid7()]);
            }
            DB::statement("ALTER TABLE `{$name}` MODIFY `uuid` CHAR(36) NOT NULL");
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->unique('uuid', "{$name}_uuid_unique");
            });
        }

        Schema::table('corporate', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('corporate_address');
            $table->dropUnique('corporate_code_unique');
            $table->dropUnique('corporate_slug_corporate_unique');
            $table->unique(['tenant_id', 'code'], 'corporate_tenant_code_unique');
            $table->unique(['tenant_id', 'slug_corporate'], 'corporate_tenant_slug_unique');
        });

        Schema::table('unit', function (Blueprint $table) {
            $table->dropUnique('unit_slug_unit_unique');
            $table->unique(['tenant_id', 'slug_unit'], 'unit_tenant_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('unit', function (Blueprint $table) {
            $table->dropUnique('unit_tenant_slug_unique');
            $table->unique('slug_unit', 'unit_slug_unit_unique');
        });

        Schema::table('corporate', function (Blueprint $table) {
            $table->dropUnique('corporate_tenant_code_unique');
            $table->dropUnique('corporate_tenant_slug_unique');
            $table->unique('code', 'corporate_code_unique');
            $table->unique('slug_corporate', 'corporate_slug_corporate_unique');
            $table->dropColumn('settings');
        });

        foreach (['corporate', 'unit'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropUnique("{$name}_uuid_unique");
                $table->dropColumn('uuid');
            });
        }

        foreach (array_merge($this->required, ['tb_users']) as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropForeign("{$name}_tenant_id_foreign");
                $table->dropIndex("{$name}_tenant_id_index");
                $table->dropColumn('tenant_id');
            });
        }
    }
};
