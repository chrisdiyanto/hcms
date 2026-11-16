<?php

namespace App\Domain\Employee\Actions;

use App\Domain\Employee\Services\EmployeeHistory;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Terminasi pegawai (blueprint 11.1, data minimal di MVP).
 *
 * Tanggal hari ini atau lampau diproses langsung; tanggal mendatang dijadwalkan
 * dan diproses oleh command `hcms:process-terminations` pada tanggal efektif.
 * Pemrosesan: status pegawai menjadi terminated, semua penugasan aktif diakhiri
 * pada tanggal terminasi, dan akun login dinonaktifkan.
 */
final class TerminateEmployee
{
    public const TYPES = [
        'resign' => 'Mengundurkan diri',
        'layoff' => 'PHK',
        'retirement' => 'Pensiun',
        'contract_end' => 'Akhir PKWT',
        'death' => 'Meninggal dunia',
        'other' => 'Lainnya',
    ];

    public function schedule(int $employeeId, array $data): int
    {
        return DB::transaction(function () use ($employeeId, $data) {
            $employee = TenantScope::table('employees')->where('id', $employeeId)->whereNull('deleted_at')->lockForUpdate()->first() ?? abort(404);
            $date = Carbon::parse($data['termination_date'])->toDateString();

            if ($employee->status !== 'active') {
                throw ValidationException::withMessages(['termination_date' => 'Pegawai sudah tidak aktif.']);
            }
            if ($date < $employee->join_date) {
                throw ValidationException::withMessages(['termination_date' => 'Tanggal terminasi tidak boleh sebelum tanggal bergabung.']);
            }
            if (TenantScope::table('employee_terminations')->where('employee_id', $employeeId)->where('state', 'scheduled')->exists()) {
                throw ValidationException::withMessages(['termination_date' => 'Sudah ada terminasi terjadwal untuk pegawai ini.']);
            }

            $id = DB::table('employee_terminations')->insertGetId(TenantScope::fillForInsert('employee_terminations', [
                'employee_id' => $employeeId,
                'termination_date' => $date,
                'type' => $data['type'],
                'reason' => $data['reason'],
                'rehire_eligible' => (bool) ($data['rehire_eligible'] ?? true),
                'state' => 'scheduled',
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            AuditLog::created('employee_terminations', $id, TenantScope::table('employee_terminations')->where('id', $id)->first());
            if ($date <= now()->toDateString()) {
                $this->process($id);
            }

            return $id;
        });
    }

    public function cancel(int $terminationId): void
    {
        TenantScope::table('employee_terminations')->where('id', $terminationId)->where('state', 'scheduled')
            ->update(['state' => 'cancelled', 'updated_at' => now()]) ?: abort(404);
        AuditLog::updated('employee_terminations', $terminationId, ['state' => 'scheduled'], ['state' => 'cancelled'], 'Terminasi dibatalkan');
    }

    public function process(int $terminationId): void
    {
        $t = TenantScope::table('employee_terminations')->where('id', $terminationId)->where('state', 'scheduled')->first();
        if (! $t) {
            return;
        }

        $employee = TenantScope::table('employees')->where('id', $t->employee_id)->first();

        TenantScope::table('employee_assignments')->where('employee_id', $t->employee_id)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $t->termination_date))
            ->update(['effective_to' => $t->termination_date, 'updated_at' => now()]);

        TenantScope::table('employees')->where('id', $t->employee_id)
            ->update(['status' => 'terminated', 'terminated_at' => $t->termination_date, 'updated_at' => now()]);

        if ($employee->user_id) {
            DB::table('tb_users')->where('id', $employee->user_id)->update(['active' => 0]);
        }

        TenantScope::table('employee_terminations')->where('id', $t->id)->update(['state' => 'processed', 'processed_at' => now(), 'updated_at' => now()]);
        EmployeeHistory::record((int) $t->employee_id, 'status', 'active', 'terminated', $t->termination_date, self::TYPES[$t->type].': '.$t->reason);
        AuditLog::updated('employees', (int) $t->employee_id, $employee, TenantScope::table('employees')->where('id', $t->employee_id)->first(), 'Terminasi #'.$t->id);
        if ($employee->user_id) {
            AuditLog::security('tb_users', $employee->user_id, 'Akun login dinonaktifkan karena terminasi #'.$t->id);
        }
    }

    /** Proses semua terminasi terjadwal yang jatuh tempo, lintas tenant. @return int jumlah diproses */
    public function processDue(?string $today = null): int
    {
        $today ??= now()->toDateString();
        $count = 0;
        $context = app(TenantContext::class);

        $due = DB::table('employee_terminations')->where('state', 'scheduled')->where('termination_date', '<=', $today)->get(['id', 'tenant_id']);
        foreach ($due as $row) {
            $context->runAs((int) $row->tenant_id, null, function () use ($row) {
                DB::transaction(fn () => $this->process((int) $row->id));
            });
            $count++;
        }

        return $count;
    }
}
