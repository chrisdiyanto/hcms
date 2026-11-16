<?php

namespace App\Domain\Performance\Workflow;

use App\Domain\Performance\Services\PerformancePlans;
use App\Domain\Performance\Services\ScoreCalculator;
use App\Domain\Workflow\Contracts\WorkflowSubject;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;

/**
 * Persetujuan rencana kinerja oleh atasan (blueprint 8.9: atasan dan pegawai menyepakati KPI).
 * Ditolak = dikembalikan ke pegawai untuk diperbaiki beserta catatannya.
 */
final class PerformancePlanSubject implements WorkflowSubject
{
    public const CODE = 'performance_plan';

    public function title(int $id): string
    {
        $plan = app(PerformancePlans::class)->plan($id);

        return 'Rencana kinerja: '.$plan->full_name.' — '.$plan->position_title;
    }

    public function url(int $id): string
    {
        return 'performance/my-plans/'.$id;
    }

    public function summary(int $id): array
    {
        $lines = [];
        foreach (app(PerformancePlans::class)->objectives($id) as $o) {
            $target = match ($o->kpi_type) {
                'higher', 'lower' => rtrim(rtrim(number_format((float) $o->target, 4, ',', '.'), '0'), ',').' '.$o->unit,
                'range' => (float) $o->range_min.'–'.(float) $o->range_max.' '.$o->unit,
                'milestone' => count($o->milestones).' milestone',
                default => 'rubrik 1–5',
            };
            $lines[] = ['label' => $o->title, 'value' => ScoreCalculator::TYPES[$o->kpi_type].' · target '.trim($target).' · bobot '.(float) $o->weight.'%'];
        }

        return $lines;
    }

    public function onApproved(int $id, object $instance): void
    {
        DB::table('performance_plans')->where('id', $id)->where('tenant_id', TenantScope::tenantId())
            ->update(['status' => 'approved', 'approved_at' => now(), 'updated_at' => now()]);
        AuditLog::updated('performance_plans', $id, ['status' => 'submitted'], ['status' => 'approved'], 'Rencana kinerja disetujui atasan');
    }

    public function onRejected(int $id, object $instance): void
    {
        $note = TenantScope::table('approval_actions')->where('instance_id', $instance->id)->where('action', 'reject')->orderByDesc('id')->value('comment');
        DB::table('performance_plans')->where('id', $id)->where('tenant_id', TenantScope::tenantId())
            ->update(['status' => 'returned', 'return_note' => $note, 'updated_at' => now()]);
        AuditLog::updated('performance_plans', $id, ['status' => 'submitted'], ['status' => 'returned'], 'Rencana kinerja dikembalikan untuk diperbaiki');
    }

    public function onCancelled(int $id, object $instance): void
    {
        DB::table('performance_plans')->where('id', $id)->where('tenant_id', TenantScope::tenantId())
            ->update(['status' => 'draft', 'updated_at' => now()]);
    }
}
