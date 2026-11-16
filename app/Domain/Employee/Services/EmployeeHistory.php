<?php

namespace App\Domain\Employee\Services;

use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Jejak perubahan status pegawai (blueprint 6.3: setiap perubahan punya effective date & pengubah). */
final class EmployeeHistory
{
    public const TRACKED = ['company_id', 'employee_number', 'full_name', 'employment_status', 'join_date', 'probation_end_date', 'contract_end_date', 'status', 'ptkp_status', 'marital_status'];

    public static function record(int $employeeId, string $field, $old, $new, ?string $date = null, ?string $reason = null): void
    {
        DB::table('employee_status_histories')->insert(TenantScope::fillForInsert('employee_status_histories', [
            'employee_id' => $employeeId,
            'field' => $field,
            'old_value' => $old === null ? null : Str::limit((string) $old, 255, ''),
            'new_value' => $new === null ? null : Str::limit((string) $new, 255, ''),
            'effective_date' => $date ?? now()->toDateString(),
            'reason' => $reason === null ? null : Str::limit($reason, 255, ''),
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]));
    }

    /** Catat kolom yang berubah antara dua versi baris pegawai. */
    public static function diff(int $employeeId, ?object $before, object $after): void
    {
        foreach (self::TRACKED as $field) {
            $old = $before->{$field} ?? null;
            $new = $after->{$field} ?? null;
            if ($before === null ? $new !== null : (string) $old !== (string) $new) {
                self::record($employeeId, $field, $old, $new);
            }
        }
    }
}
