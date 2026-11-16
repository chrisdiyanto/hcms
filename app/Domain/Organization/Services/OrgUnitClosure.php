<?php

namespace App\Domain\Organization\Services;

use Illuminate\Support\Facades\DB;

/**
 * Closure table struktur organisasi (blueprint 5.2): adjacency list `unit.parent_id`
 * tetap jadi sumber kebenaran, closure dibangun ulang per tenant setiap struktur berubah.
 *
 * Ukuran tenant 1.000–10.000 pegawai berarti ratusan sampai beberapa ribu unit,
 * jadi rebuild penuh per tenant masih murah dan jauh lebih aman daripada patch parsial.
 */
final class OrgUnitClosure
{
    public static function rebuild(int $tenantId): void
    {
        $parents = DB::table('unit')
            ->where('tenant_id', $tenantId)
            ->pluck('parent_id', 'id_unit')
            ->map(fn ($p) => $p ? (int) $p : null)
            ->all();

        $rows = [];
        foreach (array_keys($parents) as $unitId) {
            $depth = 0;
            $current = (int) $unitId;
            $seen = [];

            // Naik ke akar; berhenti di akar, di parent yang bukan milik tenant ini, atau saat ada siklus
            while ($current !== null && ! isset($seen[$current])) {
                $seen[$current] = true;
                $rows[] = ['tenant_id' => $tenantId, 'ancestor_id' => $current, 'descendant_id' => (int) $unitId, 'depth' => $depth++];

                $next = $parents[$current] ?? null;
                $current = ($next !== null && array_key_exists($next, $parents)) ? $next : null;
            }
        }

        DB::transaction(function () use ($tenantId, $rows) {
            DB::table('org_unit_closure')->where('tenant_id', $tenantId)->delete();
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('org_unit_closure')->insert($chunk);
            }
        });
    }

    /** @return int[] unit itu sendiri dan seluruh bawahannya */
    public static function descendantsOf(array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }

        return DB::table('org_unit_closure')
            ->whereIn('ancestor_id', $unitIds)
            ->pluck('descendant_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** Apakah $candidate sama dengan $unitId atau berada di bawahnya (untuk mencegah siklus). */
    public static function isSelfOrDescendant(int $unitId, int $candidate): bool
    {
        return DB::table('org_unit_closure')
            ->where('ancestor_id', $unitId)
            ->where('descendant_id', $candidate)
            ->exists();
    }
}
