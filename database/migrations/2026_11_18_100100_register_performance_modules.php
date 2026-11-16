<?php

use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Menu "Kinerja" (Kebijakan, Periode & Sasaran) dan Layanan Mandiri › Rencana Kinerja Saya. */
return new class extends Migration
{
    public function up(): void
    {
        HcmsModules::sync();
    }

    public function down(): void
    {
        $ids = DB::table('tb_module')->whereIn('module_name', ['perfpolicies', 'perfperiods'])->pluck('module_id');
        DB::table('tb_groups_access')->whereIn('module_id', $ids)->delete();
        DB::table('tb_module')->whereIn('module_id', $ids)->delete();
        DB::table('tb_menu')->whereIn('module', ['performance/policies', 'performance/periods', 'performance/my-plans'])->delete();
        DB::table('tb_menu')->where('menu_name', 'Kinerja')->where('parent_id', 0)->delete();
    }
};
