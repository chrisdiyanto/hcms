<?php

namespace App\Domain\Performance\Workflow;

use App\Domain\Performance\Services\KpiChangeRequests;
use App\Domain\Performance\Services\PerformancePlans;
use App\Domain\Workflow\Contracts\WorkflowSubject;
use App\Support\Tenancy\TenantScope;

/** Persetujuan perubahan KPI oleh atasan selama tahap monitoring. */
final class KpiChangeRequestSubject implements WorkflowSubject
{
    public const CODE = 'kpi_change_request';

    public function title(int $id): string
    {
        $plan = app(PerformancePlans::class)->plan((int) $this->row($id)->plan_id);

        return 'Perubahan KPI: '.$plan->full_name.' — '.$plan->position_title;
    }

    public function url(int $id): string
    {
        return 'performance/plans/'.$this->row($id)->plan_id.'/monitoring';
    }

    public function summary(int $id): array
    {
        $row = $this->row($id);
        $before = collect(app(KpiChangeRequests::class)->current((int) $row->plan_id))->keyBy('id');
        $lines = [];
        $seen = [];
        foreach (json_decode((string) $row->items, true) ?: [] as $item) {
            $old = ! empty($item['id']) ? $before->get((int) $item['id']) : null;
            $seen[] = (int) ($item['id'] ?? 0);
            $lines[] = ['label' => $item['title'], 'old' => $old ? $this->describe($old) : '(KPI baru)', 'new' => $this->describe($item)];
        }
        foreach ($before as $id2 => $old) {
            if (! in_array((int) $id2, $seen, true)) {
                $lines[] = ['label' => $old['title'], 'old' => $this->describe($old), 'new' => '(dihapus)'];
            }
        }
        $lines[] = ['label' => 'Alasan', 'value' => $row->reason];

        return $lines;
    }

    public function onApproved(int $id, object $instance): void
    {
        app(KpiChangeRequests::class)->apply($id, (int) auth()->id() ?: null);
    }

    public function onRejected(int $id, object $instance): void
    {
        app(KpiChangeRequests::class)->close($id, 'rejected');
    }

    public function onCancelled(int $id, object $instance): void
    {
        app(KpiChangeRequests::class)->close($id, 'cancelled');
    }

    private function describe(array $i): string
    {
        $target = match ($i['kpi_type']) {
            'higher', 'lower' => 'target '.rtrim(rtrim(number_format((float) $i['target'], 4, ',', '.'), '0'), ','),
            'range' => 'rentang '.(float) $i['range_min'].'–'.(float) $i['range_max'],
            'milestone' => 'milestone',
            default => 'rubrik',
        };

        return $target.' · bobot '.(float) $i['weight'].'%';
    }

    private function row(int $id): object
    {
        return TenantScope::table('kpi_change_requests')->where('id', $id)->first() ?? abort(404);
    }
}
