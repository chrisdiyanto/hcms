<?php

namespace App\Domain\Performance\Services;

use App\Domain\Performance\Workflow\PerformancePlanSubject;
use App\Domain\Workflow\Services\ApproverResolver;
use App\Domain\Workflow\WorkflowEngine;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Rencana kinerja (blueprint 8.3, 8.8, 8.9 langkah 4): satu rencana per penugasan primer
 * yang beririsan dengan periode. Pegawai menyusun KPI (validasi kebijakan yang dikunci
 * di periode), lalu mengajukan ke atasan lewat workflow; pengajuan = persetujuan pegawai,
 * keputusan atasan = persetujuan atasan. Setelah disetujui KPI hanya berubah lewat
 * change request (gelombang 2).
 */
final class PerformancePlans
{
    public const STATUS = ['draft' => 'Draft', 'submitted' => 'Menunggu persetujuan', 'approved' => 'Disetujui', 'returned' => 'Dikembalikan'];

    public function __construct(private PerformancePeriods $periods, private ApproverResolver $resolver) {}

    /**
     * Rencana pegawai di periode; rencana draft dibuat otomatis untuk setiap penugasan
     * primer yang beririsan dengan periode (bila periode sedang tahap perencanaan).
     */
    public function forEmployee(int $employeeId, object $period, ?int $actorId = null): Collection
    {
        if ($period->status === 'planning') {
            foreach ($this->segments($employeeId, $period) as $segment) {
                $exists = TenantScope::table('performance_plans')->where('period_id', $period->id)->where('assignment_id', $segment->id)->exists();
                if (! $exists) {
                    DB::table('performance_plans')->insertOrIgnore(TenantScope::fillForInsert('performance_plans', [
                        'uuid' => (string) Str::uuid7(), 'period_id' => $period->id, 'employee_id' => $employeeId,
                        'assignment_id' => $segment->id, 'position_id' => $segment->position_id,
                        'start_date' => $segment->start, 'end_date' => $segment->end, 'status' => 'draft',
                        'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
                    ]));
                }
            }
        }

        return TenantScope::table('performance_plans')
            ->join('positions', 'positions.id', '=', 'performance_plans.position_id')
            ->where('performance_plans.period_id', $period->id)->where('performance_plans.employee_id', $employeeId)
            ->orderBy('performance_plans.start_date')
            ->get(['performance_plans.*', 'positions.title as position_title', 'positions.code as position_code']);
    }

    /** Penugasan primer yang beririsan dengan periode, dipotong ke batas periode. */
    public function segments(int $employeeId, object $period): Collection
    {
        return TenantScope::table('employee_assignments')->where('employee_id', $employeeId)->where('type', 'primary')
            ->where('effective_from', '<=', $period->end_date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $period->start_date))
            ->orderBy('effective_from')->get()
            ->map(function ($a) use ($period) {
                $a->start = max($a->effective_from, $period->start_date);
                $a->end = min($a->effective_to ?? $period->end_date, $period->end_date);
                $a->days = Carbon::parse($a->start)->diffInDays(Carbon::parse($a->end)) + 1;

                return $a;
            });
    }

    public function plan(int $id): object
    {
        return TenantScope::table('performance_plans')
            ->join('positions', 'positions.id', '=', 'performance_plans.position_id')
            ->join('employees', 'employees.id', '=', 'performance_plans.employee_id')
            ->where('performance_plans.id', $id)
            ->first(['performance_plans.*', 'positions.title as position_title', 'positions.code as position_code',
                'employees.full_name', 'employees.employee_number', 'employees.user_id']) ?? abort(404);
    }

    public function objectives(int $planId): Collection
    {
        return TenantScope::table('performance_objectives')->where('plan_id', $planId)->orderBy('sort_order')->orderBy('id')->get()
            ->map(function ($o) {
                $o->milestones = json_decode((string) $o->milestones, true) ?: [];
                $o->rubric = json_decode((string) $o->rubric, true) ?: [];

                return $o;
            });
    }

    public function editable(object $plan, object $period): bool
    {
        return $period->status === 'planning' && in_array($plan->status, ['draft', 'returned'], true);
    }

    /** Simpan seluruh KPI rencana (ganti total). Validasi lengkap dilakukan saat diajukan. */
    public function save(int $planId, array $items): void
    {
        $plan = $this->plan($planId);
        $period = $this->periods->find((int) $plan->period_id);
        if (! $this->editable($plan, $period)) {
            throw ValidationException::withMessages(['items' => 'Rencana tidak dapat diubah pada status ini.']);
        }
        $this->validateItems($items, $period, $plan, false);

        DB::transaction(function () use ($plan, $items) {
            $before = $this->snapshot($plan->id);
            TenantScope::table('performance_objectives')->where('plan_id', $plan->id)->delete();
            foreach (array_values($items) as $i => $item) {
                DB::table('performance_objectives')->insert(TenantScope::fillForInsert('performance_objectives', [
                    'plan_id' => $plan->id,
                    'strategic_objective_id' => ($item['strategic_objective_id'] ?? null) ?: null,
                    'parent_objective_id' => ($item['parent_objective_id'] ?? null) ?: null,
                    'contribution_weight' => ($item['parent_objective_id'] ?? null) ? (($item['contribution_weight'] ?? null) ?: null) : null,
                    'title' => trim($item['title']),
                    'description' => trim((string) ($item['description'] ?? '')) ?: null,
                    'kpi_type' => $item['kpi_type'],
                    'unit' => trim((string) ($item['unit'] ?? '')) ?: null,
                    'target' => in_array($item['kpi_type'], ['higher', 'lower'], true) ? $item['target'] : null,
                    'range_min' => $item['kpi_type'] === 'range' ? $item['range_min'] : null,
                    'range_max' => $item['kpi_type'] === 'range' ? $item['range_max'] : null,
                    'milestones' => $item['kpi_type'] === 'milestone' ? json_encode(array_values($item['milestones'] ?? [])) : null,
                    'rubric' => $item['kpi_type'] === 'qualitative' ? json_encode(array_values($item['rubric'] ?? [])) : null,
                    'weight' => $item['weight'],
                    'cap' => ($item['cap'] ?? null) ?: null,
                    'sort_order' => $i,
                    'created_at' => now(), 'updated_at' => now(),
                ]));
            }
            $after = $this->snapshot($plan->id);
            if ($before !== $after) {
                AuditLog::updated('performance_plans', $plan->id, ['kpi' => $before], ['kpi' => $after], 'KPI rencana kinerja '.$plan->full_name);
            }
        });
    }

    /** Ajukan ke atasan (persetujuan pegawai). */
    public function submit(int $planId, int $userId): void
    {
        $plan = $this->plan($planId);
        $period = $this->periods->find((int) $plan->period_id);
        if (! $this->editable($plan, $period)) {
            throw ValidationException::withMessages(['items' => 'Rencana tidak dapat diajukan pada status ini.']);
        }
        $items = $this->objectives($plan->id)->map(fn ($o) => (array) $o)->all();
        $this->validateItems($items, $period, $plan, true);

        DB::transaction(function () use ($plan, $userId) {
            DB::table('performance_plans')->where('id', $plan->id)->update([
                'status' => 'submitted', 'submitted_at' => now(), 'return_note' => null, 'revision' => $plan->revision + 1, 'updated_at' => now(),
            ]);
            $instance = app(WorkflowEngine::class)->start(PerformancePlanSubject::CODE, $plan->id, $userId, (int) $plan->employee_id);
            DB::table('performance_plans')->where('id', $plan->id)->update(['workflow_instance_id' => $instance->id]);
            AuditLog::updated('performance_plans', $plan->id, ['status' => $plan->status], ['status' => 'submitted'], 'Rencana kinerja diajukan');
        });
    }

    /**
     * Validasi KPI terhadap kebijakan periode. $complete = aturan pengajuan (jumlah & total bobot).
     *
     * @throws ValidationException
     */
    public function validateItems(array $items, object $period, object $plan, bool $complete): void
    {
        $rules = $period->policy['kpi'];
        $errors = [];
        $items = array_values($items);
        $parents = $this->parentOptions($plan)->pluck('id')->map(fn ($v) => (int) $v)->all();
        $strategic = TenantScope::table('strategic_objectives')->where('period_id', $period->id)->whereNull('deleted_at')
            ->pluck('id')->map(fn ($v) => (int) $v)->all();

        foreach ($items as $i => $item) {
            $k = "items.$i";
            if (trim((string) ($item['title'] ?? '')) === '') {
                $errors["$k.title"] = 'Nama KPI wajib diisi.';
            }
            if (! array_key_exists($item['kpi_type'] ?? '', ScoreCalculator::TYPES)) {
                $errors["$k.kpi_type"] = 'Tipe KPI tidak valid.';

                continue;
            }
            $w = $item['weight'] ?? null;
            if (! is_numeric($w) || $w < $rules['min_weight'] || $w > $rules['max_weight']) {
                $errors["$k.weight"] = "Bobot per KPI {$rules['min_weight']}–{$rules['max_weight']}%.";
            }
            if (($item['cap'] ?? null) && (! is_numeric($item['cap']) || $item['cap'] < 100 || $item['cap'] > 200)) {
                $errors["$k.cap"] = 'Cap KPI 100–200%.';
            }
            switch ($item['kpi_type']) {
                case 'higher':
                case 'lower':
                    if (! is_numeric($item['target'] ?? null) || $item['target'] < 0) {
                        $errors["$k.target"] = 'Target wajib berupa angka ≥ 0.';
                    }
                    break;
                case 'range':
                    if (! is_numeric($item['range_min'] ?? null) || ! is_numeric($item['range_max'] ?? null) || $item['range_min'] >= $item['range_max']) {
                        $errors["$k.range_min"] = 'Rentang wajib diisi dan batas bawah < batas atas.';
                    }
                    break;
                case 'milestone':
                    $ms = array_values(array_filter($item['milestones'] ?? [], fn ($m) => trim((string) ($m['name'] ?? '')) !== ''));
                    $sum = array_sum(array_map(fn ($m) => (float) ($m['weight'] ?? 0), $ms));
                    if (count($ms) < 2 || round($sum, 2) != 100.0) {
                        $errors["$k.milestones"] = 'Minimal 2 milestone dengan total bobot 100%.';
                    }
                    break;
                case 'qualitative':
                    $rubric = array_values($item['rubric'] ?? []);
                    if (count(array_filter($rubric, fn ($r) => trim((string) $r) !== '')) !== 5) {
                        $errors["$k.rubric"] = 'Rubrik wajib berdeskripsi untuk level 1–5.';
                    }
                    break;
            }
            if (($item['strategic_objective_id'] ?? null) && ! in_array((int) $item['strategic_objective_id'], $strategic, true)) {
                $errors["$k.strategic_objective_id"] = 'Sasaran strategis tidak ditemukan di periode ini.';
            }
            if ($item['parent_objective_id'] ?? null) {
                if (! in_array((int) $item['parent_objective_id'], $parents, true)) {
                    $errors["$k.parent_objective_id"] = 'KPI induk harus KPI atasan yang sudah disetujui.';
                }
                $cw = $item['contribution_weight'] ?? null;
                if (! is_numeric($cw) || $cw <= 0 || $cw > 100) {
                    $errors["$k.contribution_weight"] = 'Bobot kontribusi ke KPI induk 1–100%.';
                }
            }
        }

        if ($complete) {
            $count = count($items);
            if ($count < $rules['min_count'] || $count > $rules['max_count']) {
                $errors['items'] = "Jumlah KPI harus {$rules['min_count']}–{$rules['max_count']} (saat ini $count).";
            }
            $total = round(array_sum(array_map(fn ($i) => (float) ($i['weight'] ?? 0), $items)), 2);
            if ($total != 100.0) {
                $errors['items_total'] = 'Total bobot KPI harus 100% (saat ini '.rtrim(rtrim(number_format($total, 2, ',', '.'), '0'), ',').'%).';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** KPI atasan (pemangku posisi di atas) yang sudah disetujui di periode yang sama — untuk cascading. */
    public function parentOptions(object $plan): Collection
    {
        $requester = (int) ($plan->user_id ?? 0);
        $supervisorUsers = collect($this->resolver->escalationFrom((int) $plan->position_id, $requester))->pluck('user_id')->all();
        if ($supervisorUsers === []) {
            return collect();
        }

        return TenantScope::table('performance_objectives')
            ->join('performance_plans', 'performance_plans.id', '=', 'performance_objectives.plan_id')
            ->join('employees', 'employees.id', '=', 'performance_plans.employee_id')
            ->where('performance_plans.period_id', $plan->period_id)->where('performance_plans.status', 'approved')
            ->whereIn('employees.user_id', $supervisorUsers)
            ->orderBy('performance_objectives.sort_order')
            ->get(['performance_objectives.id', 'performance_objectives.title', 'employees.full_name']);
    }

    public function snapshot(int $planId): string
    {
        return $this->objectives($planId)->map(fn ($o) => $o->title.' ['.$o->kpi_type.'] '.rtrim(rtrim((string) $o->weight, '0'), '.').'%')->implode('; ');
    }
}
