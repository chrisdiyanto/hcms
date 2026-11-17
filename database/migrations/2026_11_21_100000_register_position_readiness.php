<?php

use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Kompetensi › Kesiapan Posisi (laporan CBHRM 4, backlog Fase 2). */
return new class extends Migration
{
    public function up(): void
    {
        HcmsModules::sync();
    }

    public function down(): void
    {
        DB::table('tb_menu')->where('module', 'competency/readiness')->delete();
        DB::table('tb_module')->where('module_name', 'positionreadiness')->delete();
    }
};
