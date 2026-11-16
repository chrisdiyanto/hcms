<?php

use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Menu & modul "Control Panel › Audit Trail" (Tenant Admin, Auditor, DPO: baca saja). */
return new class extends Migration
{
    public function up(): void
    {
        HcmsModules::sync();
    }

    public function down(): void
    {
        $id = DB::table('tb_module')->where('module_name', 'audittrail')->value('module_id');
        DB::table('tb_groups_access')->where('module_id', $id)->delete();
        DB::table('tb_module')->where('module_id', $id)->delete();
        DB::table('tb_menu')->where('module', 'core/audit')->delete();
    }
};
