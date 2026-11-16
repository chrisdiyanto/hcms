<?php

namespace App\Domain\Employee\Services;

use App\Support\Security\DataScope;
use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Query\Builder;

/**
 * Pegawai yang boleh dilihat pengguna (blueprint 3.2):
 * - all    : seluruh pegawai tenant;
 * - scoped : pegawai yang penugasan aktifnya (hari ini) berada di unit dalam cakupan;
 * - own    : hanya dirinya sendiri.
 * Pegawai selalu boleh melihat datanya sendiri (data ownership).
 */
final class EmployeeScope
{
    public static function query(?DataScope $scope = null): Builder
    {
        $scope ??= DataScope::current();
        $query = TenantScope::table('employees')->whereNull('employees.deleted_at');

        if ($scope->isAll()) {
            return $query;
        }

        $today = now()->toDateString();

        return $query->where(function ($q) use ($scope, $today) {
            if ($scope->unitIds !== []) {
                $q->whereIn('employees.id', function ($sub) use ($scope, $today) {
                    $sub->select('employee_assignments.employee_id')->from('employee_assignments')
                        ->join('positions', 'positions.id', '=', 'employee_assignments.position_id')
                        ->where('employee_assignments.tenant_id', TenantScope::tenantId())
                        ->where('employee_assignments.effective_from', '<=', $today)
                        ->where(fn ($w) => $w->whereNull('employee_assignments.effective_to')->orWhere('employee_assignments.effective_to', '>=', $today))
                        ->whereIn('positions.unit_id', $scope->unitIds);
                });
            }
            $q->orWhere('employees.user_id', $scope->userId);
        });
    }

    public static function canSee(int $employeeId, ?DataScope $scope = null): bool
    {
        return self::query($scope)->where('employees.id', $employeeId)->exists();
    }
}
