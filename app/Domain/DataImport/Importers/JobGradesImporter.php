<?php

namespace App\Domain\DataImport\Importers;

/** Job grade (dirujuk ke level). */
final class JobGradesImporter extends MasterImporter
{
    public function title(): string
    {
        return 'Job grade';
    }

    protected function table(): string
    {
        return 'job_grades';
    }

    public function columns(): array
    {
        return [
            'code' => ['required' => true, 'description' => 'Kode grade', 'example' => 'G05'],
            'name' => ['required' => true, 'description' => 'Nama grade', 'example' => 'Grade 5'],
            'rank' => ['required' => true, 'description' => 'Urutan grade', 'example' => '5'],
            'level_code' => ['required' => true, 'description' => 'Kode job level', 'example' => 'STF'],
        ];
    }

    protected function map(array $row): array
    {
        $row = $this->validate($row, ['name' => ['required', 'max:100'], 'rank' => ['required', 'integer', 'min:1', 'max:999'], 'level_code' => ['required']]);

        return ['name' => $row['name'], 'rank' => (int) $row['rank'], 'job_level_id' => $this->requireId('job_levels', $row['level_code'], 'Job level')];
    }
}
