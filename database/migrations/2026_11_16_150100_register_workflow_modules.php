<?php

use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Menu "Layanan Mandiri" (Profil Saya, Tugas Persetujuan, Delegasi) & modul Alur Persetujuan. */
return new class extends Migration
{
    public function up(): void
    {
        HcmsModules::sync();
    }

    public function down(): void
    {
        $id = DB::table('tb_module')->where('module_name', 'workflows')->value('module_id');
        DB::table('tb_groups_access')->where('module_id', $id)->delete();
        DB::table('tb_module')->where('module_id', $id)->delete();
        DB::table('tb_menu')->whereIn('module', ['ess/profile', 'workflow/tasks', 'workflow/delegations', 'workflow/definitions'])->delete();
        DB::table('tb_menu')->where('menu_name', 'Layanan Mandiri')->where('parent_id', 0)->delete();
    }
};
