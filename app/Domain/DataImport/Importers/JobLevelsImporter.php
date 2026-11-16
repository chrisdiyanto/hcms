<?php

namespace App\Domain\DataImport\Importers;

/** Job level. */
final class JobLevelsImporter extends MasterImporter
{
    public function title(): string
    {
        return 'Job level';
    }

    protected function table(): string
    {
        return 'job_levels';
    }

    public function columns(): array
    {
        return [
            'code' => ['required' => true, 'description' => 'Kode level', 'example' => 'STF'],
            'name' => ['required' => true, 'description' => 'Nama level', 'example' => 'Staff'],
            'rank' => ['required' => true, 'description' => 'Urutan senioritas (1 = paling junior)', 'example' => '1'],
            'is_managerial' => ['description' => 'Ya/Tidak', 'example' => 'Tidak'],
        ];
    }

    protected function map(array $row): array
    {
        $row = $this->validate($row, ['name' => ['required', 'max:100'], 'rank' => ['required', 'integer', 'min:1', 'max:99'], 'is_managerial' => ['nullable']]);

        return ['name' => $row['name'], 'rank' => (int) $row['rank'], 'is_managerial' => self::bool($row['is_managerial'] ?? null)];
    }
}
