<?php

use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Menu Asesmen Kompetensi, Laporan Kompetensi (Kompetensi) dan Asesmen Saya (Layanan Mandiri). */
return new class extends Migration
{
    public function up(): void
    {
        HcmsModules::sync();
    }

    public function down(): void
    {
        $ids = DB::table('tb_module')->whereIn('module_name', ['assessments', 'competencyreports', 'myassessments'])->pluck('module_id');
        DB::table('tb_groups_access')->whereIn('module_id', $ids)->delete();
        DB::table('tb_module')->whereIn('module_id', $ids)->delete();
        DB::table('tb_menu')->whereIn('module', ['competency/assessments', 'competency/reports', 'competency/my-assessments'])->delete();
    }
};
