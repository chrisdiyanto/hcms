<?php

namespace App\Http\Controllers\Employee;

use App\Domain\Employee\Services\EmployeeScope;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Data riwayat pegawai: keluarga, kontak darurat, pendidikan, pengalaman, sertifikasi. */
class EmployeeRecordsController extends Controller
{
    use AuthorizesHcmsModule;

    public const TYPES = [
        'families' => [
            'table' => 'employee_families', 'title' => 'Keluarga',
            'fields' => [
                'name' => ['label' => 'Nama', 'rules' => ['required', 'max:150']],
                'relationship' => ['label' => 'Hubungan', 'type' => 'select', 'options' => ['spouse' => 'Pasangan', 'child' => 'Anak', 'parent' => 'Orang tua', 'sibling' => 'Saudara', 'other' => 'Lainnya'], 'rules' => ['required', 'in:spouse,child,parent,sibling,other']],
                'birth_date' => ['label' => 'Tanggal lahir', 'type' => 'date', 'rules' => ['nullable', 'date', 'before_or_equal:today']],
                'gender' => ['label' => 'Jenis kelamin', 'type' => 'select', 'options' => ['M' => 'Laki-laki', 'F' => 'Perempuan'], 'rules' => ['nullable', 'in:M,F']],
                'is_dependent' => ['label' => 'Tanggungan', 'type' => 'checkbox', 'rules' => ['nullable', 'boolean']],
            ],
        ],
        'emergency-contacts' => [
            'table' => 'employee_emergency_contacts', 'title' => 'Kontak darurat',
            'fields' => [
                'name' => ['label' => 'Nama', 'rules' => ['required', 'max:150']],
                'relationship' => ['label' => 'Hubungan', 'rules' => ['required', 'max:50']],
                'phone' => ['label' => 'Telepon', 'rules' => ['required', 'max:30']],
            ],
        ],
        'education' => [
            'table' => 'employee_education', 'title' => 'Pendidikan',
            'fields' => [
                'level' => ['label' => 'Jenjang', 'type' => 'select', 'options' => ['sd' => 'SD', 'smp' => 'SMP', 'sma' => 'SMA/SMK', 'd1' => 'D1', 'd2' => 'D2', 'd3' => 'D3', 'd4' => 'D4', 's1' => 'S1', 's2' => 'S2', 's3' => 'S3', 'other' => 'Lainnya'], 'rules' => ['required', 'in:sd,smp,sma,d1,d2,d3,d4,s1,s2,s3,other']],
                'institution' => ['label' => 'Institusi', 'rules' => ['required', 'max:150']],
                'major' => ['label' => 'Jurusan', 'rules' => ['nullable', 'max:150']],
                'graduation_year' => ['label' => 'Tahun lulus', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:1950', 'max:2100']],
            ],
        ],
        'experiences' => [
            'table' => 'employee_experiences', 'title' => 'Pengalaman kerja',
            'fields' => [
                'company_name' => ['label' => 'Perusahaan', 'rules' => ['required', 'max:150']],
                'position' => ['label' => 'Jabatan', 'rules' => ['required', 'max:150']],
                'start_date' => ['label' => 'Mulai', 'type' => 'date', 'rules' => ['nullable', 'date']],
                'end_date' => ['label' => 'Selesai', 'type' => 'date', 'rules' => ['nullable', 'date', 'after_or_equal:start_date']],
            ],
        ],
        'certifications' => [
            'table' => 'employee_certifications', 'title' => 'Sertifikasi',
            'fields' => [
                'name' => ['label' => 'Nama sertifikasi', 'rules' => ['required', 'max:150']],
                'issuer' => ['label' => 'Penerbit', 'rules' => ['nullable', 'max:150']],
                'certificate_number' => ['label' => 'Nomor', 'rules' => ['nullable', 'max:100']],
                'issued_at' => ['label' => 'Terbit', 'type' => 'date', 'rules' => ['nullable', 'date']],
                'expires_at' => ['label' => 'Kedaluwarsa', 'type' => 'date', 'rules' => ['nullable', 'date', 'after_or_equal:issued_at']],
            ],
        ],
    ];

    protected function moduleName(): string
    {
        return 'employees';
    }

    public function store(Request $request, int $employee, string $type)
    {
        $this->authorizeTask('is_edit');
        $def = $this->definition($type);
        $this->ensureVisible($employee);

        $rules = array_map(fn ($f) => $f['rules'], $def['fields']);
        $data = $request->validate($rules);
        foreach ($def['fields'] as $name => $field) {
            if (($field['type'] ?? null) === 'checkbox') {
                $data[$name] = $request->boolean($name);
            }
        }

        DB::table($def['table'])->insert(TenantScope::fillForInsert($def['table'], $data + [
            'employee_id' => $employee, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
        ]));
        $this->logActivity($request, 'Tambah '.$def['title'].' pegawai #'.$employee);

        return redirect('employees/'.$employee.'#'.$type)->with('message', __('core.note_success'))->with('status', 'success');
    }

    public function destroy(Request $request, int $employee, string $type, int $record)
    {
        $this->authorizeTask('is_edit');
        $def = $this->definition($type);
        $this->ensureVisible($employee);

        TenantScope::table($def['table'])->where('id', $record)->where('employee_id', $employee)->delete() ?: abort(404);
        $this->logActivity($request, 'Hapus '.$def['title'].' #'.$record.' pegawai #'.$employee);

        return redirect('employees/'.$employee.'#'.$type)->with('message', __('core.note_success_delete'))->with('status', 'success');
    }

    private function definition(string $type): array
    {
        return self::TYPES[$type] ?? abort(404);
    }

    private function ensureVisible(int $employee): void
    {
        if (! EmployeeScope::canSee($employee)) {
            abort(404);
        }
    }
}
