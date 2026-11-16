<?php

namespace App\Domain\Employee\Actions;

use App\Domain\Employee\Services\EmployeeHistory;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menempatkan pegawai pada posisi (blueprint 6.2).
 *
 * Aturan:
 * - Tepat satu penugasan primer pada satu waktu. Primer baru yang dimulai sesudah
 *   primer terbuka yang ada otomatis mengakhiri primer lama sehari sebelumnya
 *   (mutasi/promosi). Tumpang tindih lain ditolak.
 * - Rangkap jabatan / Plt. pada posisi yang sama tidak boleh tumpang tindih.
 * - Penugasan primer tidak boleh melebihi headcount posisi.
 * - Posisi harus aktif & berlaku pada tanggal mulai; pegawai harus aktif dan
 *   tanggal mulai tidak sebelum tanggal bergabung.
 */
final class AssignEmployee
{
    public const TYPES = ['primary' => 'Primer', 'concurrent' => 'Rangkap jabatan', 'acting' => 'Plt./Pjs.'];

    public function handle(int $employeeId, array $data): int
    {
        return DB::transaction(function () use ($employeeId, $data) {
            // Kunci baris pegawai agar dua penugasan bersamaan tidak lolos validasi tumpang tindih
            $employee = TenantScope::table('employees')->where('id', $employeeId)->whereNull('deleted_at')->lockForUpdate()->first()
                ?? abort(404);

            $from = Carbon::parse($data['effective_from'])->toDateString();
            $to = empty($data['effective_to']) ? null : Carbon::parse($data['effective_to'])->toDateString();
            $type = $data['type'];

            $this->validate($employee, (int) $data['position_id'], $type, $from, $to);

            if ($type === 'primary') {
                $this->closePreviousPrimary($employeeId, $from);
            }

            $id = DB::table('employee_assignments')->insertGetId(TenantScope::fillForInsert('employee_assignments', [
                'employee_id' => $employeeId,
                'position_id' => (int) $data['position_id'],
                'type' => $type,
                'effective_from' => $from,
                'effective_to' => $to,
                'decree_number' => $data['decree_number'] ?? null,
                'decree_date' => $data['decree_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            $position = TenantScope::table('positions')->where('id', $data['position_id'])->value('code');
            EmployeeHistory::record($employeeId, 'assignment_'.$type, null, $position, $from, $data['decree_number'] ?? null);

            return $id;
        });
    }

    public function end(int $assignmentId, string $date, ?string $reason = null): void
    {
        $assignment = TenantScope::table('employee_assignments')->where('id', $assignmentId)->first() ?? abort(404);
        $date = Carbon::parse($date)->toDateString();

        if ($date < $assignment->effective_from) {
            throw ValidationException::withMessages(['effective_to' => 'Tanggal akhir tidak boleh sebelum tanggal mulai penugasan.']);
        }

        TenantScope::table('employee_assignments')->where('id', $assignmentId)
            ->update(['effective_to' => $date, 'updated_by' => auth()->id(), 'updated_at' => now()]);

        $position = TenantScope::table('positions')->where('id', $assignment->position_id)->value('code');
        EmployeeHistory::record((int) $assignment->employee_id, 'assignment_'.$assignment->type, $position, null, $date, $reason);
    }

    private function validate(object $employee, int $positionId, string $type, string $from, ?string $to): void
    {
        $fail = fn (string $field, string $message) => throw ValidationException::withMessages([$field => $message]);

        if (! array_key_exists($type, self::TYPES)) {
            $fail('type', 'Jenis penugasan tidak dikenal.');
        }
        if ($to !== null && $to < $from) {
            $fail('effective_to', 'Tanggal akhir tidak boleh sebelum tanggal mulai.');
        }
        if ($employee->status !== 'active') {
            $fail('employee', 'Pegawai sudah tidak aktif.');
        }
        if ($from < $employee->join_date) {
            $fail('effective_from', 'Tanggal mulai tidak boleh sebelum tanggal bergabung ('.$employee->join_date.').');
        }

        $position = TenantScope::table('positions')->where('id', $positionId)->whereNull('deleted_at')->first();
        if (! $position) {
            $fail('position_id', 'Posisi tidak ditemukan.');
        }
        if ($position->status !== 'active') {
            $fail('position_id', 'Posisi sedang dibekukan atau ditutup.');
        }
        if ($from < $position->effective_from || ($position->effective_to && $from > $position->effective_to)) {
            $fail('effective_from', 'Posisi tidak berlaku pada tanggal mulai tersebut.');
        }

        $overlapping = fn ($q) => $q->where('effective_from', '<=', $to ?? '9999-12-31')
            ->where(fn ($w) => $w->whereNull('effective_to')->orWhere('effective_to', '>=', $from));

        if ($type === 'primary') {
            // Primer terbuka yang dimulai sebelum $from akan ditutup otomatis; selain itu tidak boleh tumpang tindih
            $conflict = TenantScope::table('employee_assignments')->where('employee_id', $employee->id)->where('type', 'primary')
                ->where($overlapping)
                ->where(fn ($w) => $w->whereNotNull('effective_to')->orWhere('effective_from', '>=', $from))
                ->exists();
            if ($conflict) {
                $fail('effective_from', 'Periode bertumpang tindih dengan penugasan primer lain.');
            }

            $occupied = TenantScope::table('employee_assignments')->where('position_id', $positionId)->where('type', 'primary')
                ->where('employee_id', '!=', $employee->id)->where($overlapping)->count();
            if ($occupied >= (int) $position->headcount) {
                $fail('position_id', 'Posisi sudah terisi penuh (headcount '.$position->headcount.').');
            }
        } elseif (TenantScope::table('employee_assignments')->where('employee_id', $employee->id)->where('position_id', $positionId)->where($overlapping)->exists()) {
            $fail('position_id', 'Pegawai sudah ditugaskan pada posisi ini dalam periode tersebut.');
        }
    }

    private function closePreviousPrimary(int $employeeId, string $from): void
    {
        $dayBefore = Carbon::parse($from)->subDay()->toDateString();

        $open = TenantScope::table('employee_assignments')->where('employee_id', $employeeId)->where('type', 'primary')
            ->whereNull('effective_to')->where('effective_from', '<', $from)->get();

        foreach ($open as $assignment) {
            $this->end((int) $assignment->id, $dayBefore, 'Digantikan penugasan primer baru');
        }
    }
}
