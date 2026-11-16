<?php

namespace App\Domain\Performance\Services;

use App\Domain\Performance\Workflow\KpiChangeRequestSubject;
use App\Domain\Workflow\WorkflowEngine;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Perubahan KPI setelah rencana disetujui (blueprint 8.9: hanya lewat change request;
 * KPI lama tersimpan sebagai versi). Usulan berisi daftar KPI lengkap: item ber-id = KPI
 * yang diubah, tanpa id = KPI baru, KPI lama yang tidak ada di usulan dipensiunkan.
 * Disetujui atasan lewat workflow `kpi_change_request`.
 */
final class KpiChangeRequests
{
    /** Kolom KPI yang boleh diubah lewat change request. */
    private const FIELDS = ['title', 'description', 'kpi_type', 'unit', 'target', 'range_min', 'range_max', 'milestones', 'rubric',
        'weight', 'cap', 'strategic_objective_id', 'parent_objective_id', 'contribution_weight'];

    public function __construct(private PerformancePlans $plans, private PerformancePeriods $periods, private PerformanceMonitoring $monitoring) {}

    public function pending(int $planId): ?object
    {
        return TenantScope::table('kpi_change_requests')->where('plan_id', $planId)->where('status', 'pending')->first();
    }

    public function submit(int $planId, array $items, string $reason, int $userId): int
    {
        $plan = $this->plans->plan($planId);
        $period = $this->periods->find((int) $plan->period_id);
        if ($period->status !== 'monitoring' || $plan->status !== 'approved') {
            throw ValidationException::withMessages(['items' => 'Perubahan KPI hanya pada tahap monitoring untuk rencana yang sudah disetujui.']);
        }
        if ($this->pending($plan->id)) {
            throw ValidationException::withMessages(['items' => 'Masih ada pengajuan perubahan KPI yang menunggu keputusan.']);
        }

        $items = array_values($items);
        $active = $this->monitoring->activeObjectives($plan->id)->pluck('id')->map(fn ($v) => (int) $v)->all();
        foreach ($items as $i => $item) {
            if (! empty($item['id']) && ! in_array((int) $item['id'], $active, true)) {
                throw ValidationException::withMessages(["items.$i.title" => 'KPI yang diubah tidak ditemukan pada rencana ini.']);
            }
        }
        $this->plans->validateItems($items, $period, $plan, true);
        if ($this->normalized($items) === $this->normalized($this->current($plan->id))) {
            throw ValidationException::withMessages(['items' => 'Tidak ada perubahan KPI.']);
        }

        return DB::transaction(function () use ($plan, $items, $reason, $userId) {
            $id = DB::table('kpi_change_requests')->insertGetId(TenantScope::fillForInsert('kpi_change_requests', [
                'plan_id' => $plan->id, 'items' => json_encode($items), 'reason' => $reason, 'status' => 'pending',
                'from_version' => $plan->kpi_version, 'requested_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]));
            $instance = app(WorkflowEngine::class)->start(KpiChangeRequestSubject::CODE, $id, $userId, (int) $plan->employee_id);
            DB::table('kpi_change_requests')->where('id', $id)->update(['workflow_instance_id' => $instance->id]);
            AuditLog::created('kpi_change_requests', $id, ['rencana' => $plan->id, 'alasan' => $reason], 'Pengajuan perubahan KPI');

            return $id;
        });
    }

    /** Terapkan usulan yang disetujui; simpan KPI lama sebagai versi. */
    public function apply(int $requestId, ?int $userId): void
    {
        $request = TenantScope::table('kpi_change_requests')->where('id', $requestId)->lockForUpdate()->first() ?? abort(404);
        $plan = $this->plans->plan((int) $request->plan_id);
        $period = $this->periods->find((int) $plan->period_id);
        $calculator = $this->monitoring->calculator($period);
        $items = json_decode((string) $request->items, true) ?: [];
        $now = now();

        DB::table('performance_plan_versions')->insert(TenantScope::fillForInsert('performance_plan_versions', [
            'plan_id' => $plan->id, 'version' => $plan->kpi_version, 'objectives' => json_encode($this->current($plan->id)),
            'reason' => mb_substr($request->reason, 0, 500), 'change_request_id' => $request->id, 'created_by' => $userId, 'created_at' => $now,
        ]));

        $current = $this->monitoring->activeObjectives($plan->id)->keyBy('id');
        $kept = [];
        foreach ($items as $i => $item) {
            $row = $this->row($item) + ['sort_order' => $i, 'updated_at' => $now];
            $existing = ! empty($item['id']) ? $current->get((int) $item['id']) : null;

            if ($existing && $existing->kpi_type === $item['kpi_type']) {
                DB::table('performance_objectives')->where('id', $existing->id)->update($row);
                $kept[] = (int) $existing->id;
                $this->recompute((int) $existing->id, $calculator);

                continue;
            }
            // KPI baru, atau tipe capaian berubah (realisasi lama tidak sebanding) → KPI baru
            DB::table('performance_objectives')->insert(TenantScope::fillForInsert('performance_objectives', $row + ['plan_id' => $plan->id, 'created_at' => $now]));
        }
        TenantScope::table('performance_objectives')->where('plan_id', $plan->id)->whereNull('retired_at')
            ->whereIn('id', $current->keys()->diff($kept)->all())->update(['retired_at' => $now]);

        $next = $plan->kpi_version + 1;
        DB::table('performance_plans')->where('id', $plan->id)->update(['kpi_version' => $next, 'updated_at' => $now]);
        DB::table('kpi_change_requests')->where('id', $request->id)->update(['status' => 'approved', 'to_version' => $next, 'decided_at' => $now, 'updated_at' => $now]);
        AuditLog::updated('performance_plans', $plan->id, ['versi_kpi' => $plan->kpi_version, 'kpi' => $this->plans->snapshot($plan->id)],
            ['versi_kpi' => $next], 'Perubahan KPI disetujui (versi '.$plan->kpi_version.' → '.$next.')');
    }

    public function close(int $requestId, string $status): void
    {
        DB::table('kpi_change_requests')->where('id', $requestId)->where('tenant_id', TenantScope::tenantId())
            ->update(['status' => $status, 'decided_at' => now(), 'updated_at' => now()]);
    }

    /** KPI aktif dalam bentuk item editor (dengan id). */
    public function current(int $planId): array
    {
        return $this->monitoring->activeObjectives($planId)->map(fn ($o) => ['id' => (int) $o->id] + array_intersect_key((array) $o, array_flip(self::FIELDS)))
            ->values()->all();
    }

    /** Hitung ulang capaian realisasi KPI yang target/rentangnya berubah. */
    private function recompute(int $objectiveId, ScoreCalculator $calculator): void
    {
        $kpi = (array) TenantScope::table('performance_objectives')->where('id', $objectiveId)->first();
        $kpi['milestones'] = json_decode((string) $kpi['milestones'], true) ?: [];
        foreach (TenantScope::table('performance_updates')->where('objective_id', $objectiveId)->get() as $u) {
            $actual = match ($kpi['kpi_type']) {
                'milestone' => array_map(fn ($m, $s) => ['weight' => (float) $m['weight'], 'done' => ! empty($s['done']),
                    'late' => ! empty($s['done']) && ! empty($m['due_date']) && ($s['done_date'] ?? '') > $m['due_date']],
                    $kpi['milestones'], array_pad(json_decode((string) $u->milestones, true) ?: [], count($kpi['milestones']), [])),
                'qualitative' => (int) $u->score,
                default => (float) $u->realization,
            };
            DB::table('performance_updates')->where('id', $u->id)->update(['achievement' => round($calculator->achievement($kpi, $actual), 2)]);
        }
    }

    private function row(array $item): array
    {
        $type = $item['kpi_type'];

        return [
            'title' => trim($item['title']), 'description' => trim((string) ($item['description'] ?? '')) ?: null, 'kpi_type' => $type,
            'unit' => trim((string) ($item['unit'] ?? '')) ?: null,
            'target' => in_array($type, ['higher', 'lower'], true) ? $item['target'] : null,
            'range_min' => $type === 'range' ? $item['range_min'] : null, 'range_max' => $type === 'range' ? $item['range_max'] : null,
            'milestones' => $type === 'milestone' ? json_encode(array_values($item['milestones'] ?? [])) : null,
            'rubric' => $type === 'qualitative' ? json_encode(array_values($item['rubric'] ?? [])) : null,
            'weight' => $item['weight'], 'cap' => ($item['cap'] ?? null) ?: null,
            'strategic_objective_id' => ($item['strategic_objective_id'] ?? null) ?: null,
            'parent_objective_id' => ($item['parent_objective_id'] ?? null) ?: null,
            'contribution_weight' => ($item['parent_objective_id'] ?? null) ? (($item['contribution_weight'] ?? null) ?: null) : null,
        ];
    }

    private function normalized(array $items): string
    {
        return json_encode(array_map(fn ($i) => [(int) ($i['id'] ?? 0), trim((string) $i['title']), $i['kpi_type'], (float) ($i['target'] ?? 0),
            (float) ($i['range_min'] ?? 0), (float) ($i['range_max'] ?? 0), (float) $i['weight'], (float) ($i['cap'] ?? 0)], $items));
    }
}
