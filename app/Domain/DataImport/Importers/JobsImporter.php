<?php

namespace App\Domain\DataImport\Importers;

use App\Support\Tenancy\TenantScope;

/** Job (dirujuk ke family dan rentang grade). */
final class JobsImporter extends MasterImporter
{
    public function title(): string
    {
        return 'Job';
    }

    protected function table(): string
    {
        return 'jobs_catalog';
    }

    public function columns(): array
    {
        return [
            'code' => ['required' => true, 'description' => 'Kode job', 'example' => 'RISK-AN'],
            'title' => ['required' => true, 'description' => 'Nama job', 'example' => 'Risk Analyst'],
            'family_code' => ['required' => true, 'description' => 'Kode job family', 'example' => 'FIN'],
            'grade_min_code' => ['description' => 'Kode grade terendah', 'example' => 'G04'],
            'grade_max_code' => ['description' => 'Kode grade tertinggi', 'example' => 'G06'],
            'purpose' => ['description' => 'Tujuan jabatan'],
            'responsibilities' => ['description' => 'Tanggung jawab utama'],
        ];
    }

    protected function map(array $row): array
    {
        $row = $this->validate($row, ['title' => ['required', 'max:150'], 'family_code' => ['required'], 'purpose' => ['nullable', 'max:2000'], 'responsibilities' => ['nullable', 'max:5000'],
            'grade_min_code' => ['nullable'], 'grade_max_code' => ['nullable']]);

        $min = isset($row['grade_min_code']) ? $this->requireId('job_grades', $row['grade_min_code'], 'Grade terendah') : null;
        $max = isset($row['grade_max_code']) ? $this->requireId('job_grades', $row['grade_max_code'], 'Grade tertinggi') : null;
        if ($min && $max) {
            $ranks = TenantScope::table('job_grades')->whereIn('id', [$min, $max])->pluck('rank', 'id');
            if (($ranks[$max] ?? 0) < ($ranks[$min] ?? 0)) {
                $this->fail('Grade tertinggi tidak boleh di bawah grade terendah.');
            }
        }

        return [
            'title' => $row['title'], 'job_family_id' => $this->requireId('job_families', $row['family_code'], 'Job family'),
            'grade_min_id' => $min, 'grade_max_id' => $max, 'purpose' => $row['purpose'] ?? null, 'responsibilities' => $row['responsibilities'] ?? null,
        ];
    }
}
