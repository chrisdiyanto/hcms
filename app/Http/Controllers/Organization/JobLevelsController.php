<?php

namespace App\Http\Controllers\Organization;

use App\Support\Tenancy\TenantScope;

/** Job Level (Staff, Supervisor, Manager, ...); dipakai bobot skor per level (blueprint 8.5). */
class JobLevelsController extends MasterDataController
{
    protected function module(): string
    {
        return 'joblevels';
    }

    protected function table(): string
    {
        return 'job_levels';
    }

    protected function title(): string
    {
        return 'Job Level';
    }

    protected function baseUrl(): string
    {
        return 'organization/job-levels';
    }

    protected function defaultOrder(): array
    {
        return ['rank', 'asc'];
    }

    protected function columns(): array
    {
        return ['rank' => 'Urutan', 'code' => 'Kode', 'name' => 'Nama', 'is_managerial' => 'Manajerial', 'is_active' => 'Aktif'];
    }

    protected function fields(?object $row): array
    {
        return [
            'code' => ['label' => 'Kode', 'rules' => ['required', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'], 'col' => 4],
            'name' => ['label' => 'Nama', 'rules' => ['required', 'max:100'], 'col' => 5],
            'rank' => ['label' => 'Urutan senioritas', 'type' => 'number', 'rules' => ['required', 'integer', 'min:1', 'max:99'], 'col' => 3, 'help' => '1 = paling junior'],
            'is_managerial' => ['label' => 'Level manajerial', 'type' => 'checkbox', 'help' => 'Dipakai untuk bobot skor kinerja per level'],
            'is_active' => ['label' => 'Aktif', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function blockDelete(int $id): ?string
    {
        return TenantScope::table('job_grades')->where('job_level_id', $id)->whereNull('deleted_at')->exists()
            ? 'Level masih dipakai oleh grade.'
            : null;
    }
}
