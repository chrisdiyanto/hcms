<?php

use App\Domain\Competency\Services\CompetencyFramework;
use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Menu "Kompetensi" (Kamus, Profil Job) + kategori kompetensi default per tenant. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            CompetencyFramework::ensureCategories((int) $tenantId);
        }
        HcmsModules::sync();
    }

    public function down(): void
    {
        $ids = DB::table('tb_module')->whereIn('module_name', ['competencies', 'jobprofiles'])->pluck('module_id');
        DB::table('tb_groups_access')->whereIn('module_id', $ids)->delete();
        DB::table('tb_module')->whereIn('module_id', $ids)->delete();
        DB::table('tb_menu')->whereIn('module', ['competency/framework', 'competency/job-profiles'])->delete();
        DB::table('tb_menu')->where('menu_name', 'Kompetensi')->where('parent_id', 0)->delete();
    }
};
