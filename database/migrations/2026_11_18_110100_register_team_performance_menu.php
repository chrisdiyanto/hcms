<?php

use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Layanan Mandiri › Kinerja Tim Saya (atasan memantau rencana bawahan langsung). */
return new class extends Migration
{
    public function up(): void
    {
        HcmsModules::sync();
    }

    public function down(): void
    {
        DB::table('tb_menu')->where('module', 'performance/team')->delete();
    }
};
