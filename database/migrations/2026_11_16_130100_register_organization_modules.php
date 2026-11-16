<?php

use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Modul, menu "Organisasi", dan hak akses default Organization & Job Architecture. */
return new class extends Migration
{
    private array $modules = ['jobfamilies', 'joblevels', 'jobgrades', 'jobs', 'positions'];

    public function up(): void
    {
        HcmsModules::sync();
    }

    public function down(): void
    {
        $ids = DB::table('tb_module')->whereIn('module_name', $this->modules)->pluck('module_id');
        DB::table('tb_groups_access')->whereIn('module_id', $ids)->delete();
        DB::table('tb_module')->whereIn('module_id', $ids)->delete();
        DB::table('tb_menu')->whereIn('module', ['organization/job-families', 'organization/job-levels', 'organization/job-grades', 'organization/jobs', 'organization/positions', 'unit'])->delete();
        DB::table('tb_menu')->where('menu_name', 'Organisasi')->where('parent_id', 0)->delete();
    }
};
