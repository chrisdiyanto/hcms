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
        $today = now()->toDateString();

        return self::forPeriod($today, $today, $scope);
    }

    /**
     * Pegawai dalam cakupan selama rentang tanggal: penugasannya di unit cakupan beririsan dengan
     * [$start, $end]. Dipakai laporan historis (headcount akhir bulan, terminasi, turnover) agar
     * pegawai yang sudah keluar atau pindah tetap terhitung di unit asalnya pada periode itu.
     */
    public static function forPeriod(string $start, string $end, ?DataScope $scope = null): Builder
    {
        $scope ??= DataScope::current();
        $query = TenantScope::table('employees')->whereNull('employees.deleted_at');

        if ($scope->isAll()) {
            return $query;
        }

        return $query->where(function ($q) use ($scope, $start, $end) {
            if ($scope->unitIds !== []) {
                $q->whereIn('employees.id', function ($sub) use ($scope, $start, $end) {
                    $sub->select('employee_assignments.employee_id')->from('employee_assignments')
                        ->join('positions', 'positions.id', '=', 'employee_assignments.position_id')
                        ->where('employee_assignments.tenant_id', TenantScope::tenantId())
                        ->where('employee_assignments.effective_from', '<=', $end)
                        ->where(fn ($w) => $w->whereNull('employee_assignments.effective_to')->orWhere('employee_assignments.effective_to', '>=', $start))
                        ->whereIn('positions.unit_id', $scope->unitIds);
                });
            }
            $q->orWhere('employees.user_id', $scope->userId);
        });
    }

    /**
     * Batasi query lain (yang punya kolom pegawai) ke cakupan data pengguna.
     * Cakupan "all" tidak menambah subquery (uji beban: IN-subquery + EXISTS membuat
     * optimizer MariaDB memilih rencana 4× lebih lambat pada 10.000 pegawai);
     * cakupan lain memakai daftar pegawai dari query().
     */
    public static function apply($query, string $employeeColumn, ?DataScope $scope = null)
    {
        $scope ??= DataScope::current();
        if ($scope->isAll()) {
            return $query;
        }

        return $query->whereIn($employeeColumn, self::query($scope)->select('employees.id'));
    }

    /** Seperti apply(), tetapi cakupan dihitung untuk rentang tanggal (lihat forPeriod()). */
    public static function applyForPeriod($query, string $employeeColumn, string $start, string $end, ?DataScope $scope = null)
    {
        $scope ??= DataScope::current();
        if ($scope->isAll()) {
            return $query;
        }

        return $query->whereIn($employeeColumn, self::forPeriod($start, $end, $scope)->select('employees.id'));
    }

    public static function canSee(int $employeeId, ?DataScope $scope = null): bool
    {
        return self::query($scope)->where('employees.id', $employeeId)->exists();
    }
}
