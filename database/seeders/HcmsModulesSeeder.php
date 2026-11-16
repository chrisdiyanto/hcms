<?php

namespace Database\Seeders;

use App\Support\Modules\HcmsModules;
use Illuminate\Database\Seeder;

/** Modul, menu, dan hak akses default HCMS; jalankan sesudah seeder modul/menu/akses Sximo. */
class HcmsModulesSeeder extends Seeder
{
    public function run(): void
    {
        HcmsModules::sync();
    }
}
