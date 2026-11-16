<?php

use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Menu Penilaian Kinerja, Rating Saya, Keberatan, PIP (Layanan Mandiri) dan Dasbor Kinerja. */
return new class extends Migration
{
    public function up(): void
    {
        HcmsModules::sync();
    }

    public function down(): void
    {
        $ids = DB::table('tb_module')->where('module_name', 'perfdashboard')->pluck('module_id');
        DB::table('tb_groups_access')->whereIn('module_id', $ids)->delete();
        DB::table('tb_module')->whereIn('module_id', $ids)->delete();
        DB::table('tb_menu')->whereIn('module', ['performance/dashboard', 'performance/assessments', 'performance/my-ratings', 'performance/appeals', 'performance/pips'])->delete();
    }
};
