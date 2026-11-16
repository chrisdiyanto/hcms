<?php

namespace App\Domain\Employee\Services;

use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Identitas negara pegawai (NIK KTP, NPWP, BPJS) — data pribadi yang dilindungi.
 * Disimpan terenkripsi; NIK juga disimpan sebagai HMAC untuk cek duplikat per
 * tenant tanpa membuka enkripsi. Hanya ditampilkan bagi pemegang izin is_sensitive,
 * dan setiap pembukaan dicatat.
 */
final class EmployeeIdentity
{
    public const FIELDS = ['nik_ktp' => 'NIK KTP', 'npwp' => 'NPWP', 'bpjs_kesehatan' => 'BPJS Kesehatan', 'bpjs_ketenagakerjaan' => 'BPJS Ketenagakerjaan'];

    public static function nikHash(?string $nik): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $nik);

        return $digits === '' ? null : hash_hmac('sha256', $digits, (string) config('app.key'));
    }

    /** Simpan identitas; nilai kosong menghapus nilai lama. */
    public static function save(int $employeeId, array $values): void
    {
        $data = ['updated_by' => auth()->id(), 'updated_at' => now()];
        foreach (array_keys(self::FIELDS) as $field) {
            if (! array_key_exists($field, $values)) {
                continue;
            }
            $plain = trim((string) $values[$field]);
            $data[$field] = $plain === '' ? null : Crypt::encryptString($plain);
        }
        if (array_key_exists('nik_ktp', $values)) {
            $data['nik_hash'] = self::nikHash($values['nik_ktp']);
        }

        $existing = TenantScope::table('employee_identities')->where('employee_id', $employeeId);
        if ($existing->exists()) {
            $existing->update($data);
        } else {
            DB::table('employee_identities')->insert(TenantScope::fillForInsert('employee_identities', $data + [
                'employee_id' => $employeeId, 'created_at' => now(),
            ]));
        }

        $changed = array_intersect_key(self::FIELDS, array_intersect_key($values, self::FIELDS));
        AuditLog::security('employee_identities', $employeeId, 'Identitas negara diperbarui: '.implode(', ', $changed), array_fill_keys(array_keys($changed), '[diubah]'));
    }

    /** @return array<string, ?string> nilai terbuka */
    public static function reveal(int $employeeId): array
    {
        $row = TenantScope::table('employee_identities')->where('employee_id', $employeeId)->first();
        $out = [];
        foreach (array_keys(self::FIELDS) as $field) {
            $out[$field] = $row && $row->{$field} ? Crypt::decryptString($row->{$field}) : null;
        }

        return $out;
    }

    /** @return array<string, ?string> nilai tersamar, mis. ••••••••••••3456 */
    public static function masked(int $employeeId): array
    {
        return array_map(
            fn ($v) => $v === null ? null : str_repeat('•', max(0, mb_strlen($v) - 4)).mb_substr($v, -4),
            self::reveal($employeeId)
        );
    }

    public static function nikTaken(string $nik, ?int $exceptEmployeeId = null): bool
    {
        $hash = self::nikHash($nik);

        return $hash !== null && TenantScope::table('employee_identities')->where('nik_hash', $hash)
            ->when($exceptEmployeeId, fn ($q) => $q->where('employee_id', '!=', $exceptEmployeeId))->exists();
    }
}
