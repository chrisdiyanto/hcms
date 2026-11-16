<?php

namespace App\Domain\Workflow;

use App\Domain\Workflow\Services\ApproverResolver;
use App\Domain\Workflow\Services\Notifier;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Mesin approval berjenjang (blueprint 3.2 & modul 5).
 *
 * - Setiap langkah membuat tugas untuk satu atau beberapa kandidat approver;
 *   keputusan pertama menutup langkah (tugas kandidat lain dibatalkan).
 * - Setiap keputusan menyimpan identitas, waktu, dan posisi pemberi keputusan.
 * - Semua perubahan status berjalan dalam transaksi database (blueprint 17.4).
 * - Tugas melewati SLA dieskalasi ke atasan posisi approver (maks. 3 kali).
 */
final class WorkflowEngine
{
    public const MAX_ESCALATION = 3;

    public function __construct(private ApproverResolver $resolver, private Notifier $notifier) {}

    public function start(string $code, int $subjectId, int $requesterUserId, ?int $requesterEmployeeId = null): object
    {
        return DB::transaction(function () use ($code, $subjectId, $requesterUserId, $requesterEmployeeId) {
            $version = WorkflowRegistry::currentVersion($code);

            $id = DB::table('workflow_instances')->insertGetId(TenantScope::fillForInsert('workflow_instances', [
                'version_id' => $version->id,
                'subject_type' => $code,
                'subject_id' => $subjectId,
                'requester_user_id' => $requesterUserId,
                'requester_employee_id' => $requesterEmployeeId,
                'status' => 'pending',
                'current_step' => 0,
                'submitted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            $instance = $this->instance($id);
            $this->audit($instance, null, 'submit', $requesterUserId, null, null);
            $this->activateStep($instance, 0);

            return $this->instance($id);
        });
    }

    public function approve(int $taskId, int $userId, ?string $comment = null): object
    {
        return $this->decide($taskId, $userId, 'approve', $comment);
    }

    public function reject(int $taskId, int $userId, string $comment): object
    {
        if (trim($comment) === '') {
            throw ValidationException::withMessages(['comment' => 'Alasan penolakan wajib diisi.']);
        }

        return $this->decide($taskId, $userId, 'reject', $comment);
    }

    public function cancel(int $instanceId, int $userId): void
    {
        DB::transaction(function () use ($instanceId, $userId) {
            $instance = TenantScope::table('workflow_instances')->where('id', $instanceId)->lockForUpdate()->first() ?? abort(404);
            if ((int) $instance->requester_user_id !== $userId) {
                abort(403, 'Hanya pengaju yang dapat membatalkan pengajuan.');
            }
            if ($instance->status !== 'pending') {
                throw ValidationException::withMessages(['instance' => 'Pengajuan sudah diputus.']);
            }

            $this->closeOpenTasks($instanceId, 'cancelled');
            $this->finish($instance, 'cancelled');
            $this->audit($instance, null, 'cancel', $userId, null, null);
            WorkflowRegistry::subject($instance->subject_type)->onCancelled((int) $instance->subject_id, $instance);
        });
    }

    /** Eskalasi tugas yang melewati SLA, lintas tenant. @return int jumlah tugas dieskalasi */
    public function escalateOverdue(): int
    {
        $count = 0;
        $context = app(TenantContext::class);

        $overdue = DB::table('approval_tasks')->where('status', 'pending')->whereNotNull('due_at')->where('due_at', '<', now())->get(['id', 'tenant_id']);
        foreach ($overdue as $row) {
            $context->runAs((int) $row->tenant_id, null, function () use ($row, &$count) {
                DB::transaction(function () use ($row, &$count) {
                    if ($this->escalate((int) $row->id)) {
                        $count++;
                    }
                });
            });
        }

        return $count;
    }

    // ------------------------------------------------------------- internal

    private function decide(int $taskId, int $userId, string $action, ?string $comment): object
    {
        return DB::transaction(function () use ($taskId, $userId, $action, $comment) {
            $task = TenantScope::table('approval_tasks')->where('id', $taskId)->lockForUpdate()->first() ?? abort(404);
            if ((int) $task->approver_user_id !== $userId) {
                abort(403, 'Tugas ini bukan milik Anda.');
            }
            $instance = TenantScope::table('workflow_instances')->where('id', $task->instance_id)->lockForUpdate()->first();
            if ($task->status !== 'pending' || $instance->status !== 'pending') {
                throw ValidationException::withMessages(['task' => 'Tugas sudah diputus atau pengajuan tidak lagi aktif.']);
            }
            if ((int) $instance->requester_user_id === $userId) {
                abort(403, 'Anda tidak dapat memutus pengajuan Anda sendiri.');
            }

            $positionId = $this->resolver->positionOf($userId);
            TenantScope::table('approval_tasks')->where('id', $taskId)->update([
                'status' => $action === 'approve' ? 'approved' : 'rejected',
                'acted_at' => now(), 'comment' => $comment, 'updated_at' => now(),
            ]);
            // Kandidat lain pada langkah yang sama tidak perlu memutus lagi
            TenantScope::table('approval_tasks')->where('instance_id', $instance->id)->where('step_index', $task->step_index)
                ->where('status', 'pending')->update(['status' => 'cancelled', 'updated_at' => now()]);
            $this->audit($instance, $taskId, $action, $userId, $positionId, $comment);

            $subject = WorkflowRegistry::subject($instance->subject_type);

            if ($action === 'reject') {
                $this->finish($instance, 'rejected');
                $subject->onRejected((int) $instance->subject_id, $instance);
                $this->notifyRequester($instance, 'Pengajuan ditolak', 'Alasan: '.$comment);

                return $this->instance($instance->id);
            }

            $steps = $this->steps($instance);
            $next = (int) $task->step_index + 1;
            if ($next < count($steps)) {
                TenantScope::table('workflow_instances')->where('id', $instance->id)->update(['current_step' => $next, 'updated_at' => now()]);
                $this->activateStep($this->instance($instance->id), $next);
            } else {
                $this->finish($instance, 'approved');
                $this->audit($instance, null, 'complete', null, null, null);
                $subject->onApproved((int) $instance->subject_id, $this->instance($instance->id));
                $this->notifyRequester($instance, 'Pengajuan disetujui', $subject->title((int) $instance->subject_id));
            }

            return $this->instance($instance->id);
        });
    }

    private function activateStep(object $instance, int $index): void
    {
        $step = $this->steps($instance)[$index];
        $candidates = $this->resolver->resolve($step['approver'] ?? [], (int) $instance->requester_user_id);

        if ($candidates === []) {
            throw ValidationException::withMessages(['workflow' => 'Tidak ada approver untuk langkah "'.$step['name'].'". Hubungi administrator.']);
        }

        foreach ($candidates as $candidate) {
            $this->createTask($instance, $index, $step, $candidate, 0);
        }
    }

    private function createTask(object $instance, int $index, array $step, array $candidate, int $level): int
    {
        $hours = (int) ($step['sla_hours'] ?? 0);
        $id = DB::table('approval_tasks')->insertGetId(TenantScope::fillForInsert('approval_tasks', [
            'instance_id' => $instance->id,
            'step_index' => $index,
            'step_name' => Str::limit((string) $step['name'], 150, ''),
            'approver_user_id' => $candidate['user_id'],
            'approver_position_id' => $candidate['position_id'],
            'delegated_from_user_id' => $candidate['delegated_from'],
            'status' => 'pending',
            'escalation_level' => $level,
            'due_at' => $hours > 0 ? now()->addHours($hours) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        if ($candidate['delegated_from']) {
            $this->audit($instance, $id, 'delegate', $candidate['delegated_from'], null, 'Didelegasikan ke pengguna #'.$candidate['user_id']);
        }

        $subject = WorkflowRegistry::subject($instance->subject_type);
        $this->notifier->send($candidate['user_id'], 'Persetujuan menunggu: '.$step['name'], $subject->title((int) $instance->subject_id), 'workflow/tasks/'.$id);

        return $id;
    }

    private function escalate(int $taskId): bool
    {
        $task = TenantScope::table('approval_tasks')->where('id', $taskId)->where('status', 'pending')->lockForUpdate()->first();
        if (! $task) {
            return false;
        }
        $instance = $this->instance((int) $task->instance_id);
        $step = $this->steps($instance)[$task->step_index];
        $hours = max(1, (int) ($step['sla_hours'] ?? 24));

        $candidates = $task->escalation_level < self::MAX_ESCALATION
            ? $this->resolver->escalationFrom($task->approver_position_id ? (int) $task->approver_position_id : null, (int) $instance->requester_user_id)
            : [];
        $candidates = array_values(array_filter($candidates, fn ($c) => $c['user_id'] !== (int) $task->approver_user_id));

        if ($candidates === []) {
            // Tidak ada tingkat lebih tinggi: tetap di approver sekarang, perpanjang tenggat, ingatkan
            TenantScope::table('approval_tasks')->where('id', $taskId)->update(['due_at' => now()->addHours($hours), 'updated_at' => now()]);
            $this->notifier->send((int) $task->approver_user_id, 'Pengingat: persetujuan melewati batas waktu', $task->step_name, 'workflow/tasks/'.$taskId, 'fa fa-clock');

            return false;
        }

        TenantScope::table('approval_tasks')->where('id', $taskId)->update(['status' => 'escalated', 'updated_at' => now()]);
        $this->audit($instance, $taskId, 'escalate', null, null, 'Melewati SLA '.$hours.' jam');
        foreach ($candidates as $candidate) {
            $this->createTask($instance, (int) $task->step_index, $step, $candidate, (int) $task->escalation_level + 1);
        }

        return true;
    }

    private function closeOpenTasks(int $instanceId, string $status): void
    {
        TenantScope::table('approval_tasks')->where('instance_id', $instanceId)->where('status', 'pending')
            ->update(['status' => $status, 'updated_at' => now()]);
    }

    private function finish(object $instance, string $status): void
    {
        TenantScope::table('workflow_instances')->where('id', $instance->id)
            ->update(['status' => $status, 'completed_at' => now(), 'updated_at' => now()]);
    }

    private function audit(object $instance, ?int $taskId, string $action, ?int $userId, ?int $positionId, ?string $comment): void
    {
        DB::table('approval_actions')->insert(TenantScope::fillForInsert('approval_actions', [
            'instance_id' => $instance->id, 'task_id' => $taskId, 'user_id' => $userId,
            'acted_as_position_id' => $positionId, 'action' => $action, 'comment' => $comment, 'created_at' => now(),
        ]));
    }

    private function notifyRequester(object $instance, string $title, string $note): void
    {
        $this->notifier->send((int) $instance->requester_user_id, $title, $note,
            WorkflowRegistry::subject($instance->subject_type)->url((int) $instance->subject_id), 'fa fa-check-circle');
    }

    private function steps(object $instance): array
    {
        return json_decode((string) TenantScope::table('workflow_versions')->where('id', $instance->version_id)->value('steps'), true) ?: [];
    }

    private function instance(int $id): object
    {
        return TenantScope::table('workflow_instances')->where('id', $id)->first() ?? abort(404);
    }
}
