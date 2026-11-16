<?php

use App\Domain\Tenancy\Actions\CreateTenant;
use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tenant yang dibuat sebelum perbaikan ini tidak punya kategori & tipe unit,
 * sehingga unit pertama tidak bisa dibuat. Isi default untuk tenant yang belum
 * punya, dan tampilkan menu Kategori/Tipe Unit di bawah Organisasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            CreateTenant::seedUnitReferences((int) $tenantId);
        }
        HcmsModules::sync();
    }

    public function down(): void
    {
        DB::table('tb_menu')->whereIn('module', ['unitcategory', 'unittype'])->delete();
    }
};
