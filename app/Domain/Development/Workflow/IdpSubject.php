<?php

namespace App\Domain\Development\Workflow;

use App\Domain\Development\Idps;
use App\Domain\Workflow\Contracts\WorkflowSubject;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;

/** Persetujuan IDP oleh atasan langsung. Ditolak = dikembalikan ke pegawai beserta catatannya. */
final class IdpSubject implements WorkflowSubject
{
    public const CODE = 'idp';

    public function title(int $id): string
    {
        $idp = app(Idps::class)->find($id);

        return 'IDP '.$idp->period_year.': '.$idp->full_name;
    }

    public function url(int $id): string
    {
        return 'ess/idp/'.$id;
    }

    public function summary(int $id): array
    {
        $idp = app(Idps::class)->find($id);
        $lines = $idp->career_goal ? [['label' => 'Sasaran karier', 'value' => $idp->career_goal]] : [];
        foreach (app(Idps::class)->activities($id) as $a) {
            $lines[] = ['label' => $a->title, 'value' => Idps::TYPES[$a->activity_type].' · '.$a->goal.' · tenggat '.$a->due_date];
        }

        return $lines;
    }

    public function onApproved(int $id, object $instance): void
    {
        app(Idps::class)->markApproved($id);
    }

    public function onRejected(int $id, object $instance): void
    {
        $note = TenantScope::table('approval_actions')->where('instance_id', $instance->id)->where('action', 'reject')->orderByDesc('id')->value('comment');
        TenantScope::table('idps')->where('id', $id)->update(['status' => 'returned', 'return_note' => $note, 'updated_at' => now()]);
        AuditLog::updated('idps', $id, ['status' => 'submitted'], ['status' => 'returned'], 'IDP dikembalikan untuk diperbaiki');
    }

    public function onCancelled(int $id, object $instance): void
    {
        TenantScope::table('idps')->where('id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
    }
}
