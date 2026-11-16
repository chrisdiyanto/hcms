<?php

namespace App\Http\Controllers\Organization;

use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Query\Builder;

/** Job Family: pengelompokan job & dasar career path (blueprint 5.1). */
class JobFamiliesController extends MasterDataController
{
    protected function module(): string
    {
        return 'jobfamilies';
    }

    protected function table(): string
    {
        return 'job_families';
    }

    protected function title(): string
    {
        return 'Job Family';
    }

    protected function baseUrl(): string
    {
        return 'organization/job-families';
    }

    protected function columns(): array
    {
        return ['code' => 'Kode', 'name' => 'Nama', 'job_count' => 'Jumlah job', 'is_active' => 'Aktif'];
    }

    protected function listQuery(): Builder
    {
        return parent::listQuery()->selectSub(
            TenantScope::table('jobs_catalog')->selectRaw('count(*)')->whereColumn('jobs_catalog.job_family_id', 'job_families.id')->whereNull('jobs_catalog.deleted_at'),
            'job_count'
        );
    }

    protected function fields(?object $row): array
    {
        return [
            'code' => ['label' => 'Kode', 'rules' => ['required', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'], 'col' => 4],
            'name' => ['label' => 'Nama', 'rules' => ['required', 'max:150'], 'col' => 8],
            'description' => ['label' => 'Deskripsi', 'type' => 'textarea', 'rules' => ['nullable', 'max:2000']],
            'is_active' => ['label' => 'Aktif', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function blockDelete(int $id): ?string
    {
        return TenantScope::table('jobs_catalog')->where('job_family_id', $id)->whereNull('deleted_at')->exists()
            ? 'Job family masih dipakai oleh job. Pindahkan atau hapus job-nya lebih dulu.'
            : null;
    }
}
