<?php

use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Modul & menu "Kepegawaian › Pegawai" beserta hak akses default. */
return new class extends Migration
{
    public function up(): void
    {
        HcmsModules::sync();
    }

    public function down(): void
    {
        $id = DB::table('tb_module')->where('module_name', 'employees')->value('module_id');
        DB::table('tb_groups_access')->where('module_id', $id)->delete();
        DB::table('tb_module')->where('module_id', $id)->delete();
        DB::table('tb_menu')->where('module', 'employees')->delete();
        DB::table('tb_menu')->where('menu_name', 'Kepegawaian')->where('parent_id', 0)->delete();
    }
};
