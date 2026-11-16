<?php

namespace App\Domain\DataImport\Importers;

use App\Domain\DataImport\Importer;
use App\Domain\Employee\Actions\AssignEmployee;
use App\Domain\Employee\Services\EmployeeHistory;
use App\Domain\Employee\Services\EmployeeIdentity;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Pegawai + penugasan primer + identitas negara (terenkripsi).
 * Upsert berdasarkan NIP. Identitas hanya boleh diimpor oleh pengguna dengan
 * izin Data Sensitif (opsi `sensitive`). Penugasan memakai AssignEmployee,
 * jadi aturan headcount/tumpang tindih sama dengan input manual.
 */
final class EmployeesImporter extends Importer
{
    private const IDENTITY = ['nik_ktp', 'npwp', 'bpjs_kesehatan', 'bpjs_ketenagakerjaan'];

    public function title(): string
    {
        return 'Pegawai';
    }

    public function columns(): array
    {
        return [
            'employee_number' => ['required' => true, 'description' => 'NIP (kunci impor)', 'example' => 'EMP-0001'],
            'full_name' => ['required' => true, 'description' => 'Nama lengkap', 'example' => 'Budi Santoso'],
            'company_code' => ['required' => true, 'description' => 'Kode company', 'example' => 'RCI'],
            'employment_status' => ['required' => true, 'description' => 'pkwtt / pkwt / probation / outsourcing / internship', 'example' => 'pkwtt'],
            'join_date' => ['required' => true, 'description' => 'Tanggal bergabung', 'example' => '2021-01-04'],
            'contract_end_date' => ['description' => 'Wajib untuk pkwt'],
            'probation_end_date' => ['description' => 'Wajib untuk probation'],
            'birth_place' => ['description' => 'Tempat lahir', 'example' => 'Bandung'],
            'birth_date' => ['description' => 'Tanggal lahir', 'example' => '1990-05-17'],
            'gender' => ['description' => 'M / F (atau L / P)', 'example' => 'M'],
            'email' => ['description' => 'Email kantor', 'example' => 'budi@contoh.co.id'],
            'phone' => ['description' => 'Telepon', 'example' => '08123456789'],
            'address_domicile' => ['description' => 'Alamat domisili'],
            'ptkp_status' => ['description' => 'TK/0 … K/3', 'example' => 'K/1'],
            'position_code' => ['description' => 'Kode posisi untuk penugasan primer', 'example' => 'POS-RISK-01'],
            'assignment_from' => ['description' => 'Mulai penugasan (default tanggal bergabung)'],
            'username' => ['description' => 'Username akun login yang ditautkan'],
            'nik_ktp' => ['description' => 'NIK KTP 16 digit (butuh izin Data Sensitif)'],
            'npwp' => ['description' => 'NPWP (butuh izin Data Sensitif)'],
            'bpjs_kesehatan' => ['description' => 'No. BPJS Kesehatan (butuh izin Data Sensitif)'],
            'bpjs_ketenagakerjaan' => ['description' => 'No. BPJS Ketenagakerjaan (butuh izin Data Sensitif)'],
        ];
    }

    public function process(array $row, int $line): string
    {
        $row = self::nullable($row);
        foreach (['join_date', 'contract_end_date', 'probation_end_date', 'birth_date', 'assignment_from'] as $field) {
            $row[$field] = self::date($row[$field] ?? null);
        }
        if (isset($row['gender'])) {
            $row['gender'] = ['L' => 'M', 'P' => 'F'][strtoupper($row['gender'])] ?? strtoupper($row['gender']);
        }
        $row['employment_status'] = strtolower((string) ($row['employment_status'] ?? ''));

        $row = $this->validate($row, [
            'employee_number' => ['required', 'max:30', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'full_name' => ['required', 'max:150'],
            'company_code' => ['required'],
            'employment_status' => ['required', 'in:pkwtt,pkwt,probation,outsourcing,internship'],
            'join_date' => ['required', 'date'],
            'contract_end_date' => ['nullable', 'required_if:employment_status,pkwt', 'date', 'after:join_date'],
            'probation_end_date' => ['nullable', 'required_if:employment_status,probation', 'date', 'after:join_date'],
            'birth_place' => ['nullable', 'max:100'], 'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:M,F'], 'email' => ['nullable', 'email', 'max:150'], 'phone' => ['nullable', 'max:30'],
            'address_domicile' => ['nullable', 'max:1000'], 'ptkp_status' => ['nullable', Rule::in(['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'])],
            'position_code' => ['nullable'], 'assignment_from' => ['nullable', 'date'], 'username' => ['nullable'],
            'nik_ktp' => ['nullable', 'digits:16'], 'npwp' => ['nullable', 'regex:/^[0-9.\-]{15,20}$/'],
            'bpjs_kesehatan' => ['nullable', 'digits_between:11,13'], 'bpjs_ketenagakerjaan' => ['nullable', 'digits_between:11,13'],
        ]);

        $identity = array_filter(array_intersect_key($row, array_flip(self::IDENTITY)), fn ($v) => $v !== null);
        if ($identity !== [] && empty($this->options['sensitive'])) {
            $this->fail('Kolom identitas negara hanya boleh diimpor oleh pengguna dengan izin Data Sensitif.');
        }

        $number = strtoupper($row['employee_number']);
        $existing = TenantScope::table('employees')->where('employee_number', $number)->first();
        if ($existing && $existing->deleted_at) {
            $this->fail('NIP '.$number.' milik data pegawai yang sudah dihapus.');
        }
        if (isset($identity['nik_ktp']) && EmployeeIdentity::nikTaken($identity['nik_ktp'], $existing->id ?? null)) {
            $this->fail('NIK KTP sudah dipakai pegawai lain.');
        }

        $userId = null;
        if (! empty($row['username'])) {
            $userId = TenantScope::table('tb_users')->where('username', $row['username'])->value('id') ?? $this->fail('Akun "'.$row['username'].'" tidak ditemukan.');
            $linked = TenantScope::table('employees')->where('user_id', $userId)->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->exists();
            if ($linked) {
                $this->fail('Akun "'.$row['username'].'" sudah ditautkan ke pegawai lain.');
            }
        }

        $data = [
            'company_id' => $this->requireId('corporate', $row['company_code'], 'Company', 'code', 'id_corporate', false),
            'employee_number' => $number,
            'full_name' => $row['full_name'],
            'employment_status' => $row['employment_status'],
            'join_date' => $row['join_date'],
            'contract_end_date' => $row['employment_status'] === 'pkwt' ? $row['contract_end_date'] : null,
            'probation_end_date' => $row['employment_status'] === 'probation' ? $row['probation_end_date'] : null,
            'birth_place' => $row['birth_place'] ?? null, 'birth_date' => $row['birth_date'] ?? null, 'gender' => $row['gender'] ?? null,
            'email' => $row['email'] ?? null, 'phone' => $row['phone'] ?? null, 'address_domicile' => $row['address_domicile'] ?? null,
            'ptkp_status' => $row['ptkp_status'] ?? null,
            'updated_by' => auth()->id(), 'updated_at' => now(),
        ];
        if ($userId) {
            $data['user_id'] = $userId;
        }

        if ($existing) {
            if ($existing->status !== 'active') {
                $this->fail('Pegawai '.$number.' sudah berhenti; perubahan lewat impor tidak diizinkan.');
            }
            TenantScope::table('employees')->where('id', $existing->id)->update($data);
            $after = TenantScope::table('employees')->where('id', $existing->id)->first();
            EmployeeHistory::diff((int) $existing->id, $existing, $after);
            AuditLog::updated('employees', $existing->id, $existing, $after, 'Impor data');
            $id = (int) $existing->id;
            $outcome = 'updated';
        } else {
            $data = TenantScope::fillForInsert('employees', $data + ['status' => 'active', 'created_by' => auth()->id(), 'created_at' => now()]);
            $id = (int) DB::table('employees')->insertGetId($data);
            EmployeeHistory::diff($id, null, TenantScope::table('employees')->where('id', $id)->first());
            AuditLog::created('employees', $id, $data, 'Impor data');
            $outcome = 'created';
        }

        if ($identity !== []) {
            EmployeeIdentity::save($id, $identity);
        }

        if (! empty($row['position_code'])) {
            $positionId = $this->requireId('positions', $row['position_code'], 'Posisi');
            $currentPosition = TenantScope::table('employee_assignments')->where('employee_id', $id)->where('type', 'primary')
                ->whereNull('effective_to')->value('position_id');
            if ((int) $currentPosition !== $positionId) {
                app(AssignEmployee::class)->handle($id, [
                    'position_id' => $positionId, 'type' => 'primary',
                    'effective_from' => $row['assignment_from'] ?? $row['join_date'], 'notes' => 'Impor data',
                ]);
            }
        }

        return $outcome;
    }

    public function countInDatabase(): int
    {
        return TenantScope::table('employees')->whereNull('deleted_at')->count();
    }
}
