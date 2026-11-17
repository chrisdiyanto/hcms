<?php

namespace App\Domain\Development;

use App\Domain\Competency\Services\JobFitCalculator;
use App\Domain\Employee\Services\EmployeeScope;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Training Need Analysis (laporan CBHRM 5; kamus metrik §5, K5).
 *
 * - Saran otomatis (status `suggested`) dari gap kompetensi ≤ −1 pada posisi primer saat ini dan dari
 *   PIP yang berjalan. Saran BUKAN kebutuhan TNA sampai disetujui HR.
 * - Pengajuan manusia (`proposed`): permintaan pegawai/atasan (`request`) atau kebutuhan organisasi dari HR.
 * - Tinjauan HR: setujui/tolak (izin Approve; pengaju tidak boleh memutus pengajuannya sendiri).
 * - Prioritas K5: kritikalitas × |gap| (maks 4) × faktor strategis (1,5 bila terkait sasaran strategis);
 *   tinggi ≥ 6, sedang 3–5,99, rendah < 3. Kebutuhan dari PIP selalu tinggi. Tanpa gap kompetensi,
 *   prioritas ditetapkan pengaju/peninjau.
 */
class TrainingNeeds
{
    public const STATUS = ['suggested' => 'Saran otomatis', 'proposed' => 'Diajukan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak',
        'planned' => 'Direncanakan', 'closed' => 'Selesai'];

    public const SOURCES = ['gap' => 'Gap kompetensi', 'performance' => 'Kinerja / PIP', 'organization' => 'Prioritas organisasi', 'request' => 'Permintaan'];

    public const PRIORITIES = ['high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah'];

    public const STRATEGIC_FACTOR = 1.5;

    public const MAX_GAP = 4.0;

    /** K5 */
    public static function score(?int $criticality, ?float $gap, bool $strategic): ?float
    {
        if ($criticality === null || $gap === null || $gap >= 0) {
            return null;
        }

        return round($criticality * min(abs($gap), self::MAX_GAP) * ($strategic ? self::STRATEGIC_FACTOR : 1.0), 2);
    }

    public static function level(float $score): string
    {
        return $score >= 6 ? 'high' : ($score >= 3 ? 'medium' : 'low');
    }

    // ------------------------------------------------------------ saran otomatis

    /**
     * Perbarui saran otomatis tahun rencana (semua pegawai tenant; idempoten). Saran yang sumbernya
     * sudah tidak ada (gap tertutup, PIP selesai) dihapus selama masih berstatus saran.
     *
     * @return array{gap: int, performance: int, removed: int}
     */
    public function refreshSuggestions(int $year): array
    {
        $today = now()->toDateString();
        $gaps = TenantScope::table('competency_gaps')
            ->join('employees', 'employees.id', '=', 'competency_gaps.employee_id')
            ->join('employee_assignments as a', function ($j) use ($today) {
                $j->on('a.employee_id', '=', 'competency_gaps.employee_id')->on('a.position_id', '=', 'competency_gaps.position_id')
                    ->where('a.type', 'primary')->where('a.effective_from', '<=', $today)
                    ->where(fn ($w) => $w->whereNull('a.effective_to')->orWhere('a.effective_to', '>=', $today));
            })
            ->join('competencies', 'competencies.id', '=', 'competency_gaps.competency_id')
            ->whereNull('employees.deleted_at')->where('employees.status', 'active')
            ->where('competency_gaps.gap', '<=', JobFitCalculator::CRITICAL_GAP)
            ->get(['competency_gaps.employee_id', 'competency_gaps.competency_id', 'competency_gaps.competency_code', 'competency_gaps.actual',
                'competency_gaps.required_level', 'competency_gaps.criticality', 'competency_gaps.gap', 'competencies.name']);
        $pips = TenantScope::table('performance_improvement_plans')->whereIn('status', ['active', 'extended'])
            ->get(['id', 'employee_id', 'objectives', 'end_date', 'owner_user_id']);

        return DB::transaction(function () use ($year, $gaps, $pips) {
            $existing = TenantScope::table('training_needs')->where('period_year', $year)->whereNotNull('dedupe_key')
                ->get(['id', 'employee_id', 'dedupe_key', 'status'])->keyBy(fn ($r) => $r->employee_id.'|'.$r->dedupe_key);
            $seen = [];
            $upsert = function (int $employeeId, string $key, array $data) use ($year, $existing, &$seen) {
                $seen[$employeeId.'|'.$key] = true;
                $row = $existing->get($employeeId.'|'.$key);
                if ($row && $row->status !== 'suggested') {
                    return 0; // sudah ditinjau manusia: tidak disentuh
                }
                if ($row) {
                    TenantScope::table('training_needs')->where('id', $row->id)->update($data + ['updated_at' => now()]);

                    return 0;
                }
                DB::table('training_needs')->insert(TenantScope::fillForInsert('training_needs', $data + [
                    'employee_id' => $employeeId, 'period_year' => $year, 'dedupe_key' => $key, 'status' => 'suggested',
                    'created_at' => now(), 'updated_at' => now(),
                ]));

                return 1;
            };

            $created = ['gap' => 0, 'performance' => 0];
            foreach ($gaps as $g) {
                $score = self::score((int) $g->criticality, (float) $g->gap, false);
                $created['gap'] += $upsert((int) $g->employee_id, 'gap:'.$g->competency_code, [
                    'competency_id' => $g->competency_id, 'competency_code' => $g->competency_code, 'topic' => $g->name,
                    'source' => 'gap', 'source_ref_type' => 'competency_gaps', 'source_ref_id' => null,
                    'current_level' => $g->actual, 'target_level' => $g->required_level, 'criticality' => $g->criticality, 'gap' => $g->gap,
                    'priority_score' => $score, 'priority' => self::level($score),
                    'reason' => sprintf('Gap %s pada %s (level %s dari wajib %d, kritikalitas %d).', number_format((float) $g->gap, 2, ',', '.'),
                        $g->name, number_format((float) $g->actual, 2, ',', '.'), $g->required_level, $g->criticality),
                ]);
            }
            foreach ($pips as $p) {
                $created['performance'] += $upsert((int) $p->employee_id, 'pip:'.$p->id, [
                    'topic' => 'Perbaikan kinerja (PIP)', 'source' => 'performance', 'source_ref_type' => 'performance_improvement_plans',
                    'source_ref_id' => $p->id, 'priority_score' => null, 'priority' => 'high', 'due_date' => $p->end_date, 'owner_user_id' => $p->owner_user_id,
                    'reason' => 'Rencana perbaikan kinerja berjalan: '.mb_strimwidth((string) $p->objectives, 0, 400, '…'),
                ]);
            }
            $stale = $existing->filter(fn ($r, $k) => $r->status === 'suggested' && ! isset($seen[$k]))->pluck('id');
            if ($stale->isNotEmpty()) {
                TenantScope::table('training_needs')->whereIn('id', $stale)->delete();
            }

            return $created + ['removed' => $stale->count()];
        });
    }

    // ------------------------------------------------------------ pengajuan

    /**
     * Kebutuhan diajukan manusia (pegawai/atasan = request, HR = organization).
     *
     * @param  array{competency_id: ?int, topic: ?string, reason: string, evidence: ?string, priority: ?string, strategic_objective_id: ?int, suggested_intervention: ?string, due_date: ?string, estimated_cost: ?float}  $data
     */
    public function propose(int $employeeId, int $year, string $source, array $data, int $userId): int
    {
        $competency = ! empty($data['competency_id'])
            ? TenantScope::table('competencies')->where('id', $data['competency_id'])->first(['id', 'code', 'name']) : null;
        $gap = $competency ? TenantScope::table('competency_gaps')->where('employee_id', $employeeId)->where('competency_code', $competency->code)
            ->orderBy('gap')->first(['actual', 'required_level', 'criticality', 'gap']) : null;
        $strategic = ! empty($data['strategic_objective_id']);
        $score = self::score($gap ? (int) $gap->criticality : null, $gap && $gap->gap !== null ? (float) $gap->gap : null, $strategic);

        $id = DB::table('training_needs')->insertGetId(TenantScope::fillForInsert('training_needs', [
            'employee_id' => $employeeId, 'period_year' => $year, 'source' => $source,
            'competency_id' => $competency->id ?? null, 'competency_code' => $competency->code ?? null,
            'topic' => trim((string) ($data['topic'] ?? '')) ?: ($competency->name ?? 'Kebutuhan pengembangan'),
            'current_level' => $gap->actual ?? null, 'target_level' => $gap->required_level ?? null, 'criticality' => $gap->criticality ?? null, 'gap' => $gap->gap ?? null,
            'strategic_objective_id' => $data['strategic_objective_id'] ?? null,
            'priority_score' => $score, 'priority' => $score !== null ? self::level($score) : ($data['priority'] ?? 'medium'),
            'reason' => $data['reason'], 'evidence' => $data['evidence'] ?? null,
            'suggested_intervention' => $data['suggested_intervention'] ?? null, 'due_date' => $data['due_date'] ?? null,
            'estimated_cost' => $data['estimated_cost'] ?? null,
            'status' => 'proposed', 'requested_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]));
        AuditLog::created('training_needs', $id, ['pegawai' => $employeeId, 'sumber' => $source, 'topik' => $data['topic'] ?? ($competency->name ?? null)], 'Kebutuhan pelatihan diajukan');

        return $id;
    }

    // ------------------------------------------------------------ tinjauan HR

    /** Setujui atau tolak (status suggested/proposed). Pengaju tidak boleh memutus pengajuannya sendiri (SoD). */
    public function review(array $ids, string $decision, ?string $note, int $userId): int
    {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['decision' => 'Keputusan tidak dikenal.']);
        }
        if ($decision === 'rejected' && trim((string) $note) === '') {
            throw ValidationException::withMessages(['review_note' => 'Alasan penolakan wajib diisi.']);
        }
        $rows = $this->visible()->whereIn('training_needs.id', $ids)->whereIn('training_needs.status', ['suggested', 'proposed'])
            ->get(['training_needs.id', 'training_needs.requested_by', 'training_needs.status']);
        if ($rows->contains(fn ($r) => (int) $r->requested_by === $userId)) {
            throw ValidationException::withMessages(['ids' => 'Anda tidak dapat memutus kebutuhan yang Anda ajukan sendiri.']);
        }

        return DB::transaction(function () use ($rows, $decision, $note, $userId) {
            foreach ($rows as $row) {
                TenantScope::table('training_needs')->where('id', $row->id)->update([
                    'status' => $decision, 'reviewed_by' => $userId, 'reviewed_at' => now(), 'review_note' => $note, 'updated_at' => now(),
                ]);
                AuditLog::updated('training_needs', $row->id, ['status' => $row->status], ['status' => $decision, 'catatan' => $note],
                    $decision === 'approved' ? 'Kebutuhan pelatihan disetujui' : 'Kebutuhan pelatihan ditolak');
            }

            return $rows->count();
        });
    }

    /** Ubah rincian tindak lanjut; sasaran strategis menghitung ulang prioritas K5 bila ada gap. */
    public function updateDetails(int $id, array $data): void
    {
        $row = $this->visible()->where('training_needs.id', $id)->first(['training_needs.*']) ?? abort(404);
        if (in_array($row->status, ['rejected', 'closed'], true)) {
            throw ValidationException::withMessages(['status' => 'Kebutuhan yang ditolak atau selesai tidak dapat diubah.']);
        }
        $score = self::score($row->criticality !== null ? (int) $row->criticality : null, $row->gap !== null ? (float) $row->gap : null,
            ! empty($data['strategic_objective_id']));
        if ($row->source === 'performance') {
            $data['priority'] = 'high';
        } elseif ($score !== null) {
            $data['priority'] = self::level($score);
        }
        $data['priority_score'] = $row->source === 'performance' ? null : $score;
        TenantScope::table('training_needs')->where('id', $id)->update($data + ['updated_at' => now()]);
        AuditLog::updated('training_needs', $id, collect((array) $row)->only(array_keys($data))->all(), $data, 'Rincian kebutuhan pelatihan diubah');
    }

    // ------------------------------------------------------------ baca

    /** Kebutuhan pegawai dalam data scope pengguna. */
    public function visible()
    {
        return EmployeeScope::apply(TenantScope::table('training_needs')
            ->join('employees', 'employees.id', '=', 'training_needs.employee_id'), 'training_needs.employee_id');
    }

    /** Pegawai aktif yang posisinya melapor langsung (solid line) ke posisi yang dijabat pengguna. */
    public function directReports(int $userId): Collection
    {
        $today = now()->toDateString();
        $held = TenantScope::table('employee_assignments')->join('employees', 'employees.id', '=', 'employee_assignments.employee_id')
            ->where('employees.user_id', $userId)->where('employees.status', 'active')->whereIn('employee_assignments.type', ['primary', 'acting'])
            ->where('employee_assignments.effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('employee_assignments.effective_to')->orWhere('employee_assignments.effective_to', '>=', $today))
            ->pluck('employee_assignments.position_id');
        $positions = TenantScope::table('position_relationships')->whereIn('reports_to_id', $held)->where('type', 'solid')->whereNull('effective_to')->pluck('position_id');

        return TenantScope::table('employees')->join('employee_assignments as a', 'a.employee_id', '=', 'employees.id')
            ->whereIn('a.position_id', $positions)->where('a.type', 'primary')
            ->where('a.effective_from', '<=', $today)->where(fn ($w) => $w->whereNull('a.effective_to')->orWhere('a.effective_to', '>=', $today))
            ->where('employees.status', 'active')->whereNull('employees.deleted_at')
            ->orderBy('employees.full_name')->get(['employees.id', 'employees.full_name', 'employees.employee_number'])->unique('id')->values();
    }
}
