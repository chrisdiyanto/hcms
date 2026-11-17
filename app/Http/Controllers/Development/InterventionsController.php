<?php

namespace App\Http\Controllers\Development;

use App\Domain\Development\Idps;
use App\Http\Controllers\Organization\MasterDataController;
use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Query\Builder;

/** Katalog intervensi pengembangan (pelatihan, coaching, mentoring, penugasan, sertifikasi) — Rilis 2 gelombang 2. */
class InterventionsController extends MasterDataController
{
    protected function module(): string
    {
        return 'interventions';
    }

    protected function table(): string
    {
        return 'development_interventions';
    }

    protected function title(): string
    {
        return 'Katalog Intervensi';
    }

    protected function baseUrl(): string
    {
        return 'development/interventions';
    }

    protected function columns(): array
    {
        return ['code' => 'Kode', 'name' => 'Nama', 'type' => 'Jenis', 'provider' => 'Penyedia', 'competency_code' => 'Kompetensi sasaran',
            'cost_per_participant' => 'Biaya/peserta', 'is_active' => 'Aktif'];
    }

    protected function searchColumns(): array
    {
        return ['development_interventions.code', 'development_interventions.name', 'development_interventions.provider'];
    }

    protected function defaultOrder(): array
    {
        return ['development_interventions.code', 'asc'];
    }

    protected function listQuery(): Builder
    {
        return parent::listQuery()->leftJoin('competencies', 'competencies.id', '=', 'development_interventions.competency_id')
            ->addSelect('competencies.code as competency_code');
    }

    protected function formatCell(object $row, string $key): string
    {
        return match ($key) {
            'type' => e(Idps::TYPES[$row->type] ?? $row->type),
            'cost_per_participant' => $row->cost_per_participant !== null ? 'Rp '.number_format((float) $row->cost_per_participant, 0, ',', '.') : '—',
            'competency_code' => $row->competency_code ? e($row->competency_code).($row->target_level ? ' → L'.(int) $row->target_level : '') : '—',
            default => parent::formatCell($row, $key),
        };
    }

    protected function fields(?object $row): array
    {
        $published = TenantScope::table('competency_framework_versions')->where('status', 'published')->value('id');
        $competencies = $published ? TenantScope::table('competencies')->where('framework_version_id', $published)->orderBy('code')->get()
            ->mapWithKeys(fn ($c) => [$c->id => $c->code.' — '.$c->name])->all() : [];

        return [
            'code' => ['label' => 'Kode', 'rules' => ['required', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'], 'col' => 3],
            'name' => ['label' => 'Nama intervensi', 'rules' => ['required', 'max:200'], 'col' => 6],
            'type' => ['label' => 'Jenis', 'type' => 'select', 'options' => Idps::TYPES, 'rules' => ['required', 'in:'.implode(',', array_keys(Idps::TYPES))], 'col' => 3],
            'provider' => ['label' => 'Penyedia', 'rules' => ['nullable', 'max:150'], 'col' => 6, 'help' => 'internal, lembaga pelatihan, platform e-learning'],
            'competency_id' => ['label' => 'Kompetensi sasaran', 'type' => 'select', 'options' => $competencies, 'rules' => ['nullable', 'integer', TenantScope::existsRule('competencies', 'id')], 'col' => 4],
            'target_level' => ['label' => 'Level sasaran', 'type' => 'number', 'rules' => ['nullable', 'integer', 'between:1,5'], 'col' => 2],
            'duration_hours' => ['label' => 'Durasi (jam)', 'type' => 'number', 'rules' => ['nullable', 'numeric', 'min:0', 'max:9999'], 'col' => 3],
            'cost_per_participant' => ['label' => 'Biaya per peserta (Rp)', 'type' => 'number', 'rules' => ['nullable', 'numeric', 'min:0', 'max:999999999999'], 'col' => 3],
            'capacity' => ['label' => 'Kapasitas per kelas', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:1', 'max:100000'], 'col' => 3],
            'description' => ['label' => 'Deskripsi & hasil belajar', 'type' => 'textarea', 'rules' => ['nullable', 'max:2000']],
            'prerequisites' => ['label' => 'Prasyarat', 'type' => 'textarea', 'rules' => ['nullable', 'max:1000']],
            'is_active' => ['label' => 'Aktif (dapat dipilih di IDP)', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function blockDelete(int $id): ?string
    {
        return TenantScope::table('idp_activities')->where('intervention_id', $id)->exists()
            ? 'Intervensi sudah dipakai di IDP. Nonaktifkan saja agar riwayat tetap utuh.'
            : null;
    }
}
