<?php

namespace App\Domain\DataImport\Importers;

/** Job family. */
final class JobFamiliesImporter extends MasterImporter
{
    public function title(): string
    {
        return 'Job family';
    }

    protected function table(): string
    {
        return 'job_families';
    }

    public function columns(): array
    {
        return [
            'code' => ['required' => true, 'description' => 'Kode job family', 'example' => 'FIN'],
            'name' => ['required' => true, 'description' => 'Nama', 'example' => 'Finance'],
            'description' => ['description' => 'Deskripsi'],
        ];
    }

    protected function map(array $row): array
    {
        $row = $this->validate($row, ['name' => ['required', 'max:150'], 'description' => ['nullable', 'max:2000']]);

        return ['name' => $row['name'], 'description' => $row['description'] ?? null];
    }
}
