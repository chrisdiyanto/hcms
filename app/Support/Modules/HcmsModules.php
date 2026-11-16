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
        'layanan' => ['name' => 'Layanan Mandiri', 'id' => 'Layanan Mandiri', 'icon' => 'fa fa-user-circle', 'position' => 'sidebar'],
        'kepegawaian' => ['name' => 'Kepegawaian', 'id' => 'Kepegawaian', 'icon' => 'fa fa-users', 'position' => 'sidebar'],
        'organisasi' => ['name' => 'Organisasi', 'id' => 'Organisasi', 'icon' => 'fa fa-sitemap', 'position' => 'sidebar'],
        'kompetensi' => ['name' => 'Kompetensi', 'id' => 'Kompetensi', 'icon' => 'fa fa-medal', 'position' => 'sidebar'],
        'kinerja' => ['name' => 'Kinerja', 'id' => 'Kinerja', 'icon' => 'fa fa-bullseye', 'position' => 'sidebar'],
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
            'unitcategory' => ['title' => 'Kategori Unit', 'table' => 'unit_category', 'url' => 'unitcategory', 'parent' => 'organisasi', 'icon' => 'fa fa-tags', 'title_id' => 'Kategori Unit',
                'access' => $hrManage + $orgRead, 'register_module' => false],
            'unittype' => ['title' => 'Tipe Unit', 'table' => 'unit_type', 'url' => 'unittype', 'parent' => 'organisasi', 'icon' => 'fa fa-shapes', 'title_id' => 'Tipe Unit',
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

            // Layanan Mandiri: halaman pribadi (tanpa baris tb_module; akses dicek per data)
            'ess' => ['title' => 'Profil Saya', 'table' => 'employees', 'url' => 'ess/profile', 'parent' => 'layanan', 'icon' => 'fa fa-user', 'title_id' => 'Profil Saya',
                'access' => self::everyone(), 'register_module' => false],
            'approvaltasks' => ['title' => 'Tugas Persetujuan', 'table' => 'approval_tasks', 'url' => 'workflow/tasks', 'parent' => 'layanan', 'icon' => 'fa fa-check-square', 'title_id' => 'Tugas Persetujuan',
                'access' => self::everyone(), 'register_module' => false],
            'delegations' => ['title' => 'Delegasi', 'table' => 'approval_delegations', 'url' => 'workflow/delegations', 'parent' => 'layanan', 'icon' => 'fa fa-share', 'title_id' => 'Delegasi',
                'access' => self::everyone(), 'register_module' => false],
            'workflows' => ['title' => 'Alur Persetujuan', 'table' => 'workflow_definitions', 'url' => 'workflow/definitions', 'parent' => 'Control Panel', 'icon' => 'fa fa-project-diagram', 'title_id' => 'Alur Persetujuan',
                'access' => [Roles::SUPER_ADMIN => self::FULL, Roles::TENANT_ADMIN => self::FULL]],

            'audittrail' => ['title' => 'Audit Trail', 'table' => 'audit_logs', 'url' => 'core/audit', 'parent' => 'Control Panel', 'icon' => 'fa fa-history', 'title_id' => 'Jejak Audit',
                'access' => [Roles::SUPER_ADMIN => self::READ, Roles::TENANT_ADMIN => self::READ, Roles::AUDITOR => self::READ, Roles::DPO => self::READ]],

            'dataimport' => ['title' => 'Migrasi Data', 'table' => 'import_batches', 'url' => 'core/import', 'parent' => 'Control Panel', 'icon' => 'fa fa-file-import', 'title_id' => 'Migrasi Data',
                'access' => [Roles::SUPER_ADMIN => self::FULL, Roles::TENANT_ADMIN => self::FULL, Roles::HR_ADMIN => self::FULL]],

            // Competency Management
            'competencies' => ['title' => 'Kamus Kompetensi', 'table' => 'competencies', 'url' => 'competency/framework', 'parent' => 'kompetensi', 'icon' => 'fa fa-book', 'title_id' => 'Kamus Kompetensi',
                'access' => $hrManage + $orgRead + [Roles::LINE_MANAGER => self::READ, Roles::ASSESSOR => self::READ, Roles::EMPLOYEE => self::READ]],
            'jobprofiles' => ['title' => 'Profil Kompetensi Job', 'table' => 'job_competencies', 'url' => 'competency/job-profiles', 'parent' => 'kompetensi', 'icon' => 'fa fa-clipboard-list', 'title_id' => 'Profil Kompetensi Job',
                'access' => $hrManage + $orgRead + [Roles::LINE_MANAGER => self::READ, Roles::ASSESSOR => self::READ]],
            'assessments' => ['title' => 'Asesmen Kompetensi', 'table' => 'competency_assessments', 'url' => 'competency/assessments', 'parent' => 'kompetensi', 'icon' => 'fa fa-star-half-alt', 'title_id' => 'Asesmen Kompetensi',
                'access' => $hrManage + [Roles::ASSESSOR => self::FULL]],
            'competencyreports' => ['title' => 'Laporan Kompetensi', 'table' => 'job_fit_results', 'url' => 'competency/reports', 'parent' => 'kompetensi', 'icon' => 'fa fa-chart-bar', 'title_id' => 'Laporan Kompetensi',
                'access' => $hrManage + $orgRead + [Roles::LINE_MANAGER => self::READ, Roles::ASSESSOR => self::READ]],
            // Performance Management
            'perfpolicies' => ['title' => 'Kebijakan Kinerja', 'table' => 'performance_policies', 'url' => 'performance/policies', 'parent' => 'kinerja', 'icon' => 'fa fa-balance-scale', 'title_id' => 'Kebijakan Kinerja',
                'access' => $hrManage + [Roles::AUDITOR => self::READ, Roles::HRBP => self::READ]],
            'perfperiods' => ['title' => 'Periode Kinerja', 'table' => 'performance_periods', 'url' => 'performance/periods', 'parent' => 'kinerja', 'icon' => 'fa fa-calendar-check', 'title_id' => 'Periode & Sasaran',
                'access' => $hrManage + $orgRead + [Roles::LINE_MANAGER => self::READ]],
            'myplans' => ['title' => 'Rencana Kinerja Saya', 'table' => 'performance_plans', 'url' => 'performance/my-plans', 'parent' => 'layanan', 'icon' => 'fa fa-bullseye', 'title_id' => 'Rencana Kinerja Saya',
                'access' => self::everyone(), 'register_module' => false],
            'myteamperformance' => ['title' => 'Kinerja Tim Saya', 'table' => 'performance_plans', 'url' => 'performance/team', 'parent' => 'layanan', 'icon' => 'fa fa-users-cog', 'title_id' => 'Kinerja Tim Saya',
                'access' => self::everyone(), 'register_module' => false],
            'myassessments' => ['title' => 'Asesmen Saya', 'table' => 'assessment_raters', 'url' => 'competency/my-assessments', 'parent' => 'layanan', 'icon' => 'fa fa-star', 'title_id' => 'Asesmen Saya',
                'access' => self::everyone(), 'register_module' => false],

            // Employee Management — isi daftar tetap dibatasi data scope tiap peran
            'employees' => ['title' => 'Pegawai', 'table' => 'employees', 'url' => 'employees', 'parent' => 'kepegawaian', 'icon' => 'fa fa-id-card', 'title_id' => 'Pegawai',
                'access' => $hrManage + $orgRead + [Roles::LINE_MANAGER => self::READ, Roles::EMPLOYEE => self::READ]],
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
            // Menu induk terlihat oleh semua peran yang boleh membuka salah satu anaknya
            $roles = [];
            foreach (self::definitions() as $def) {
                if ($def['parent'] === $key) {
                    $roles = array_merge($roles, array_keys($def['access']));
                }
            }
            $roles = array_values(array_unique($roles));

            $ids[$key] = (int) (DB::table('tb_menu')->where('menu_name', $parent['name'])->where('parent_id', 0)->value('menu_id')
                ?: DB::table('tb_menu')->insertGetId([
                    'parent_id' => 0, 'module' => '', 'url' => '', 'menu_name' => $parent['name'], 'menu_type' => 'internal',
                    'role_id' => '', 'deep' => 0, 'ordering' => (int) DB::table('tb_menu')->where('parent_id', 0)->max('ordering') + 1,
                    'position' => $parent['position'], 'menu_icons' => $parent['icon'], 'active' => '1',
                    'access_data' => json_encode(self::menuAccess($roles)),
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

    /** Semua peran manusia (bukan API Client), untuk menu halaman pribadi. */
    private static function everyone(): array
    {
        $codes = array_diff(array_keys(Roles::definitions()), [Roles::API_CLIENT]);

        return array_fill_keys($codes, []);
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
