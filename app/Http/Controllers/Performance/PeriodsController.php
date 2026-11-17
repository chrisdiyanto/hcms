<?php

namespace App\Http\Controllers\Performance;

use App\Domain\Employee\Services\EmployeeScope;
use App\Domain\Performance\Services\PerformancePeriods;
use App\Domain\Performance\Services\PerformancePlans;
use App\Domain\Performance\Services\PerformancePolicy;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Periode kinerja (buka = kunci kebijakan), sasaran strategis yang di-cascade
 * (korporat → direktorat → divisi → tim), dan rekap status rencana kinerja pegawai
 * sesuai data scope pengguna.
 */
class PeriodsController extends Controller
{
    use AuthorizesHcmsModule;

    public const LEVELS = ['corporate' => 'Korporat', 'directorate' => 'Direktorat', 'division' => 'Divisi', 'team' => 'Tim'];

    public function __construct(private PerformancePeriods $periods, private PerformancePlans $plans)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'perfperiods';
    }

    public function index()
    {
        $access = $this->authorizeTask('is_view');

        return view('performance.periods.index', [
            'pageTitle' => 'Periode Kinerja',
            'pageNote' => 'buka periode, kunci kebijakan, tetapkan sasaran strategis',
            'periods' => TenantScope::table('performance_periods')->orderByDesc('start_date')->get(),
            'statuses' => PerformancePeriods::STATUS,
            'canAdd' => ! empty($access['is_add']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTask('is_add');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'alpha_dash', TenantScope::uniqueRule('performance_periods', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'planning_deadline' => ['nullable', 'date', 'after_or_equal:start_date', 'before_or_equal:end_date'],
        ], [], ['code' => 'kode', 'name' => 'nama', 'start_date' => 'tanggal mulai', 'end_date' => 'tanggal selesai', 'planning_deadline' => 'batas perencanaan']);
        $id = $this->periods->create($data, (int) auth()->id());
        $this->logActivity($request, 'Periode kinerja #'.$id.' dibuat');

        return redirect('performance/periods/'.$id)->with('message', 'Periode dibuat. Buka periode untuk mengunci kebijakan dan memulai perencanaan.')->with('status', 'success');
    }

    public function show(int $period)
    {
        $access = $this->authorizeTask('is_view');
        $row = $this->periods->find($period);

        $objectives = TenantScope::table('strategic_objectives')->leftJoin('unit', 'unit.id_unit', '=', 'strategic_objectives.unit_id')
            ->where('strategic_objectives.period_id', $row->id)->whereNull('strategic_objectives.deleted_at')
            ->orderByRaw("FIELD(strategic_objectives.level, 'corporate', 'directorate', 'division', 'team')")->orderBy('strategic_objectives.code')
            ->get(['strategic_objectives.*', 'unit.unit_name']);

        // Rekap rencana pegawai aktif dalam cakupan data (satu baris per rencana)
        // Rekap dihitung di SQL; daftar dipaginasi & bisa difilter status/nama (uji beban: 10.000 baris dalam satu halaman)
        $base = fn () => EmployeeScope::query()->where('employees.status', 'active')
            ->leftJoin('performance_plans', function ($j) use ($row) {
                $j->on('performance_plans.employee_id', '=', 'employees.id')->where('performance_plans.period_id', $row->id);
            });
        $planCounts = $base()->selectRaw("COALESCE(performance_plans.status, 'none') as st, COUNT(*) as n")->groupBy('st')->pluck('n', 'st')->map(fn ($n) => (int) $n);
        $status = (string) request('plan_status');
        $q = trim((string) request('q'));
        $plans = $base()->leftJoin('positions', 'positions.id', '=', 'performance_plans.position_id')
            ->when($status === 'none', fn ($w) => $w->whereNull('performance_plans.id'))
            ->when(array_key_exists($status, PerformancePlans::STATUS), fn ($w) => $w->where('performance_plans.status', $status))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('employees.full_name', 'like', '%'.$q.'%')->orWhere('employees.employee_number', $q)))
            ->orderBy('employees.full_name')
            ->select(['employees.id as employee_id', 'employees.full_name', 'employees.employee_number', 'performance_plans.id as plan_id',
                'performance_plans.status', 'performance_plans.submitted_at', 'positions.title as position_title'])
            ->paginate(50)->withQueryString();

        return view('performance.periods.show', [
            'pageTitle' => $row->name,
            'pageNote' => PerformancePeriods::STATUS[$row->status],
            'period' => $row,
            'objectives' => $objectives,
            'tree' => $this->tree($objectives),
            'plans' => $plans,
            'planCounts' => $planCounts, 'planStatus' => $status, 'q' => $q,
            'units' => TenantScope::table('unit')->orderBy('unit_name')->pluck('unit_name', 'id_unit')->all(),
            'levels' => self::LEVELS, 'statuses' => PerformancePeriods::STATUS, 'planStatuses' => PerformancePlans::STATUS,
            'dimensions' => PerformancePolicy::DIMENSIONS,
            'canEdit' => ! empty($access['is_edit']) && $row->status !== 'closed',
            'canApprove' => ! empty($access['is_approve']),
        ]);
    }

    public function open(Request $request, int $period)
    {
        $this->authorizeTask('is_approve');
        $this->periods->open($period, (int) auth()->id());
        $this->logActivity($request, 'Periode kinerja #'.$period.' dibuka');

        return redirect('performance/periods/'.$period)->with('message', 'Periode dibuka; kebijakan dikunci. Pegawai dapat menyusun rencana kinerja.')->with('status', 'success');
    }

    public function advance(Request $request, int $period)
    {
        $this->authorizeTask('is_approve');
        $next = $this->periods->advance($period);
        $this->logActivity($request, 'Periode kinerja #'.$period.' → '.$next);

        return redirect('performance/periods/'.$period)->with('message', 'Tahap periode: '.PerformancePeriods::STATUS[$next])->with('status', 'success');
    }

    // ---------------------------------------------------------- sasaran strategis

    public function storeObjective(Request $request, int $period)
    {
        $this->authorizeTask('is_edit');
        $row = $this->periods->find($period);
        $data = $this->validateObjective($request, $row);

        $id = DB::table('strategic_objectives')->insertGetId(TenantScope::fillForInsert('strategic_objectives', $data + [
            'uuid' => (string) Str::uuid7(), 'period_id' => $row->id, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
        ]));
        AuditLog::created('strategic_objectives', $id, $data, 'Sasaran strategis '.$data['code']);

        return redirect('performance/periods/'.$row->id.'#sasaran')->with('message', __('core.note_success'))->with('status', 'success');
    }

    public function updateObjective(Request $request, int $objective)
    {
        $this->authorizeTask('is_edit');
        $before = $this->objective($objective);
        $row = $this->periods->find((int) $before->period_id);
        $data = $this->validateObjective($request, $row, $before);

        DB::table('strategic_objectives')->where('id', $before->id)->update($data + ['updated_at' => now()]);
        AuditLog::updated('strategic_objectives', $before->id, $before, $data, 'Sasaran strategis '.$data['code']);

        return redirect('performance/periods/'.$row->id.'#sasaran')->with('message', __('core.note_success'))->with('status', 'success');
    }

    public function destroyObjective(int $objective)
    {
        $this->authorizeTask('is_remove');
        $row = $this->objective($objective);
        if (TenantScope::table('strategic_objectives')->where('parent_id', $row->id)->whereNull('deleted_at')->exists()
            || TenantScope::table('performance_objectives')->where('strategic_objective_id', $row->id)->exists()) {
            return back()->withErrors(['objective' => 'Sasaran masih menjadi induk sasaran lain atau dirujuk KPI pegawai.']);
        }
        DB::table('strategic_objectives')->where('id', $row->id)->update(['deleted_at' => now()]);
        AuditLog::deleted('strategic_objectives', $row->id, $row, 'Sasaran strategis '.$row->code);

        return redirect('performance/periods/'.$row->period_id.'#sasaran')->with('message', __('core.note_success'))->with('status', 'success');
    }

    private function validateObjective(Request $request, object $period, ?object $current = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30',
                Rule::unique('strategic_objectives', 'code')->where('period_id', $period->id)->whereNull('deleted_at')->ignore($current?->id)],
            'title' => ['required', 'string', 'max:255'],
            'level' => ['required', Rule::in(array_keys(self::LEVELS))],
            'parent_id' => ['nullable', 'integer', TenantScope::existsRule('strategic_objectives', 'id')->where('period_id', $period->id)->whereNull('deleted_at')],
            'unit_id' => ['nullable', 'integer', TenantScope::existsRule('unit', 'id_unit')],
            'measure' => ['nullable', 'string', 'max:255'],
            'contribution_weight' => ['nullable', 'numeric', 'between:0,100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [], ['code' => 'kode', 'title' => 'sasaran', 'parent_id' => 'induk', 'contribution_weight' => 'bobot kontribusi']);
        $data['code'] = Str::upper($data['code']);

        $order = array_keys(self::LEVELS);
        if ($data['level'] === 'corporate') {
            $data['parent_id'] = null;
        } elseif (empty($data['parent_id'])) {
            abort(back()->withInput()->withErrors(['parent_id' => 'Sasaran selain korporat wajib punya induk (cascading).']));
        } else {
            $parent = TenantScope::table('strategic_objectives')->where('id', $data['parent_id'])->first();
            if (array_search($parent->level, $order) >= array_search($data['level'], $order) || ($current && (int) $parent->id === (int) $current->id)) {
                abort(back()->withInput()->withErrors(['parent_id' => 'Induk harus berada pada level di atasnya.']));
            }
        }

        return $data;
    }

    private function objective(int $id): object
    {
        return TenantScope::table('strategic_objectives')->where('id', $id)->whereNull('deleted_at')->first() ?? abort(404);
    }

    /** Urutan pohon (induk lalu anak) dengan kedalaman. */
    private function tree($objectives): array
    {
        $children = $objectives->groupBy(fn ($o) => (int) $o->parent_id);
        $out = [];
        $walk = function (int $parent, int $depth) use (&$walk, &$out, $children) {
            foreach ($children->get($parent, collect()) as $o) {
                $out[] = ['row' => $o, 'depth' => $depth];
                $walk((int) $o->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return $out;
    }
}
