<?php

namespace App\Http\Controllers\Workflow;

use App\Domain\Workflow\WorkflowEngine;
use App\Domain\Workflow\WorkflowRegistry;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Tugas Saya" (persetujuan yang menunggu keputusan pengguna) dan
 * "Pengajuan Saya". Halaman pribadi: tidak butuh izin modul, tapi setiap
 * tugas hanya bisa dibuka & diputus oleh approver-nya.
 */
class TasksController extends Controller
{
    public function index(Request $request)
    {
        $me = (int) auth()->id();

        $base = fn () => TenantScope::table('approval_tasks')
            ->join('workflow_instances as wi', 'wi.id', '=', 'approval_tasks.instance_id')
            ->leftJoin('tb_users as requester', 'requester.id', '=', 'wi.requester_user_id')
            ->where('approval_tasks.approver_user_id', $me)
            ->select('approval_tasks.*', 'wi.subject_type', 'wi.subject_id', 'wi.status as instance_status', 'wi.submitted_at',
                DB::raw("CONCAT(requester.first_name, ' ', requester.last_name) as requester_name"));

        $pending = $base()->where('approval_tasks.status', 'pending')->orderBy('approval_tasks.due_at')->get();
        $done = $base()->whereIn('approval_tasks.status', ['approved', 'rejected'])->orderByDesc('approval_tasks.acted_at')->limit(30)->get();
        $mine = TenantScope::table('workflow_instances')->where('requester_user_id', $me)->orderByDesc('submitted_at')->limit(30)->get();

        $title = fn ($row) => WorkflowRegistry::subject($row->subject_type)->title((int) $row->subject_id);

        return view('workflow.tasks.index', [
            'pageTitle' => 'Tugas Persetujuan',
            'pageNote' => '',
            'pending' => $pending,
            'done' => $done,
            'mine' => $mine,
            'title' => $title,
            'workflowNames' => array_map(fn ($d) => $d['name'], WorkflowRegistry::definitions()),
        ]);
    }

    public function show(int $task)
    {
        $row = $this->ownTask($task);
        $instance = TenantScope::table('workflow_instances')->where('id', $row->instance_id)->first();
        $subject = WorkflowRegistry::subject($instance->subject_type);

        return view('workflow.tasks.show', [
            'pageTitle' => $row->step_name,
            'pageNote' => $subject->title((int) $instance->subject_id),
            'task' => $row,
            'instance' => $instance,
            'summary' => $subject->summary((int) $instance->subject_id),
            'timeline' => $this->timeline((int) $instance->id),
            'requester' => DB::table('tb_users')->where('id', $instance->requester_user_id)->first(['first_name', 'last_name', 'email']),
            'delegator' => $row->delegated_from_user_id ? DB::table('tb_users')->where('id', $row->delegated_from_user_id)->value('username') : null,
        ]);
    }

    public function approve(Request $request, int $task, WorkflowEngine $engine)
    {
        $this->ownTask($task);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:1000']]);
        $engine->approve($task, (int) auth()->id(), $data['comment'] ?? null);

        return redirect('workflow/tasks')->with('message', 'Pengajuan disetujui.')->with('status', 'success');
    }

    public function reject(Request $request, int $task, WorkflowEngine $engine)
    {
        $this->ownTask($task);
        $data = $request->validate(['comment' => ['required', 'string', 'max:1000']]);
        $engine->reject($task, (int) auth()->id(), $data['comment']);

        return redirect('workflow/tasks')->with('message', 'Pengajuan ditolak.')->with('status', 'success');
    }

    public function cancel(int $instance, WorkflowEngine $engine)
    {
        $engine->cancel($instance, (int) auth()->id());

        return back()->with('message', 'Pengajuan dibatalkan.')->with('status', 'success');
    }

    private function ownTask(int $id): object
    {
        $task = TenantScope::table('approval_tasks')->where('id', $id)->first();
        if (! $task || (int) $task->approver_user_id !== (int) auth()->id()) {
            abort(404);
        }

        return $task;
    }

    private function timeline(int $instanceId)
    {
        return TenantScope::table('approval_actions')
            ->leftJoin('tb_users', 'tb_users.id', '=', 'approval_actions.user_id')
            ->leftJoin('positions', 'positions.id', '=', 'approval_actions.acted_as_position_id')
            ->where('approval_actions.instance_id', $instanceId)
            ->orderBy('approval_actions.id')
            ->get(['approval_actions.*', 'tb_users.username', 'positions.title as position_title']);
    }
}
