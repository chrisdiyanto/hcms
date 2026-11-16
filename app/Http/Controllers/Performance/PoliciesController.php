<?php

namespace App\Http\Controllers\Performance;

use App\Domain\Performance\Services\PerformancePolicy;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;

/**
 * Kebijakan penilaian kinerja berversi: bobot dimensi (default 60/30/10, override per level
 * jabatan), batas KPI, cap, tabel konversi 1–5, band rating, prorata. Draft diubah HR,
 * diterbitkan oleh pemegang izin Approve.
 */
class PoliciesController extends Controller
{
    use AuthorizesHcmsModule;

    public function __construct(private PerformancePolicy $policies)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'perfpolicies';
    }

    public function index(Request $request)
    {
        $access = $this->authorizeTask('is_view');
        $versions = TenantScope::table('performance_policies')->orderByDesc('version')->get(['id', 'version', 'name', 'status', 'published_at']);
        $selected = $request->integer('version') ?: ($this->policies->draft()->id ?? $this->policies->active()->id ?? null);
        $policy = $selected ? $this->policies->find($selected) : null;

        return view('performance.policies.index', [
            'pageTitle' => 'Kebijakan Kinerja',
            'pageNote' => ($a = $this->policies->active()) ? 'berlaku: '.$a->name : 'belum ada kebijakan yang berlaku',
            'versions' => $versions, 'policy' => $policy,
            'config' => $policy->config ?? PerformancePolicy::DEFAULTS,
            'problems' => $policy && $policy->status === 'draft' ? $this->policies->problems($policy->config) : [],
            'levels' => TenantScope::table('job_levels')->whereNull('deleted_at')->orderBy('code')->pluck('name', 'id')->all(),
            'dimensions' => PerformancePolicy::DIMENSIONS,
            'canEdit' => ! empty($access['is_edit']) && $policy && $policy->status === 'draft',
            'canDraft' => ! empty($access['is_edit']) && ! $this->policies->draft(),
            'canApprove' => ! empty($access['is_approve']),
        ]);
    }

    public function createDraft(Request $request)
    {
        $this->authorizeTask('is_edit');
        $id = $this->policies->createDraft((int) auth()->id());
        $this->logActivity($request, 'Draft kebijakan kinerja #'.$id.' dibuat');

        return redirect('performance/policies?version='.$id)->with('message', 'Draft dibuat dari kebijakan yang berlaku.')->with('status', 'success');
    }

    public function update(Request $request, int $policy)
    {
        $this->authorizeTask('is_edit');
        $levelRule = TenantScope::existsRule('job_levels', 'id');
        $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'config.weights.*' => ['required', 'numeric', 'between:0,100'],
            'config.weight_overrides' => ['nullable', 'array', 'max:20'],
            'config.weight_overrides.*.job_level_id' => ['nullable', 'integer', 'distinct', $levelRule],
            'config.weight_overrides.*.what' => ['nullable', 'numeric', 'between:0,100'],
            'config.weight_overrides.*.how' => ['nullable', 'numeric', 'between:0,100'],
            'config.weight_overrides.*.contribution' => ['nullable', 'numeric', 'between:0,100'],
            'config.kpi.*' => ['required', 'integer', 'between:1,100'],
            'config.cap' => ['required', 'numeric'],
            'config.conversion' => ['required', 'array', 'size:5'],
            'config.conversion.*' => ['required', 'numeric', 'between:0,100'],
            'config.bands' => ['required', 'array', 'min:2', 'max:7'],
            'config.bands.*.code' => ['nullable', 'string', 'max:30'],
            'config.bands.*.label' => ['nullable', 'string', 'max:60'],
            'config.bands.*.min' => ['nullable', 'numeric', 'between:0,100'],
            'config.prorata.*' => ['required', 'integer', 'between:0,365'],
            'config.range_penalty' => ['required', 'numeric', 'between:0,10'],
            'config.milestone_late_penalty' => ['required', 'numeric', 'between:0,1'],
        ], [], ['name' => 'nama kebijakan']);

        $this->policies->updateDraft($policy, $request->input('name'), $request->input('config', []), $request->input('notes'));
        $this->logActivity($request, 'Draft kebijakan kinerja #'.$policy.' diubah');

        return redirect('performance/policies?version='.$policy)->with('message', __('core.note_success'))->with('status', 'success');
    }

    public function publish(Request $request, int $policy)
    {
        $this->authorizeTask('is_approve');
        $this->policies->publish($policy, (int) auth()->id());
        $this->logActivity($request, 'Kebijakan kinerja #'.$policy.' diterbitkan');

        return redirect('performance/policies?version='.$policy)
            ->with('message', 'Kebijakan diterbitkan. Berlaku untuk periode yang dibuka setelah ini.')->with('status', 'success');
    }
}
