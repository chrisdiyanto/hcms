<?php

namespace App\Domain\Organization\Services;

use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;

/**
 * Riwayat perubahan unit organisasi (blueprint 5.2: "Riwayat organisasi
 * disimpan dengan effective dating"). Menyimpan kolom yang berubah sebagai
 * {kolom: [lama, baru]} beserta pengubah dan tanggal.
 */
final class OrganizationHistory
{
    private const TRACKED = ['parent_id', 'unit_name', 'unit_code', 'slug_unit', 'id_corporate', 'id_unit_category', 'id_unit_type', 'status', 'urutan'];

    public static function snapshot($unitId): ?object
    {
        return $unitId ? TenantScope::table('unit')->where('id_unit', $unitId)->first() : null;
    }

    public static function record(int $unitId, ?object $before): void
    {
        $after = self::snapshot($unitId);
        if (! $after) {
            return;
        }

        $changes = [];
        foreach (self::TRACKED as $column) {
            $old = $before->{$column} ?? null;
            $new = $after->{$column} ?? null;
            if ((string) $old !== (string) $new) {
                $changes[$column] = [$old, $new];
            }
        }

        if ($before && $changes === []) {
            return;
        }

        self::write($unitId, $before ? 'updated' : 'created', $changes);
    }

    public static function recordDeleted(array $unitIds): void
    {
        foreach (TenantScope::table('unit')->whereIn('id_unit', $unitIds)->get() as $unit) {
            $changes = [];
            foreach (self::TRACKED as $column) {
                $changes[$column] = [$unit->{$column} ?? null, null];
            }
            self::write((int) $unit->id_unit, 'deleted', $changes);
        }
    }

    private static function write(int $unitId, string $action, array $changes): void
    {
        DB::table('organization_histories')->insert(TenantScope::fillForInsert('organization_histories', [
            'unit_id' => $unitId,
            'action' => $action,
            'changes' => json_encode($changes),
            'effective_date' => now()->toDateString(),
            'changed_by' => auth()->id(),
        ]));
    }
}
