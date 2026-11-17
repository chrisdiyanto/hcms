<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tb_menu')->where('module', 'performance/dashboard')
            ->update(['module' => 'executive/analytic/performance']);
    }

    public function down(): void
    {
        DB::table('tb_menu')->where('module', 'executive/analytic/performance')
            ->update(['module' => 'performance/dashboard']);
    }
};
