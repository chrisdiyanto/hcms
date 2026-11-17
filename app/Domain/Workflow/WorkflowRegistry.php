<?php

namespace App\Domain\Workflow;

use App\Domain\Development\Workflow\IdpSubject;
use App\Domain\Employee\Workflow\EmployeeChangeRequestSubject;
use App\Domain\Performance\Workflow\KpiChangeRequestSubject;
use App\Domain\Performance\Workflow\PerformancePlanSubject;
use App\Domain\Workflow\Contracts\WorkflowSubject;
use App\Support\Security\Roles;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;

/**
 * Daftar alur yang dikenal sistem beserta langkah default-nya.
 * Tenant dapat mengubah langkah lewat halaman konfigurasi; setiap perubahan
 * menjadi versi baru (pengajuan yang sudah berjalan tetap memakai versinya).
 *
 * Jenis approver per langkah:
 * - supervisor : atasan (solid line) posisi pengaju, `levels` tingkat ke atas;
 *                posisi kosong otomatis naik ke atasan berikutnya.
 * - position   : pemangku posisi tertentu (`position_id`), kosong → naik.
 * - role       : semua pengguna tenant dengan peran `role`; siapa pun boleh memutus.
 */
final class WorkflowRegistry
{
    public const APPROVER_TYPES = ['supervisor' => 'Atasan (berdasarkan posisi)', 'position' => 'Pemangku posisi tertentu', 'role' => 'Peran'];

    public static function definitions(): array
    {
        return [
            'employee_change_request' => [
                'name' => 'Perubahan data pegawai (ESS)',
                'subject' => EmployeeChangeRequestSubject::class,
                'steps' => [
                    ['name' => 'Verifikasi HR', 'approver' => ['type' => 'role', 'role' => Roles::HR_ADMIN], 'sla_hours' => 48],
                ],
            ],
            PerformancePlanSubject::CODE => [
                'name' => 'Persetujuan rencana kinerja (KPI)',
                'subject' => PerformancePlanSubject::class,
                'steps' => [
                    ['name' => 'Persetujuan atasan langsung', 'approver' => ['type' => 'supervisor', 'levels' => 1], 'sla_hours' => 72],
                ],
            ],
            KpiChangeRequestSubject::CODE => [
                'name' => 'Perubahan KPI (change request)',
                'subject' => KpiChangeRequestSubject::class,
                'steps' => [
                    ['name' => 'Persetujuan atasan langsung', 'approver' => ['type' => 'supervisor', 'levels' => 1], 'sla_hours' => 72],
                ],
            ],
            IdpSubject::CODE => [
                'name' => 'Persetujuan rencana pengembangan individu (IDP)',
                'subject' => IdpSubject::class,
                'steps' => [
                    ['name' => 'Persetujuan atasan langsung', 'approver' => ['type' => 'supervisor', 'levels' => 1], 'sla_hours' => 72],
                ],
            ],
        ];
    }

    public static function subject(string $type): WorkflowSubject
    {
        $class = self::definitions()[$type]['subject'] ?? null;
        abort_unless($class, 500, 'Subjek workflow tidak dikenal: '.$type);

        return app($class);
    }

    /** Versi aktif alur di tenant aktif; dibuat dari default bila belum ada. */
    public static function currentVersion(string $code): object
    {
        $definition = TenantScope::table('workflow_definitions')->where('code', $code)->first();
        if (! $definition) {
            $default = self::definitions()[$code] ?? abort(500, 'Alur tidak dikenal: '.$code);
            $id = DB::table('workflow_definitions')->insertGetId(TenantScope::fillForInsert('workflow_definitions', [
                'code' => $code, 'name' => $default['name'], 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]));
            self::saveVersion($id, $default['steps'], null);
            $definition = TenantScope::table('workflow_definitions')->where('id', $id)->first();
        }

        return TenantScope::table('workflow_versions')->where('definition_id', $definition->id)->where('is_current', true)->first();
    }

    public static function saveVersion(int $definitionId, array $steps, ?int $userId): int
    {
        return DB::transaction(function () use ($definitionId, $steps, $userId) {
            $next = (int) TenantScope::table('workflow_versions')->where('definition_id', $definitionId)->max('version') + 1;
            TenantScope::table('workflow_versions')->where('definition_id', $definitionId)->update(['is_current' => false]);

            return DB::table('workflow_versions')->insertGetId(TenantScope::fillForInsert('workflow_versions', [
                'definition_id' => $definitionId, 'version' => $next, 'steps' => json_encode(array_values($steps)),
                'is_current' => true, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]));
        });
    }
}
