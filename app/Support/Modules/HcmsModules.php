<?php

namespace App\Support\Modules;

use App\Support\Security\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registri modul HCMS yang ditulis tangan (bukan hasil generator Sximo).
 *
 * Mendaftarkan baris tb_module (tipe `core`, jadi tidak bisa di-rebuild generator),
 * menu, dan hak akses default per peran. Seeder Sximo mengosongkan tb_module,
 * tb_menu, dan tb_groups_access, jadi sync() dipanggil dari migrasi DAN seeder.
 * Hak akses yang sudah diubah admin lewat halaman Permission tidak ditimpa.
 */
final class HcmsModules
{
    private const FULL = ['is_global', 'is_view', 'is_detail', 'is_add', 'is_edit', 'is_remove', 'is_excel', 'is_csv', 'is_pdf', 'is_print', 'is_approve', 'is_sensitive'];

    private const READ = ['is_global', 'is_view', 'is_detail'];

    /** Menu induk (tanpa modul) */
    private const PARENTS = [
        'organisasi' => ['name' => 'Organisasi', 'id' => 'Organisasi', 'icon' => 'fa fa-sitemap', 'position' => 'sidebar'],
    ];

    public static function definitions(): array
    {
        $hrManage = [Roles::SUPER_ADMIN => self::FULL, Roles::TENANT_ADMIN => self::FULL, Roles::HR_ADMIN => self::FULL];
        $orgRead = [Roles::HRBP => self::READ, Roles::EXECUTIVE => self::READ, Roles::AUDITOR => self::READ, Roles::DEPARTMENT_HEAD => self::READ];

        return [
            // Platform & tenant (Control Panel)
            'tenants' => ['title' => 'Tenant', 'table' => 'tenants', 'url' => 'core/tenants', 'parent' => 'Control Panel', 'icon' => 'fa fa-building', 'title_id' => 'Tenant',
                'access' => [Roles::SUPER_ADMIN => self::FULL], 'register_module' => false],
            'corporate' => ['title' => 'Company', 'table' => 'corporate', 'url' => 'corporate', 'parent' => 'Control Panel', 'icon' => 'fa fa-briefcase', 'title_id' => 'Perusahaan',
                'access' => [Roles::SUPER_ADMIN => self::FULL, Roles::TENANT_ADMIN => self::FULL], 'register_module' => false],

            // Organization & Job Architecture
            'unit' => ['title' => 'Unit Organisasi', 'table' => 'unit', 'url' => 'unit', 'parent' => 'organisasi', 'icon' => 'fa fa-sitemap', 'title_id' => 'Unit Organisasi',
                'access' => $hrManage + $orgRead, 'register_module' => false],
            'jobfamilies' => ['title' => 'Job Family', 'table' => 'job_families', 'url' => 'organization/job-families', 'parent' => 'organisasi', 'icon' => 'fa fa-layer-group', 'title_id' => 'Rumpun Jabatan',
                'access' => $hrManage + $orgRead],
            'joblevels' => ['title' => 'Job Level', 'table' => 'job_levels', 'url' => 'organization/job-levels', 'parent' => 'organisasi', 'icon' => 'fa fa-signal', 'title_id' => 'Level Jabatan',
                'access' => $hrManage + $orgRead],
            'jobgrades' => ['title' => 'Job Grade', 'table' => 'job_grades', 'url' => 'organization/job-grades', 'parent' => 'organisasi', 'icon' => 'fa fa-list-ol', 'title_id' => 'Grade',
                'access' => $hrManage + $orgRead],
            'jobs' => ['title' => 'Job', 'table' => 'jobs_catalog', 'url' => 'organization/jobs', 'parent' => 'organisasi', 'icon' => 'fa fa-id-badge', 'title_id' => 'Jabatan',
                'access' => $hrManage + $orgRead],
            'positions' => ['title' => 'Posisi', 'table' => 'positions', 'url' => 'organization/positions', 'parent' => 'organisasi', 'icon' => 'fa fa-user-tie', 'title_id' => 'Posisi',
                'access' => $hrManage + $orgRead],
        ];
    }

    public static function sync(): void
    {
        if (! Schema::hasTable('tb_module') || ! Schema::hasColumn('tb_groups', 'code')) {
            return;
        }

        Roles::flushCache();
        $parentIds = self::syncParents();

        foreach (self::definitions() as $name => $def) {
            if ($def['register_module'] ?? true) {
                self::registerModule($name, $def);
            }
            self::syncMenu($name, $def, $parentIds);
            self::grantDefaults($name, $def['access']);
        }

        Roles::ensureAccessRows();
    }

    private static function syncParents(): array
    {
        $ids = [];
        foreach (self::PARENTS as $key => $parent) {
            $ids[$key] = (int) (DB::table('tb_menu')->where('menu_name', $parent['name'])->where('parent_id', 0)->value('menu_id')
                ?: DB::table('tb_menu')->insertGetId([
                    'parent_id' => 0, 'module' => '', 'url' => '', 'menu_name' => $parent['name'], 'menu_type' => 'internal',
                    'role_id' => '', 'deep' => 0, 'ordering' => (int) DB::table('tb_menu')->where('parent_id', 0)->max('ordering') + 1,
                    'position' => $parent['position'], 'menu_icons' => $parent['icon'], 'active' => '1',
                    'access_data' => json_encode(self::menuAccess(array_keys(self::definitions()['jobfamilies']['access']))),
                    'allow_guest' => '0', 'menu_lang' => json_encode(['title' => ['en' => $parent['name'], 'id' => $parent['id']]]),
                ]));
        }
        $ids['Control Panel'] = (int) DB::table('tb_menu')->where('menu_name', 'Control Panel')->where('parent_id', 0)->value('menu_id');

        return $ids;
    }

    private static function registerModule(string $name, array $def): void
    {
        if (DB::table('tb_module')->where('module_name', $name)->exists()) {
            return;
        }

        DB::table('tb_module')->insert([
            'module_name' => $name,
            'module_title' => $def['title'],
            'module_note' => 'Modul HCMS (ditulis tangan, bukan generator)',
            'module_author' => 'HCMS',
            'module_created' => now(),
            'module_desc' => '',
            'module_db' => $def['table'],
            'module_db_key' => 'id',
            'module_type' => 'core',
            'module_config' => \SiteHelpers::CF_encode_json(['table_db' => $def['table'], 'primary_key' => 'id', 'grid' => [], 'forms' => []]),
            'module_lang' => json_encode(['title' => ['id' => $def['title_id']], 'note' => []]),
        ]);
    }

    private static function syncMenu(string $name, array $def, array $parentIds): void
    {
        $parentId = $parentIds[$def['parent']] ?? 0;
        $module = $def['url'];

        if (DB::table('tb_menu')->where('module', $module)->exists()) {
            return;
        }

        DB::table('tb_menu')->insert([
            'parent_id' => $parentId, 'module' => $module, 'url' => '', 'menu_name' => $def['title'], 'menu_type' => 'internal',
            'role_id' => '', 'deep' => 0, 'ordering' => (int) DB::table('tb_menu')->where('parent_id', $parentId)->max('ordering') + 1,
            'position' => $parentId ? (string) DB::table('tb_menu')->where('menu_id', $parentId)->value('position') : 'sidebar',
            'menu_icons' => $def['icon'], 'active' => '1',
            'access_data' => json_encode(self::menuAccess(array_keys($def['access']))),
            'allow_guest' => '0', 'menu_lang' => json_encode(['title' => ['en' => $def['title'], 'id' => $def['title_id']]]),
        ]);
    }

    /** Hak default hanya mengisi baris yang belum pernah diberi akses (semua 0 / belum ada). */
    private static function grantDefaults(string $name, array $access): void
    {
        $moduleId = DB::table('tb_module')->where('module_name', $name)->value('module_id');
        if (! $moduleId) {
            return;
        }

        foreach ($access as $roleCode => $tasks) {
            $groupId = Roles::id($roleCode);
            if (! $groupId) {
                continue;
            }
            $current = json_decode((string) DB::table('tb_groups_access')->where('group_id', $groupId)->where('module_id', $moduleId)->value('access_data'), true) ?: [];
            if (in_array('1', array_map('strval', $current), true)) {
                continue;
            }
            DB::table('tb_groups_access')->updateOrInsert(
                ['group_id' => $groupId, 'module_id' => $moduleId],
                ['access_data' => json_encode(array_fill_keys($tasks, '1') + array_fill_keys(self::FULL, '0'))]
            );
        }
    }

    private static function menuAccess(array $roleCodes): array
    {
        $access = [];
        foreach (DB::table('tb_groups')->pluck('group_id') as $groupId) {
            $access[(string) $groupId] = '0';
        }
        foreach ($roleCodes as $code) {
            if ($id = Roles::id($code)) {
                $access[(string) $id] = '1';
            }
        }

        return $access;
    }
}
