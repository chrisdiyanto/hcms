<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu Control Panel:
 * - "Tenant" (core/tenants) hanya untuk Superadmin (grup 1).
 * - "Company" (modul Sximo corporate) untuk Superadmin & Administrator.
 *
 * Administrator bertindak sebagai Tenant Administrator sampai peran IAM dibuat,
 * jadi diberi akses penuh ke modul corporate (data otomatis dibatasi tenant-nya).
 */
return new class extends Migration
{
    private const FULL_ACCESS = ['is_global' => '1', 'is_view' => '1', 'is_detail' => '1', 'is_add' => '1', 'is_edit' => '1', 'is_remove' => '1', 'is_excel' => '1', 'is_csv' => '1', 'is_pdf' => '1', 'is_print' => '1'];

    private const NO_ACCESS = ['is_global' => '0', 'is_view' => '0', 'is_detail' => '0', 'is_add' => '0', 'is_edit' => '0', 'is_remove' => '0', 'is_excel' => '0', 'is_csv' => '0', 'is_pdf' => '0', 'is_print' => '0'];

    public function up(): void
    {
        if (! Schema::hasTable('tb_menu')) {
            return;
        }

        $parentId = (int) (DB::table('tb_menu')->where('menu_name', 'Control Panel')->where('parent_id', 0)->value('menu_id') ?? 0);
        $ordering = (int) DB::table('tb_menu')->where('parent_id', $parentId)->max('ordering');

        $menus = [
            ['module' => 'core/tenants', 'menu_name' => 'Tenant', 'id' => 'Tenant', 'icon' => 'fa fa-building', 'access' => ['1' => '1', '2' => '0', '3' => '0']],
            ['module' => 'corporate', 'menu_name' => 'Company', 'id' => 'Perusahaan', 'icon' => 'fa fa-briefcase', 'access' => ['1' => '1', '2' => '1', '3' => '0']],
        ];

        foreach ($menus as $menu) {
            if (DB::table('tb_menu')->where('module', $menu['module'])->exists()) {
                continue;
            }
            DB::table('tb_menu')->insert([
                'parent_id' => $parentId,
                'module' => $menu['module'],
                'url' => '',
                'menu_name' => $menu['menu_name'],
                'menu_type' => 'internal',
                'role_id' => '',
                'deep' => 0,
                'ordering' => ++$ordering,
                'position' => 'topnav',
                'menu_icons' => $menu['icon'],
                'active' => '1',
                'access_data' => json_encode($menu['access']),
                'allow_guest' => '0',
                'menu_lang' => json_encode(['title' => ['en' => $menu['menu_name'], 'id' => $menu['id']]]),
            ]);
        }

        $this->setCorporateAccess(2, self::FULL_ACCESS);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tb_menu')) {
            return;
        }

        DB::table('tb_menu')->whereIn('module', ['core/tenants', 'corporate'])->delete();
        $this->setCorporateAccess(2, self::NO_ACCESS);
    }

    private function setCorporateAccess(int $groupId, array $access): void
    {
        $moduleId = DB::table('tb_module')->where('module_name', 'corporate')->value('module_id');
        if (! $moduleId) {
            return;
        }

        DB::table('tb_groups_access')->updateOrInsert(
            ['group_id' => $groupId, 'module_id' => $moduleId],
            ['access_data' => json_encode($access)]
        );
    }
};
