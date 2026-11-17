<?php

namespace App\Http\Controllers\Organization;

use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Posisi: Job × Unit × Company (blueprint 5.1 & 5.3), dengan reporting line
 * solid (atasan langsung, satu) dan dotted (fungsional, boleh lebih dari satu).
 * Setiap perubahan dicatat di position_histories.
 */
class PositionsController extends MasterDataController
{
    public const IMPACTS = [1 => 'Sedang', 2 => 'Tinggi', 3 => 'Sangat tinggi'];

    private const TRACKED = ['code', 'title', 'company_id', 'unit_id', 'job_id', 'grade_id', 'status', 'is_critical', 'critical_impact', 'critical_reason', 'work_location', 'headcount', 'effective_from', 'effective_to'];

    protected function module(): string
    {
        return 'positions';
    }

    protected function table(): string
    {
        return 'positions';
    }

    protected function title(): string
    {
        return 'Posisi';
    }

    protected function baseUrl(): string
    {
        return 'organization/positions';
    }

    protected function columns(): array
    {
        return ['code' => 'Kode', 'title' => 'Nama posisi', 'unit_name' => 'Unit', 'job_title' => 'Job', 'grade_code' => 'Grade', 'reports_to' => 'Atasan langsung', 'status' => 'Status', 'is_critical' => 'Kritis'];
    }

    protected function searchColumns(): array
    {
        return ['code', 'title', 'unit.unit_name', 'jobs_catalog.title'];
    }

    protected function listQuery(): Builder
    {
        return parent::listQuery()
            ->leftJoin('unit', 'unit.id_unit', '=', 'positions.unit_id')
            ->leftJoin('jobs_catalog', 'jobs_catalog.id', '=', 'positions.job_id')
            ->leftJoin('job_grades', 'job_grades.id', '=', 'positions.grade_id')
            ->leftJoin('position_relationships as rel', function ($join) {
                $join->on('rel.position_id', '=', 'positions.id')->where('rel.type', 'solid')->whereNull('rel.effective_to');
            })
            ->leftJoin('positions as boss', 'boss.id', '=', 'rel.reports_to_id')
            ->addSelect('unit.unit_name', 'jobs_catalog.title as job_title', 'job_grades.code as grade_code', 'boss.title as reports_to');
    }

    protected function formatCell(object $row, string $key): string
    {
        if ($key === 'is_critical') {
            return $row->is_critical ? e('Ya — '.(self::IMPACTS[$row->critical_impact] ?? 'dampak belum diisi')) : '—';
        }
        if ($key === 'status') {
            return ['active' => 'Aktif', 'frozen' => 'Dibekukan', 'closed' => 'Ditutup'][$row->status] ?? e($row->status);
        }

        return parent::formatCell($row, $key);
    }

    protected function fields(?object $row): array
    {
        $positions = TenantScope::table('positions')->whereNull('deleted_at')
            ->when($row, fn ($q) => $q->where('id', '!=', $row->id))
            ->orderBy('code')->get()->mapWithKeys(fn ($p) => [$p->id => $p->code.' — '.$p->title])->all();

        $units = TenantScope::table('unit')->orderBy('unit_name')->get()
            ->mapWithKeys(fn ($u) => [$u->id_unit => $u->unit_name.($u->unit_code ? ' ('.$u->unit_code.')' : '')])->all();

        $companies = TenantScope::table('corporate')->orderBy('corporate_name')->pluck('corporate_name', 'id_corporate')->all();
        $relations = $row ? $this->currentRelations($row->id) : ['solid' => null, 'dotted' => []];

        return [
            'code' => ['label' => 'Kode posisi', 'rules' => ['required', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/'], 'col' => 4],
            'title' => ['label' => 'Nama posisi', 'rules' => ['required', 'max:150'], 'col' => 8, 'help' => 'mis. Risk Analyst – Divisi Kredit – Jakarta'],
            'company_id' => ['label' => 'Company', 'type' => 'select', 'options' => $companies, 'rules' => ['required', 'integer', TenantScope::existsRule('corporate', 'id_corporate')], 'col' => 6],
            'unit_id' => ['label' => 'Unit organisasi', 'type' => 'select', 'options' => $units, 'rules' => ['required', 'integer', TenantScope::existsRule('unit', 'id_unit')], 'col' => 6],
            'job_id' => ['label' => 'Job', 'type' => 'select', 'options' => $this->options('jobs_catalog', 'title'), 'rules' => ['required', 'integer', $this->existsIn('jobs_catalog')], 'col' => 6],
            'grade_id' => ['label' => 'Grade', 'type' => 'select', 'options' => $this->options('job_grades'), 'rules' => ['nullable', 'integer', $this->existsIn('job_grades')], 'col' => 6],
            'reports_to_solid' => ['label' => 'Atasan langsung (solid line)', 'type' => 'select', 'options' => $positions, 'rules' => ['nullable', 'integer', $this->existsIn('positions')], 'virtual' => true, 'value' => $relations['solid'], 'col' => 6],
            'reports_to_dotted' => ['label' => 'Atasan fungsional (dotted line)', 'type' => 'multiselect', 'options' => $positions, 'rules' => ['nullable', 'array'], 'virtual' => true, 'value' => $relations['dotted'], 'col' => 6],
            'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Aktif', 'frozen' => 'Dibekukan', 'closed' => 'Ditutup'], 'rules' => ['required', 'in:active,frozen,closed'], 'default' => 'active', 'col' => 4],
            'headcount' => ['label' => 'Jumlah pemangku', 'type' => 'number', 'rules' => ['required', 'integer', 'min:1', 'max:999'], 'default' => 1, 'col' => 4],
            'work_location' => ['label' => 'Lokasi kerja', 'rules' => ['nullable', 'max:150'], 'col' => 4],
            'effective_from' => ['label' => 'Berlaku mulai', 'type' => 'date', 'rules' => ['required', 'date'], 'default' => now()->toDateString(), 'col' => 6],
            'effective_to' => ['label' => 'Berlaku sampai', 'type' => 'date', 'rules' => ['nullable', 'date', 'after_or_equal:effective_from'], 'col' => 6],
            'is_critical' => ['label' => 'Posisi kritis', 'type' => 'checkbox', 'help' => 'Dampak bisnis tinggi × talenta langka; menjadi sasaran succession planning'],
            // Wajib bila posisi kritis: dasar prioritas suksesi & risiko kekosongan (prasyarat P3 CBHRM)
            'critical_impact' => ['label' => 'Tingkat dampak posisi kritis', 'type' => 'select', 'options' => self::IMPACTS,
                'rules' => ['nullable', 'required_if:is_critical,1', 'integer', 'in:1,2,3'], 'col' => 4],
            'critical_reason' => ['label' => 'Alasan posisi kritis', 'type' => 'textarea',
                'rules' => ['nullable', 'required_if:is_critical,1', 'max:500'], 'col' => 8,
                'help' => 'mis. pemegang izin regulator, pendapatan utama, keahlian langka di pasar'],
        ];
    }

    protected function extraRules(Request $request, ?object $row): array
    {
        return [
            // Unit harus milik company yang dipilih
            'unit_id' => [function ($attribute, $value, $fail) use ($request) {
                $company = TenantScope::table('unit')->where('id_unit', $value)->value('id_corporate');
                if ($company !== null && (int) $company !== (int) $request->input('company_id')) {
                    $fail('Unit tidak berada di company yang dipilih.');
                }
            }],
            // Grade dalam rentang job
            'grade_id' => [function ($attribute, $value, $fail) use ($request) {
                if (! $value) {
                    return;
                }
                $job = TenantScope::table('jobs_catalog')->where('id', $request->input('job_id'))->first(['grade_min_id', 'grade_max_id']);
                $ranks = TenantScope::table('job_grades')->whereIn('id', array_filter([$value, $job->grade_min_id ?? null, $job->grade_max_id ?? null]))->pluck('rank', 'id');
                $rank = $ranks[$value] ?? null;
                if (($job->grade_min_id && $rank < ($ranks[$job->grade_min_id] ?? 0)) || ($job->grade_max_id && $rank > ($ranks[$job->grade_max_id] ?? PHP_INT_MAX))) {
                    $fail('Grade di luar rentang grade job.');
                }
            }],
            // Atasan langsung tidak boleh membentuk siklus
            'reports_to_solid' => [function ($attribute, $value, $fail) use ($row) {
                if ($row && $value && $this->wouldCycle((int) $row->id, (int) $value)) {
                    $fail('Atasan langsung membentuk lingkaran pelaporan.');
                }
            }],
            'reports_to_dotted.*' => ['integer', $this->existsIn('positions'), 'different:reports_to_solid'],
        ];
    }

    protected function afterSave(Request $request, int $id, ?object $before): void
    {
        $today = now()->toDateString();
        $current = $this->currentRelations($id);

        // Solid line: akhiri yang lama bila berubah, buat yang baru
        $solid = $request->filled('reports_to_solid') ? (int) $request->input('reports_to_solid') : null;
        if ($solid !== $current['solid']) {
            $this->endRelations($id, 'solid');
            if ($solid) {
                $this->addRelation($id, $solid, 'solid', $today);
            }
        }

        // Dotted line: sinkronkan himpunan
        $dotted = array_values(array_unique(array_map('intval', (array) $request->input('reports_to_dotted', []))));
        $dotted = array_values(array_diff($dotted, [$id, $solid]));
        foreach (array_diff($current['dotted'], $dotted) as $removed) {
            $this->endRelations($id, 'dotted', $removed);
        }
        foreach (array_diff($dotted, $current['dotted']) as $added) {
            $this->addRelation($id, $added, 'dotted', $today);
        }

        $this->recordHistory($id, $before);
    }

    public function store(Request $request)
    {
        return parent::store($this->clearCriticalDetails($request));
    }

    public function update(Request $request, int $id)
    {
        return parent::update($this->clearCriticalDetails($request), $id);
    }

    /** Posisi tidak kritis: dampak & alasan dikosongkan (bukan ditolak) agar mencabut status kritis cukup satu klik. */
    private function clearCriticalDetails(Request $request): Request
    {
        if (! $request->boolean('is_critical')) {
            $request->merge(['critical_impact' => null, 'critical_reason' => null]);
        }

        return $request;
    }

    protected function blockDelete(int $id): ?string
    {
        return TenantScope::table('position_relationships')->where('reports_to_id', $id)->whereNull('effective_to')->exists()
            ? 'Posisi masih menjadi atasan posisi lain. Pindahkan reporting line-nya lebih dulu.'
            : null;
    }

    public function destroy(Request $request, int $id)
    {
        $response = parent::destroy($request, $id);
        if (TenantScope::table('positions')->where('id', $id)->whereNotNull('deleted_at')->exists()) {
            $this->endRelations($id);
            DB::table('position_histories')->insert(TenantScope::fillForInsert('position_histories', [
                'position_id' => $id, 'action' => 'deleted', 'changed_by' => auth()->id(), 'effective_date' => now()->toDateString(),
            ]));
        }

        return $response;
    }

    // ---------------------------------------------------------------- bantuan

    /** @return array{solid: ?int, dotted: int[]} */
    private function currentRelations(int $id): array
    {
        $rows = TenantScope::table('position_relationships')->where('position_id', $id)->whereNull('effective_to')->get(['reports_to_id', 'type']);

        return [
            'solid' => ($s = $rows->firstWhere('type', 'solid')) ? (int) $s->reports_to_id : null,
            'dotted' => $rows->where('type', 'dotted')->pluck('reports_to_id')->map(fn ($v) => (int) $v)->values()->all(),
        ];
    }

    private function addRelation(int $id, int $reportsTo, string $type, string $from): void
    {
        DB::table('position_relationships')->insert(TenantScope::fillForInsert('position_relationships', [
            'position_id' => $id, 'reports_to_id' => $reportsTo, 'type' => $type, 'effective_from' => $from,
            'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    private function endRelations(int $id, ?string $type = null, ?int $reportsTo = null): void
    {
        TenantScope::table('position_relationships')->where('position_id', $id)->whereNull('effective_to')
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($reportsTo, fn ($q) => $q->where('reports_to_id', $reportsTo))
            ->update(['effective_to' => now()->toDateString(), 'updated_at' => now()]);
    }

    /** Apakah menjadikan $boss atasan $id membuat $id menjadi atasan dirinya sendiri. */
    private function wouldCycle(int $id, int $boss): bool
    {
        $seen = [];
        $current = $boss;
        while ($current && ! isset($seen[$current])) {
            if ($current === $id) {
                return true;
            }
            $seen[$current] = true;
            $current = (int) TenantScope::table('position_relationships')->where('position_id', $current)
                ->where('type', 'solid')->whereNull('effective_to')->value('reports_to_id');
        }

        return false;
    }

    private function recordHistory(int $id, ?object $before): void
    {
        $after = TenantScope::table('positions')->where('id', $id)->first();
        $changes = [];
        foreach (self::TRACKED as $column) {
            $old = $before->{$column} ?? null;
            $new = $after->{$column} ?? null;
            if ((string) $old !== (string) $new) {
                $changes[$column] = [$old, $new];
            }
        }

        if ($before && $changes === []) {
            return;
        }

        DB::table('position_histories')->insert(TenantScope::fillForInsert('position_histories', [
            'position_id' => $id,
            'action' => $before ? 'updated' : 'created',
            'changes' => json_encode($changes),
            'effective_date' => $after->effective_from ?? now()->toDateString(),
            'changed_by' => auth()->id(),
        ]));
    }
}
