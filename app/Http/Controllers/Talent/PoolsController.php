<?php

namespace App\Http\Controllers\Talent;

use App\Domain\Competency\Services\PositionReadiness;
use App\Domain\Employee\Services\EmployeeScope;
use App\Domain\Talent\TalentPools;
use App\Domain\Talent\TalentReviews;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Talenta › Talent Pool: pool, nominasi, persetujuan, tinjau ulang, keluar. Data individu sensitif, akses tercatat. */
class PoolsController extends Controller
{
    use AuthorizesHcmsModule;

    public function __construct(private TalentPools $pools)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'talentpools';
    }

    public function index()
    {
        $access = $this->authorizeTask('is_view');

        return view('talent.pools.index', [
            'pageTitle' => 'Talent Pool', 'pageNote' => '',
            'pools' => TenantScope::table('talent_pools')->leftJoin('job_families as f', 'f.id', '=', 'talent_pools.target_job_family_id')
                ->leftJoin('positions as p', 'p.id', '=', 'talent_pools.target_position_id')->orderByDesc('talent_pools.is_active')->orderBy('talent_pools.name')
                ->selectRaw("talent_pools.*, f.name as family_name, p.title as position_title,
                    (SELECT COUNT(*) FROM talent_pool_members m WHERE m.pool_id = talent_pools.id AND m.status = 'active') as active_count,
                    (SELECT COUNT(*) FROM talent_pool_members m WHERE m.pool_id = talent_pools.id AND m.status = 'nominated') as nominated_count")->get(),
            'families' => TenantScope::table('job_families')->orderBy('name')->pluck('name', 'id'),
            'positions' => TenantScope::table('positions')->whereNull('deleted_at')->where('status', 'active')->orderByDesc('is_critical')->orderBy('code')->get(['id', 'code', 'title', 'is_critical']),
            'owners' => TenantScope::table('tb_users')->where('active', 1)->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'canAdd' => ! empty($access['is_add']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTask('is_add');
        $data = $request->validate([
            'code' => ['required', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', TenantScope::uniqueRule('talent_pools', 'code')],
            'name' => ['required', 'string', 'max:150'], 'purpose' => ['required', 'string', 'max:2000'],
            'target_job_family_id' => ['nullable', 'integer', TenantScope::existsRule('job_families', 'id'), 'required_without:target_position_id'],
            'target_position_id' => ['nullable', 'integer', TenantScope::existsRule('positions', 'id')],
            'entry_criteria' => ['required', 'string', 'max:2000'], 'exit_criteria' => ['required', 'string', 'max:2000'],
            'owner_user_id' => ['required', 'integer', TenantScope::existsRule('tb_users', 'id')],
            'review_cycle_months' => ['required', 'integer', 'between:3,24'],
        ], ['target_job_family_id.required_without' => 'Pilih rumpun jabatan atau posisi sasaran.']);
        $id = DB::table('talent_pools')->insertGetId(TenantScope::fillForInsert('talent_pools', ['code' => strtoupper($data['code'])]
            + collect($data)->except('code')->all() + ['is_active' => true, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]));
        AuditLog::created('talent_pools', $id, $data, 'Talent pool dibuat');

        return redirect('talent/pools/'.$id)->with('message', 'Pool dibuat. Nominasikan anggota dari talent review atau secara manual.')->with('status', 'success');
    }

    public function show(int $id)
    {
        $access = $this->authorizeTask('is_view');
        $pool = $this->pools->pool($id) ?? abort(404);
        $sensitive = ! empty($access['is_sensitive']);
        AuditLog::sensitiveViewed('talent_pools', $id, 'Membuka anggota talent pool '.$pool->code);

        return view('talent.pools.show', [
            'pageTitle' => $pool->name, 'pageNote' => $pool->code, 'pool' => $pool, 'sensitive' => $sensitive,
            'members' => $this->pools->members($pool, $sensitive),
            'candidates' => ! empty($access['is_edit']) && $pool->is_active ? $this->pools->candidates($pool) : collect(),
            'employees' => ! empty($access['is_edit']) && $pool->is_active ? EmployeeScope::query()->where('employees.status', 'active')->orderBy('employees.full_name')
                ->get(['employees.id', 'employees.full_name', 'employees.employee_number']) : collect(),
            'events' => $this->pools->events($id),
            'statuses' => TalentPools::STATUS, 'horizons' => PositionReadiness::HORIZONS, 'boxLabels' => TalentReviews::BOX_LABELS, 'levels' => TalentReviews::LEVELS,
            'canEdit' => ! empty($access['is_edit']), 'canApprove' => ! empty($access['is_approve']),
        ]);
    }

    public function nominate(Request $request, int $id)
    {
        $this->authorizeTask('is_edit');
        $data = $request->validate([
            'candidates' => ['nullable', 'array'], 'candidates.*' => ['string', 'regex:/^\d+:\d*$/'],
            'employee_ids' => ['nullable', 'array'], 'employee_ids.*' => ['integer'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $entries = collect($data['candidates'] ?? [])->map(function ($v) {
            [$employee, $entry] = array_pad(explode(':', $v), 2, '');

            return ['employee_id' => (int) $employee, 'entry_id' => $entry !== '' ? (int) $entry : null];
        })->concat(collect($data['employee_ids'] ?? [])->map(fn ($e) => ['employee_id' => (int) $e]))->all();
        if ($entries === []) {
            return back()->withErrors(['employees' => 'Pilih minimal satu pegawai.']);
        }
        $count = $this->pools->nominate($id, $entries, $data['reason'], (int) auth()->id());

        return redirect('talent/pools/'.$id)->with('message', $count.' pegawai dinominasikan dan menunggu persetujuan.')->with('status', 'success');
    }

    public function decide(Request $request, int $id)
    {
        $this->authorizeTask('is_approve');
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer'],
            'decision' => ['required', Rule::in(['active', 'rejected'])],
            'readiness' => ['nullable', Rule::in(array_keys(PositionReadiness::HORIZONS))],
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ], ['ids.required' => 'Pilih minimal satu nominasi.']);
        $ids = TenantScope::table('talent_pool_members')->where('pool_id', $id)->whereIn('id', $data['ids'])->pluck('id')->all();
        $count = $this->pools->decide($ids, $data['decision'], $data['readiness'] ?? null, $data['decision_note'] ?? null, (int) auth()->id());

        return redirect('talent/pools/'.$id)->with('message', $count.' nominasi '.($data['decision'] === 'active' ? 'disetujui' : 'ditolak').'.')->with('status', 'success');
    }

    public function member(Request $request, int $member)
    {
        $this->authorizeTask('is_edit');
        $data = $request->validate([
            'action' => ['required', Rule::in(['review', 'exit'])],
            'readiness' => ['nullable', 'required_if:action,review', Rule::in(array_keys(PositionReadiness::HORIZONS))],
            'note' => ['nullable', 'required_if:action,exit', 'string', 'max:1000'],
        ], ['note.required_if' => 'Alasan keluar wajib diisi.']);
        $data['action'] === 'review'
            ? $this->pools->reviewMember($member, $data['readiness'], $data['note'] ?? null, (int) auth()->id())
            : $this->pools->exit($member, $data['note'], (int) auth()->id());
        $pool = (int) TenantScope::table('talent_pool_members')->where('id', $member)->value('pool_id');

        return redirect('talent/pools/'.$pool)->with('message', $data['action'] === 'review' ? 'Tinjau ulang tersimpan.' : 'Anggota dikeluarkan dari pool.')->with('status', 'success');
    }
}
