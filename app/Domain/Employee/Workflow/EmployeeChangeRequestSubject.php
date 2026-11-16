<?php

namespace App\Domain\Employee\Workflow;

use App\Domain\Employee\Services\EmployeeHistory;
use App\Domain\Workflow\Contracts\WorkflowSubject;
use App\Domain\Workflow\WorkflowEngine;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ESS change request (blueprint 6.4): pegawai mengajukan perubahan data pribadi,
 * HR memverifikasi lewat workflow, lalu perubahan diterapkan beserta riwayatnya.
 */
final class EmployeeChangeRequestSubject implements WorkflowSubject
{
    public const CODE = 'employee_change_request';

    /** Kolom yang boleh diajukan pegawai sendiri. */
    public const FIELDS = [
        'phone' => 'Telepon',
        'email' => 'Email',
        'address_ktp' => 'Alamat KTP',
        'address_domicile' => 'Alamat domisili',
        'marital_status' => 'Status perkawinan',
        'ptkp_status' => 'Status PTKP',
    ];

    /**
     * Buat pengajuan untuk pegawai milik pengguna; hanya kolom yang berubah disimpan.
     *
     * @return int id pengajuan
     */
    public function submit(object $employee, array $values, string $reason, int $userId): int
    {
        $changes = [];
        foreach (self::FIELDS as $field => $label) {
            if (! array_key_exists($field, $values)) {
                continue;
            }
            $new = $values[$field] === '' ? null : $values[$field];
            if ((string) ($employee->{$field} ?? '') !== (string) ($new ?? '')) {
                $changes[$field] = [$employee->{$field}, $new];
            }
        }

        if ($changes === []) {
            throw ValidationException::withMessages(['changes' => 'Tidak ada data yang berubah.']);
        }
        if (TenantScope::table('employee_change_requests')->where('employee_id', $employee->id)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['changes' => 'Masih ada pengajuan perubahan yang menunggu persetujuan.']);
        }

        return DB::transaction(function () use ($employee, $changes, $reason, $userId) {
            $id = DB::table('employee_change_requests')->insertGetId(TenantScope::fillForInsert('employee_change_requests', [
                'employee_id' => $employee->id,
                'requested_by' => $userId,
                'changes' => json_encode($changes),
                'reason' => $reason,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            $instance = app(WorkflowEngine::class)->start(self::CODE, $id, $userId, (int) $employee->id);
            TenantScope::table('employee_change_requests')->where('id', $id)->update(['workflow_instance_id' => $instance->id]);

            return $id;
        });
    }

    public function title(int $id): string
    {
        $row = $this->row($id);

        return 'Perubahan data: '.($row->full_name ?? '?').' ('.($row->employee_number ?? '?').')';
    }

    public function url(int $id): string
    {
        return 'ess/profile';
    }

    public function summary(int $id): array
    {
        $row = $this->row($id);
        $lines = [];
        foreach (json_decode((string) $row->changes, true) ?: [] as $field => [$old, $new]) {
            $lines[] = ['label' => self::FIELDS[$field] ?? $field, 'old' => $old, 'new' => $new];
        }
        $lines[] = ['label' => 'Alasan', 'value' => $row->reason];

        return $lines;
    }

    public function onApproved(int $id, object $instance): void
    {
        $request = TenantScope::table('employee_change_requests')->where('id', $id)->lockForUpdate()->first();
        $employee = TenantScope::table('employees')->where('id', $request->employee_id)->first();

        $update = [];
        foreach (json_decode((string) $request->changes, true) ?: [] as $field => [$old, $new]) {
            if (isset(self::FIELDS[$field])) {
                $update[$field] = $new;
                EmployeeHistory::record((int) $employee->id, $field, $employee->{$field}, $new, now()->toDateString(), 'ESS #'.$id);
            }
        }

        TenantScope::table('employees')->where('id', $employee->id)->update($update + ['updated_at' => now()]);
        AuditLog::updated('employees', (int) $employee->id, $employee, TenantScope::table('employees')->where('id', $employee->id)->first(), 'ESS #'.$id);
        TenantScope::table('employee_change_requests')->where('id', $id)->update(['status' => 'approved', 'applied_at' => now(), 'updated_at' => now()]);
    }

    public function onRejected(int $id, object $instance): void
    {
        TenantScope::table('employee_change_requests')->where('id', $id)->update(['status' => 'rejected', 'updated_at' => now()]);
    }

    public function onCancelled(int $id, object $instance): void
    {
        TenantScope::table('employee_change_requests')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);
    }

    private function row(int $id): object
    {
        return TenantScope::table('employee_change_requests')
            ->join('employees', 'employees.id', '=', 'employee_change_requests.employee_id')
            ->where('employee_change_requests.id', $id)
            ->first(['employee_change_requests.*', 'employees.full_name', 'employees.employee_number']) ?? abort(404);
    }
}
