<?php

namespace App\Support\Security;

use Illuminate\Support\Facades\DB;

/**
 * Peran blueprint (bagian 3.1). Kode program merujuk peran lewat `code`,
 * bukan lewat nama atau group_id.
 */
final class Roles
{
    public const SUPER_ADMIN = 'super_admin';

    public const TENANT_ADMIN = 'tenant_admin';

    public const HR_ADMIN = 'hr_admin';

    public const HRBP = 'hrbp';

    public const EXECUTIVE = 'executive';

    public const DEPARTMENT_HEAD = 'department_head';

    public const LINE_MANAGER = 'line_manager';

    public const EMPLOYEE = 'employee';

    public const ASSESSOR = 'assessor';

    public const TALENT_COMMITTEE = 'talent_committee';

    public const RECRUITER = 'recruiter';

    public const TRAINING_ADMIN = 'training_admin';

    public const DPO = 'dpo';

    public const AUDITOR = 'auditor';

    public const API_CLIENT = 'api_client';

    /** @var array<string, int> */
    private static array $ids = [];

    /**
     * @return array<string, array{name:string, description:string, level:int, data_scope:string, requires_mfa:bool, legacy_id?:int}>
     */
    public static function definitions(): array
    {
        return [
            self::SUPER_ADMIN => ['name' => 'Super Administrator', 'description' => 'Mengelola platform; data pegawai tenant hanya lewat break-glass', 'level' => 1, 'data_scope' => 'all', 'requires_mfa' => true, 'legacy_id' => 1],
            self::TENANT_ADMIN => ['name' => 'Tenant Administrator', 'description' => 'Mengelola tenant, company, konfigurasi, dan pengguna', 'level' => 2, 'data_scope' => 'all', 'requires_mfa' => true, 'legacy_id' => 2],
            self::HR_ADMIN => ['name' => 'HR Administrator', 'description' => 'Mengelola data dan proses HR sesuai cakupan', 'level' => 3, 'data_scope' => 'scoped', 'requires_mfa' => true],
            self::HRBP => ['name' => 'HR Business Partner', 'description' => 'Mendampingi unit; melihat data unit binaannya', 'level' => 4, 'data_scope' => 'scoped', 'requires_mfa' => false],
            self::EXECUTIVE => ['name' => 'Executive', 'description' => 'Dashboard eksekutif dan persetujuan strategis', 'level' => 4, 'data_scope' => 'scoped', 'requires_mfa' => true],
            self::DPO => ['name' => 'Data Protection Officer', 'description' => 'Log pemrosesan, permintaan subjek data, insiden', 'level' => 4, 'data_scope' => 'all', 'requires_mfa' => true],
            self::AUDITOR => ['name' => 'Auditor', 'description' => 'Akses baca sesuai penugasan dan periode audit', 'level' => 4, 'data_scope' => 'scoped', 'requires_mfa' => true],
            self::DEPARTMENT_HEAD => ['name' => 'Department Head', 'description' => 'Mengelola kinerja dan pengembangan unit', 'level' => 5, 'data_scope' => 'scoped', 'requires_mfa' => false],
            self::LINE_MANAGER => ['name' => 'Line Manager', 'description' => 'Menilai, check-in, dan mengembangkan bawahan', 'level' => 6, 'data_scope' => 'scoped', 'requires_mfa' => false],
            self::ASSESSOR => ['name' => 'Assessor', 'description' => 'Menilai kompetensi atau potensi tanpa menjadi atasan', 'level' => 7, 'data_scope' => 'scoped', 'requires_mfa' => false],
            self::TALENT_COMMITTEE => ['name' => 'Talent Committee', 'description' => 'Talent review, kalibrasi talenta, dan keputusan suksesi', 'level' => 7, 'data_scope' => 'scoped', 'requires_mfa' => false],
            self::RECRUITER => ['name' => 'Recruiter / Hiring Manager', 'description' => 'Proses rekrutmen sesuai lowongan', 'level' => 7, 'data_scope' => 'scoped', 'requires_mfa' => false],
            self::TRAINING_ADMIN => ['name' => 'Training Administrator', 'description' => 'Katalog, jadwal, dan peserta pelatihan', 'level' => 7, 'data_scope' => 'scoped', 'requires_mfa' => false],
            self::API_CLIENT => ['name' => 'API Client', 'description' => 'Akun mesin untuk integrasi, scope endpoint terbatas', 'level' => 8, 'data_scope' => 'scoped', 'requires_mfa' => false],
            self::EMPLOYEE => ['name' => 'Employee', 'description' => 'Data pribadi, kinerja, kompetensi, IDP, pengajuan', 'level' => 9, 'data_scope' => 'own', 'requires_mfa' => false, 'legacy_id' => 3],
        ];
    }

    /**
     * Samakan tb_groups dengan definitions(). Aman dijalankan berulang
     * (migrasi, DatabaseSeeder, seeder tes). Grup lama 1–3 dipakai ulang ID-nya.
     */
    public static function sync(): void
    {
        foreach (self::definitions() as $code => $role) {
            $values = [
                'name' => $role['name'],
                'description' => $role['description'],
                'level' => $role['level'],
                'data_scope' => $role['data_scope'],
                'requires_mfa' => $role['requires_mfa'],
            ];

            $legacyId = $role['legacy_id'] ?? null;
            $byCode = DB::table('tb_groups')->where('code', $code)->value('group_id');

            if (! $byCode && $legacyId && DB::table('tb_groups')->where('group_id', $legacyId)->whereNull('code')->exists()) {
                DB::table('tb_groups')->where('group_id', $legacyId)->update($values + ['code' => $code]);
            } else {
                DB::table('tb_groups')->updateOrInsert(['code' => $code], $values);
            }
        }

        self::flushCache();
        self::ensureAccessRows();
    }

    /**
     * Setiap pasangan grup × modul wajib punya baris tb_groups_access.
     * Tanpa baris, validAccess() mengembalikan false dan controller Sximo error.
     * Baris baru selalu tanpa akses; hak diberikan lewat halaman Permission modul.
     */
    public static function ensureAccessRows(): void
    {
        $none = json_encode(array_fill_keys(['is_global', 'is_view', 'is_detail', 'is_add', 'is_edit', 'is_remove', 'is_excel', 'is_csv', 'is_pdf', 'is_print', 'is_approve', 'is_sensitive'], '0'));

        $existing = DB::table('tb_groups_access')->get(['group_id', 'module_id'])
            ->map(fn ($r) => $r->group_id.':'.$r->module_id)->flip();

        $missing = [];
        foreach (DB::table('tb_groups')->pluck('group_id') as $groupId) {
            foreach (DB::table('tb_module')->pluck('module_id') as $moduleId) {
                if (! isset($existing[$groupId.':'.$moduleId])) {
                    $missing[] = ['group_id' => $groupId, 'module_id' => $moduleId, 'access_data' => $none];
                }
            }
        }

        foreach (array_chunk($missing, 500) as $chunk) {
            DB::table('tb_groups_access')->insert($chunk);
        }
    }

    public static function id(string $code): int
    {
        return self::$ids[$code] ??= (int) DB::table('tb_groups')->where('code', $code)->value('group_id');
    }

    public static function flushCache(): void
    {
        self::$ids = [];
    }
}
