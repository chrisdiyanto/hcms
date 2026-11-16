<?php

namespace App\Http\Controllers\Workflow;

use App\Domain\Workflow\WorkflowRegistry;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Security\Roles;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Konfigurasi langkah alur per tenant. Menyimpan = versi baru; pengajuan
 * yang sedang berjalan tetap memakai versi lama (blueprint 17.1 versioning).
 */
class DefinitionsController extends Controller
{
    use AuthorizesHcmsModule;

    protected function moduleName(): string
    {
        return 'workflows';
    }

    public function index()
    {
        $this->authorizeTask('is_view');

        $rows = [];
        foreach (WorkflowRegistry::definitions() as $code => $def) {
            $version = WorkflowRegistry::currentVersion($code);
            $rows[] = ['code' => $code, 'name' => $def['name'], 'version' => $version->version, 'steps' => json_decode($version->steps, true)];
        }

        return view('workflow.definitions.index', [
            'pageTitle' => 'Alur Persetujuan', 'pageNote' => '', 'rows' => $rows,
            'canEdit' => $this->can('is_edit'), 'roles' => $this->roleOptions(), 'types' => WorkflowRegistry::APPROVER_TYPES,
        ]);
    }

    public function edit(string $code)
    {
        $this->authorizeTask('is_edit');
        $def = WorkflowRegistry::definitions()[$code] ?? abort(404);
        $version = WorkflowRegistry::currentVersion($code);

        return view('workflow.definitions.edit', [
            'pageTitle' => $def['name'], 'pageNote' => 'versi '.$version->version,
            'code' => $code, 'steps' => old('steps', json_decode($version->steps, true)),
            'types' => WorkflowRegistry::APPROVER_TYPES, 'roles' => $this->roleOptions(),
            'positions' => TenantScope::table('positions')->whereNull('deleted_at')->orderBy('code')->get(['id', 'code', 'title'])
                ->mapWithKeys(fn ($p) => [$p->id => $p->code.' — '.$p->title]),
        ]);
    }

    public function update(Request $request, string $code)
    {
        $this->authorizeTask('is_edit');
        WorkflowRegistry::definitions()[$code] ?? abort(404);

        $data = $request->validate([
            'steps' => ['required', 'array', 'min:1', 'max:6'],
            'steps.*.name' => ['required', 'string', 'max:150'],
            'steps.*.approver.type' => ['required', Rule::in(array_keys(WorkflowRegistry::APPROVER_TYPES))],
            'steps.*.approver.levels' => ['nullable', 'integer', 'min:1', 'max:5'],
            'steps.*.approver.role' => ['nullable', 'required_if:steps.*.approver.type,role', Rule::in(array_keys(Roles::definitions()))],
            'steps.*.approver.position_id' => ['nullable', 'required_if:steps.*.approver.type,position', 'integer', TenantScope::existsRule('positions', 'id')],
            'steps.*.sla_hours' => ['required', 'integer', 'min:1', 'max:720'],
        ]);

        $steps = array_map(function ($s) {
            $type = $s['approver']['type'];
            $approver = ['type' => $type] + match ($type) {
                'supervisor' => ['levels' => (int) ($s['approver']['levels'] ?? 1)],
                'role' => ['role' => $s['approver']['role']],
                'position' => ['position_id' => (int) $s['approver']['position_id']],
            };

            return ['name' => $s['name'], 'approver' => $approver, 'sla_hours' => (int) $s['sla_hours']];
        }, array_values($data['steps']));

        $definitionId = TenantScope::table('workflow_definitions')->where('code', $code)->value('id') ?? WorkflowRegistry::currentVersion($code)->definition_id;
        WorkflowRegistry::saveVersion((int) $definitionId, $steps, (int) auth()->id());
        $this->logActivity($request, 'Alur '.$code.' diperbarui');

        return redirect('workflow/definitions')->with('message', 'Alur disimpan sebagai versi baru.')->with('status', 'success');
    }

    private function roleOptions(): array
    {
        return array_map(fn ($r) => $r['name'], Roles::definitions());
    }
}
