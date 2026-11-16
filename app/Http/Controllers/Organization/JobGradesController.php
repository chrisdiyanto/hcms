<?php

namespace App\Http\Controllers\Organization;

use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Query\Builder;

/** Job Grade: acuan rentang grade per job dan struktur gaji (blueprint 5.1 & 14.1). */
class JobGradesController extends MasterDataController
{
    protected function module(): string
    {
        return 'jobgrades';
    }

    protected function table(): string
    {
        return 'job_grades';
    }

    protected function title(): string
    {
        return 'Job Grade';
    }

    protected function baseUrl(): string
    {
        return 'organization/job-grades';
    }

    protected function defaultOrder(): array
    {
        return ['rank', 'asc'];
    }

    protected function columns(): array
    {
        return ['rank' => 'Urutan', 'code' => 'Kode', 'name' => 'Nama', 'level_name' => 'Level', 'is_active' => 'Aktif'];
    }

    protected function searchColumns(): array
    {
        return ['code', 'name', 'job_levels.name'];
    }

    protected function listQuery(): Builder
    {
        return parent::listQuery()
            ->leftJoin('job_levels', 'job_levels.id', '=', 'job_grades.job_level_id')
            ->addSelect('job_levels.name as level_name');
    }

    protected function fields(?object $row): array
    {
        $levels = $this->options('job_levels');

        return [
            'code' => ['label' => 'Kode', 'rules' => ['required', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'], 'col' => 4],
            'name' => ['label' => 'Nama', 'rules' => ['required', 'max:100'], 'col' => 5],
            'rank' => ['label' => 'Urutan', 'type' => 'number', 'rules' => ['required', 'integer', 'min:1', 'max:999'], 'col' => 3],
            'job_level_id' => ['label' => 'Level', 'type' => 'select', 'options' => $levels, 'rules' => ['required', 'integer', $this->existsIn('job_levels')]],
            'is_active' => ['label' => 'Aktif', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function blockDelete(int $id): ?string
    {
        $used = TenantScope::table('positions')->where('grade_id', $id)->whereNull('deleted_at')->exists()
            || TenantScope::table('jobs_catalog')->whereNull('deleted_at')->where(fn ($q) => $q->where('grade_min_id', $id)->orWhere('grade_max_id', $id))->exists();

        return $used ? 'Grade masih dipakai oleh job atau posisi.' : null;
    }
}
