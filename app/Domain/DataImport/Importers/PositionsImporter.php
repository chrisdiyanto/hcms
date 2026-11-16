<?php

namespace App\Domain\DataImport\Importers;

use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;

/**
 * Posisi. Atasan langsung (`reports_to_code`) dipasang di tahap kedua sehingga
 * boleh merujuk posisi yang baru muncul di baris berikutnya.
 */
final class PositionsImporter extends MasterImporter
{
    /** @var array<int, array{id: int, boss: string}> baris → atasan */
    private array $pendingBosses = [];

    private int $currentLine = 0;

    public function title(): string
    {
        return 'Posisi';
    }

    protected function table(): string
    {
        return 'positions';
    }

    protected function codeRules(): array
    {
        return ['required', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/'];
    }

    public function columns(): array
    {
        return [
            'code' => ['required' => true, 'description' => 'Kode posisi', 'example' => 'POS-RISK-01'],
            'title' => ['required' => true, 'description' => 'Nama posisi', 'example' => 'Risk Analyst – Divisi Kredit'],
            'company_code' => ['required' => true, 'description' => 'Kode company', 'example' => 'RCI'],
            'unit_code' => ['required' => true, 'description' => 'Kode unit (milik company tersebut)', 'example' => 'DIV-KRD'],
            'job_code' => ['required' => true, 'description' => 'Kode job', 'example' => 'RISK-AN'],
            'grade_code' => ['description' => 'Kode grade (dalam rentang job)', 'example' => 'G05'],
            'reports_to_code' => ['description' => 'Kode posisi atasan langsung', 'example' => 'POS-HEAD-KRD'],
            'status' => ['description' => 'active / frozen / closed (default active)', 'example' => 'active'],
            'is_critical' => ['description' => 'Ya/Tidak', 'example' => 'Tidak'],
            'headcount' => ['description' => 'Jumlah pemangku (default 1)', 'example' => '1'],
            'work_location' => ['description' => 'Lokasi kerja', 'example' => 'Jakarta'],
            'effective_from' => ['required' => true, 'description' => 'Berlaku mulai (YYYY-MM-DD atau DD/MM/YYYY)', 'example' => '2026-01-01'],
        ];
    }

    public function process(array $row, int $line): string
    {
        $this->currentLine = $line;

        return parent::process($row, $line);
    }

    protected function map(array $row): array
    {
        $row['effective_from'] = self::date($row['effective_from'] ?? null);
        $row = $this->validate($row, [
            'title' => ['required', 'max:150'], 'company_code' => ['required'], 'unit_code' => ['required'], 'job_code' => ['required'],
            'grade_code' => ['nullable'], 'reports_to_code' => ['nullable'], 'status' => ['nullable', 'in:active,frozen,closed'],
            'is_critical' => ['nullable'], 'headcount' => ['nullable', 'integer', 'min:1', 'max:999'], 'work_location' => ['nullable', 'max:150'],
            'effective_from' => ['required', 'date'],
        ]);

        $company = $this->requireId('corporate', $row['company_code'], 'Company', 'code', 'id_corporate', false);
        $unit = $this->requireId('unit', $row['unit_code'], 'Unit', 'unit_code', 'id_unit', false);
        if ((int) TenantScope::table('unit')->where('id_unit', $unit)->value('id_corporate') !== $company) {
            $this->fail('Unit '.$row['unit_code'].' tidak berada di company '.$row['company_code'].'.');
        }
        $job = $this->requireId('jobs_catalog', $row['job_code'], 'Job');
        $grade = isset($row['grade_code']) ? $this->requireId('job_grades', $row['grade_code'], 'Grade') : null;
        if ($grade) {
            $range = TenantScope::table('jobs_catalog')->where('id', $job)->first(['grade_min_id', 'grade_max_id']);
            $ranks = TenantScope::table('job_grades')->whereIn('id', array_filter([$grade, $range->grade_min_id, $range->grade_max_id]))->pluck('rank', 'id');
            if (($range->grade_min_id && $ranks[$grade] < $ranks[$range->grade_min_id]) || ($range->grade_max_id && $ranks[$grade] > $ranks[$range->grade_max_id])) {
                $this->fail('Grade '.$row['grade_code'].' di luar rentang grade job '.$row['job_code'].'.');
            }
        }

        if (! empty($row['reports_to_code'])) {
            $this->pendingBosses[$this->currentLine] = ['code' => strtoupper($row['code'] ?? ''), 'boss' => $row['reports_to_code']];
        }

        return [
            'title' => $row['title'], 'company_id' => $company, 'unit_id' => $unit, 'job_id' => $job, 'grade_id' => $grade,
            'status' => $row['status'] ?? 'active', 'is_critical' => self::bool($row['is_critical'] ?? null),
            'headcount' => (int) ($row['headcount'] ?? 1), 'work_location' => $row['work_location'] ?? null,
            'effective_from' => $row['effective_from'],
        ];
    }

    protected function afterSave(int $id, array $row): void
    {
        if (isset($this->pendingBosses[$this->currentLine])) {
            $this->pendingBosses[$this->currentLine]['id'] = $id;
        }
    }

    /** Pasang reporting line solid; tolak atasan yang tidak ada, diri sendiri, atau siklus. */
    public function finalize(): array
    {
        $errors = [];
        $today = now()->toDateString();

        foreach ($this->pendingBosses as $line => $pending) {
            if (! isset($pending['id'])) {
                continue;
            }
            $bossId = $this->idByCode('positions', $pending['boss']);
            if (! $bossId) {
                $errors[$line][] = 'Posisi atasan "'.$pending['boss'].'" tidak ditemukan.';

                continue;
            }
            if ($bossId === $pending['id']) {
                $errors[$line][] = 'Posisi tidak boleh melapor ke dirinya sendiri.';

                continue;
            }

            $current = TenantScope::table('position_relationships')->where('position_id', $pending['id'])->where('type', 'solid')->whereNull('effective_to')->value('reports_to_id');
            if ((int) $current === $bossId) {
                continue;
            }
            TenantScope::table('position_relationships')->where('position_id', $pending['id'])->where('type', 'solid')->whereNull('effective_to')
                ->update(['effective_to' => $today, 'updated_at' => now()]);
            DB::table('position_relationships')->insert(TenantScope::fillForInsert('position_relationships', [
                'position_id' => $pending['id'], 'reports_to_id' => $bossId, 'type' => 'solid', 'effective_from' => $today,
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        // Deteksi siklus sesudah semua relasi terpasang
        foreach ($this->pendingBosses as $line => $pending) {
            if (isset($pending['id']) && ! isset($errors[$line]) && $this->inCycle($pending['id'])) {
                $errors[$line][] = 'Reporting line membentuk lingkaran (atasan melapor balik ke posisi ini).';
            }
        }

        return $errors;
    }

    private function inCycle(int $start): bool
    {
        $seen = [];
        $current = $start;
        while ($current) {
            if (isset($seen[$current])) {
                return $current === $start || isset($seen[$start]);
            }
            $seen[$current] = true;
            $current = (int) TenantScope::table('position_relationships')->where('position_id', $current)->where('type', 'solid')->whereNull('effective_to')->value('reports_to_id');
        }

        return false;
    }
}
