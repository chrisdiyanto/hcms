<?php

namespace App\Http\Controllers\Organization;

use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Job: standar jabatan (job description, persyaratan, rentang grade).
 * Profil kompetensi job ditambahkan di Fase 2 dan diwarisi semua posisinya.
 */
class JobsController extends MasterDataController
{
    public const REQUIREMENT_TYPES = [
        'education' => 'Pendidikan',
        'experience' => 'Pengalaman',
        'certification' => 'Sertifikasi',
        'skill' => 'Keahlian',
        'other' => 'Lainnya',
    ];

    protected function module(): string
    {
        return 'jobs';
    }

    protected function table(): string
    {
        return 'jobs_catalog';
    }

    protected function title(): string
    {
        return 'Job';
    }

    protected function baseUrl(): string
    {
        return 'organization/jobs';
    }

    protected function columns(): array
    {
        return ['code' => 'Kode', 'title' => 'Nama job', 'family_name' => 'Job family', 'grade_range' => 'Rentang grade', 'position_count' => 'Posisi', 'is_active' => 'Aktif'];
    }

    protected function searchColumns(): array
    {
        return ['code', 'title', 'job_families.name'];
    }

    protected function listQuery(): Builder
    {
        return parent::listQuery()
            ->leftJoin('job_families', 'job_families.id', '=', 'jobs_catalog.job_family_id')
            ->leftJoin('job_grades as gmin', 'gmin.id', '=', 'jobs_catalog.grade_min_id')
            ->leftJoin('job_grades as gmax', 'gmax.id', '=', 'jobs_catalog.grade_max_id')
            ->addSelect('job_families.name as family_name')
            ->selectRaw("CONCAT_WS(' – ', gmin.code, gmax.code) as grade_range")
            ->selectSub(
                TenantScope::table('positions')->selectRaw('count(*)')->whereColumn('positions.job_id', 'jobs_catalog.id')->whereNull('positions.deleted_at'),
                'position_count'
            );
    }

    protected function fields(?object $row): array
    {
        $grades = $this->options('job_grades');

        return [
            'code' => ['label' => 'Kode', 'rules' => ['required', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'], 'col' => 3],
            'title' => ['label' => 'Nama job', 'rules' => ['required', 'max:150'], 'col' => 5],
            'job_family_id' => ['label' => 'Job family', 'type' => 'select', 'options' => $this->options('job_families'), 'rules' => ['required', 'integer', $this->existsIn('job_families')], 'col' => 4],
            'grade_min_id' => ['label' => 'Grade terendah', 'type' => 'select', 'options' => $grades, 'rules' => ['nullable', 'integer', $this->existsIn('job_grades')], 'col' => 6],
            'grade_max_id' => ['label' => 'Grade tertinggi', 'type' => 'select', 'options' => $grades, 'rules' => ['nullable', 'integer', $this->existsIn('job_grades')], 'col' => 6],
            'purpose' => ['label' => 'Tujuan jabatan', 'type' => 'textarea', 'rules' => ['nullable', 'max:2000']],
            'responsibilities' => ['label' => 'Tanggung jawab utama', 'type' => 'textarea', 'rules' => ['nullable', 'max:5000'], 'help' => 'Satu tanggung jawab per baris'],
            'is_active' => ['label' => 'Aktif', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function extraRules(Request $request, ?object $row): array
    {
        return [
            'grade_max_id' => [function ($attribute, $value, $fail) use ($request) {
                $min = $request->input('grade_min_id');
                if ($value && $min) {
                    $ranks = TenantScope::table('job_grades')->whereIn('id', [$min, $value])->pluck('rank', 'id');
                    if (($ranks[$value] ?? 0) < ($ranks[$min] ?? 0)) {
                        $fail('Grade tertinggi tidak boleh di bawah grade terendah.');
                    }
                }
            }],
            'requirements' => ['nullable', 'array', 'max:30'],
            'requirements.*.type' => ['required', 'in:'.implode(',', array_keys(self::REQUIREMENT_TYPES))],
            'requirements.*.description' => ['required', 'string', 'max:255'],
            'requirements.*.is_mandatory' => ['nullable', 'boolean'],
        ];
    }

    protected function afterSave(Request $request, int $id, ?object $before): void
    {
        // Persyaratan disimpan ulang utuh sesuai urutan di form; perubahan dicatat di audit
        $snapshot = fn () => TenantScope::table('job_requirements')->where('job_id', $id)->orderBy('sort_order')->get(['type', 'description', 'is_mandatory'])
            ->map(fn ($r) => $r->type.': '.$r->description.($r->is_mandatory ? ' (wajib)' : ''))->all();
        $beforeRequirements = $snapshot();
        TenantScope::table('job_requirements')->where('job_id', $id)->delete();
        foreach (array_values((array) $request->input('requirements', [])) as $i => $req) {
            DB::table('job_requirements')->insert(TenantScope::fillForInsert('job_requirements', [
                'job_id' => $id,
                'type' => $req['type'],
                'description' => $req['description'],
                'is_mandatory' => ! empty($req['is_mandatory']),
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $afterRequirements = $snapshot();
        if ($beforeRequirements !== $afterRequirements) {
            AuditLog::updated('job_requirements', $id, ['persyaratan' => implode(' | ', $beforeRequirements)], ['persyaratan' => implode(' | ', $afterRequirements)], 'Persyaratan job #'.$id);
        }
    }

    protected function formPartial(): ?string
    {
        return 'organization.jobs.requirements';
    }

    protected function formData(?object $row): array
    {
        $requirements = $row
            ? TenantScope::table('job_requirements')->where('job_id', $row->id)->orderBy('sort_order')
                ->get(['type', 'description', 'is_mandatory'])->map(fn ($r) => ['type' => $r->type, 'description' => $r->description, 'is_mandatory' => (bool) $r->is_mandatory])->all()
            : [];

        return ['requirements' => old('requirements', $requirements), 'types' => self::REQUIREMENT_TYPES];
    }

    protected function blockDelete(int $id): ?string
    {
        return TenantScope::table('positions')->where('job_id', $id)->whereNull('deleted_at')->exists()
            ? 'Job masih dipakai oleh posisi.'
            : null;
    }
}
