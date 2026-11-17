<?php

namespace App\Support\Security;

use App\Domain\Organization\Services\OrgUnitClosure;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;

/**
 * Cakupan data pengguna di dalam tenant (blueprint 3.2):
 * - all    : seluruh data tenant (Tenant Admin, DPO, dst.).
 * - scoped : unit penugasan (user_unit) + user_data_scopes yang berlaku,
 *            masing-masing beserta seluruh unit bawahannya (closure table).
 * - own    : hanya data milik pengguna sendiri.
 *
 * Mode diambil dari tb_groups.data_scope untuk grup yang sedang aktif.
 * Pegawai selalu boleh melihat datanya sendiri (data ownership), apa pun modenya.
 */
final class DataScope
{
    public const ALL = 'all';

    public const SCOPED = 'scoped';

    public const OWN = 'own';

    /** @param  int[]  $unitIds */
    private function __construct(
        public readonly string $mode,
        public readonly int $userId,
        public readonly array $unitIds,
    ) {}

    public static function for(int $userId, int $groupId): self
    {
        $mode = (string) (DB::table('tb_groups')->where('group_id', $groupId)->value('data_scope') ?? self::OWN);

        if ($mode !== self::SCOPED) {
            return new self($mode === self::ALL ? self::ALL : self::OWN, $userId, []);
        }

        $today = now()->toDateString();

        // Unit penugasan sendiri selalu termasuk, beserta bawahannya:
        // tabel lama Sximo (user_unit) dan posisi yang sedang dijabat lewat data pegawai
        // (penugasan primer, rangkap, atau Plt. yang aktif hari ini)
        $legacy = DB::table('user_unit')->where('id_user', $userId)->whereNotNull('id_unit')
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today))
            ->pluck('id_unit');
        $held = TenantScope::table('employee_assignments')
            ->join('employees', 'employees.id', '=', 'employee_assignments.employee_id')
            ->join('positions', 'positions.id', '=', 'employee_assignments.position_id')
            ->where('employees.user_id', $userId)->where('employees.status', 'active')->whereNull('employees.deleted_at')
            ->where('employee_assignments.effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('employee_assignments.effective_to')->orWhere('employee_assignments.effective_to', '>=', $today))
            ->pluck('positions.unit_id');
        $assigned = $legacy->merge($held)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        $grants = TenantScope::table('user_data_scopes')->where('user_id', $userId)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
            ->get(['unit_id', 'include_children']);

        $withChildren = array_merge($assigned, $grants->where('include_children', 1)->pluck('unit_id')->map(fn ($id) => (int) $id)->all());
        $exact = $grants->where('include_children', 0)->pluck('unit_id')->map(fn ($id) => (int) $id)->all();

        $units = array_values(array_unique(array_merge(OrgUnitClosure::descendantsOf($withChildren), $exact)));

        // Hanya unit milik tenant aktif
        $units = $units === [] ? [] : TenantScope::table('unit')->whereIn('id_unit', $units)->pluck('id_unit')->map(fn ($id) => (int) $id)->all();
        sort($units);

        return new self(self::SCOPED, $userId, $units);
    }

    /** Cakupan pengguna yang sedang login, dengan grup aktif dari sesi. */
    public static function current(): self
    {
        return self::for((int) auth()->id(), (int) session('gid'));
    }

    public function isAll(): bool
    {
        return $this->mode === self::ALL;
    }

    public function allowsUnit(?int $unitId): bool
    {
        return $this->isAll() || ($unitId !== null && in_array($unitId, $this->unitIds, true));
    }

    /**
     * Batasi query: baris terlihat bila unitnya dalam cakupan, atau barisnya milik
     * pengguna sendiri ($ownerColumn).
     */
    public function apply($query, ?string $unitColumn, ?string $ownerColumn = null)
    {
        if ($this->isAll()) {
            return $query;
        }

        return $query->where(function ($q) use ($unitColumn, $ownerColumn) {
            $matched = false;
            if ($unitColumn && $this->unitIds !== []) {
                $q->whereIn($unitColumn, $this->unitIds);
                $matched = true;
            }
            if ($ownerColumn) {
                $matched ? $q->orWhere($ownerColumn, $this->userId) : $q->where($ownerColumn, $this->userId);
                $matched = true;
            }
            if (! $matched) {
                $q->whereRaw('1 = 0');
            }
        });
    }

    /** Versi SQL mentah untuk queryWhere() model Sximo. */
    public function sql(?string $unitColumn, ?string $ownerColumn = null): string
    {
        if ($this->isAll()) {
            return '';
        }

        $parts = [];
        if ($unitColumn && $this->unitIds !== []) {
            $parts[] = $unitColumn.' IN ('.implode(',', $this->unitIds).')';
        }
        if ($ownerColumn) {
            $parts[] = $ownerColumn.' = '.$this->userId;
        }

        return ' AND ('.($parts === [] ? '1 = 0' : implode(' OR ', $parts)).') ';
    }
}
