<?php

namespace Database\Seeders;

use App\Support\Security\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data inti Sximo untuk tes otomatis (grup, modul, hak akses).
 * Dipakai lewat RefreshDatabase ($seed), tidak untuk instalasi biasa.
 */
class TestCoreSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder Sximo memakai truncate; sama seperti DatabaseSeeder, FK dimatikan sementara
        Schema::withoutForeignKeyConstraints(fn () => $this->call([
            tb_groupsSeeder::class,
            tb_moduleSeeder::class,
            tb_groups_accessSeeder::class,
            RolesSeeder::class,
        ]));

        // Sama dengan migrasi 2026_11_16_100200 (yang berjalan sebelum tb_module terisi saat tes)
        $moduleId = DB::table('tb_module')->where('module_name', 'corporate')->value('module_id');
        DB::table('tb_groups_access')->updateOrInsert(
            ['group_id' => Roles::id(Roles::TENANT_ADMIN), 'module_id' => $moduleId],
            ['access_data' => json_encode(['is_global' => '1', 'is_view' => '1', 'is_detail' => '1', 'is_add' => '1', 'is_edit' => '1', 'is_remove' => '1', 'is_excel' => '1', 'is_csv' => '1', 'is_pdf' => '1', 'is_print' => '1'])]
        );
    }
}
