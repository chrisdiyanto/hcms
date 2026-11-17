<?php

namespace App\Http\Controllers\Competency;

use App\Domain\Competency\Services\CompetencyFramework;
use App\Domain\Competency\Services\CompetencyProfile;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Profil kompetensi standar per Job (level wajib + bobot kritikalitas 3/2/1),
 * diwarisi semua posisi pada job tersebut; posisi dapat di-override.
 * Hanya kompetensi aktif dari kamus yang sedang berlaku yang bisa dipakai.
 */
class JobProfilesController extends Controller
{
    /** Job dianggap terpetakan bila profilnya punya minimal sekian kompetensi (dan ≥ 1 kritis). */
    public const MIN_COMPETENCIES = 3;

    use AuthorizesHcmsModule;

    public function __construct(private CompetencyFramework $framework, private CompetencyProfile $profiles)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'jobprofiles';
    }

    public function index()
    {
        $access = $this->authorizeTask('is_view');

        $jobs = TenantScope::table('jobs_catalog')
            ->leftJoin('job_families', 'job_families.id', '=', 'jobs_catalog.job_family_id')
            ->whereNull('jobs_catalog.deleted_at')
            ->orderBy('job_families.name')->orderBy('jobs_catalog.code')
            ->get(['jobs_catalog.id', 'jobs_catalog.code', 'jobs_catalog.title', 'job_families.name as family'])
            ->map(function ($job) {
                $rows = TenantScope::table('job_competencies')->where('job_id', $job->id);
                $job->competency_count = (clone $rows)->count();
                $job->critical_count = (clone $rows)->where('criticality', 3)->count();
                $job->total_weight = (int) (clone $rows)->sum('criticality');
                $job->position_count = TenantScope::table('positions')->where('job_id', $job->id)->whereNull('deleted_at')->count();

                return $job;
            });

        // Laporan CBHRM 1 & gerbang Fase 2: cakupan pemetaan per job family.
        // Job terpetakan = ≥ 3 kompetensi dengan ≥ 1 kritis; posisi aktif terpetakan = job-nya punya profil
        // atau posisi punya kompetensi tambahan (docs/METRIK-CBHRM.md §3).
        $mappedPositions = TenantScope::table('positions')->whereNull('deleted_at')->where('status', 'active')
            ->where(fn ($q) => $q->whereExists(fn ($e) => $e->from('job_competencies')->whereColumn('job_competencies.job_id', 'positions.job_id'))
                ->orWhereExists(fn ($e) => $e->from('position_competencies')->whereColumn('position_competencies.position_id', 'positions.id')->where('position_competencies.is_excluded', false)))
            ->selectRaw('job_id, COUNT(*) n')->groupBy('job_id')->pluck('n', 'job_id');
        $activePositions = TenantScope::table('positions')->whereNull('deleted_at')->where('status', 'active')
            ->selectRaw('job_id, COUNT(*) n')->groupBy('job_id')->pluck('n', 'job_id');
        $coverage = $jobs->groupBy('family')->map(function ($items) use ($mappedPositions, $activePositions) {
            $mapped = $items->filter(fn ($j) => $j->competency_count >= self::MIN_COMPETENCIES && $j->critical_count >= 1);
            $positions = (int) $items->sum(fn ($j) => $activePositions[$j->id] ?? 0);
            $mappedPos = (int) $items->sum(fn ($j) => $mappedPositions[$j->id] ?? 0);

            return (object) ['jobs' => $items->count(), 'mapped_jobs' => $mapped->count(), 'positions' => $positions, 'mapped_positions' => $mappedPos,
                'full' => $items->isNotEmpty() && $mapped->count() === $items->count() && $mappedPos === $positions];
        });

        return view('competency.profiles.index', [
            'pageTitle' => 'Profil Kompetensi Job',
            'pageNote' => ($v = $this->framework->published()) ? 'mengacu '.$v->name : 'belum ada kamus yang berlaku',
            'jobs' => $jobs->groupBy('family'),
            'coverage' => $coverage,
            'positionTotals' => [(int) $mappedPositions->sum(), (int) $activePositions->sum()],
            'hasFramework' => (bool) $v,
            'access' => $access,
        ]);
    }

    public function edit(int $job)
    {
        $this->authorizeTask('is_view');
        $row = $this->job($job);
        $version = $this->framework->published();

        $positions = TenantScope::table('positions')->leftJoin('unit', 'unit.id_unit', '=', 'positions.unit_id')
            ->where('positions.job_id', $row->id)->whereNull('positions.deleted_at')->orderBy('positions.code')
            ->get(['positions.id', 'positions.code', 'positions.title', 'unit.unit_name'])
            ->map(function ($p) {
                $p->override_count = TenantScope::table('position_competencies')->where('position_id', $p->id)->count();

                return $p;
            });

        return view('competency.profiles.edit', [
            'pageTitle' => 'Profil Kompetensi: '.$row->title,
            'pageNote' => $row->code,
            'job' => $row,
            'version' => $version,
            'rows' => old('items', $this->profiles->forJob($row->id)->map(fn ($r) => [
                'competency_id' => $r->competency_id, 'required_level' => $r->required_level, 'criticality' => $r->criticality,
            ])->values()->all()),
            'competencies' => $this->competencyOptions($version),
            'positions' => $positions,
            'canEdit' => $this->can('is_edit') && $version,
            'criticality' => CompetencyProfile::CRITICALITY,
        ]);
    }

    public function update(Request $request, int $job)
    {
        $this->authorizeTask('is_edit');
        $row = $this->job($job);
        $version = $this->framework->published() ?? abort(422, 'Belum ada kamus kompetensi yang berlaku.');

        $data = $request->validate([
            'items' => ['nullable', 'array', 'max:30'],
            'items.*.competency_id' => ['required', 'integer', 'distinct', $this->activeCompetencyRule($version)],
            'items.*.required_level' => ['required', 'integer', 'between:1,5'],
            'items.*.criticality' => ['required', Rule::in(array_keys(CompetencyProfile::CRITICALITY))],
        ], ['items.*.competency_id.distinct' => 'Kompetensi yang sama dipilih lebih dari sekali.']);

        DB::transaction(function () use ($row, $data) {
            $before = $this->snapshot('job_competencies', 'job_id', $row->id);
            TenantScope::table('job_competencies')->where('job_id', $row->id)->delete();
            foreach ($data['items'] ?? [] as $item) {
                DB::table('job_competencies')->insert(TenantScope::fillForInsert('job_competencies', [
                    'job_id' => $row->id, 'competency_id' => (int) $item['competency_id'], 'required_level' => (int) $item['required_level'],
                    'criticality' => (int) $item['criticality'], 'updated_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
                ]));
            }
            $after = $this->snapshot('job_competencies', 'job_id', $row->id);
            if ($before !== $after) {
                AuditLog::updated('job_competencies', $row->id, ['profil' => $before], ['profil' => $after], 'Profil kompetensi job '.$row->code);
            }
        });

        return redirect('competency/job-profiles/'.$row->id)->with('message', __('core.note_success'))->with('status', 'success');
    }

    /** Override per posisi: ubah level/bobot, kecualikan, atau tambah kompetensi khusus posisi. */
    public function position(int $position)
    {
        $this->authorizeTask('is_view');
        $pos = $this->positionRow($position);
        $version = $this->framework->published();

        return view('competency.profiles.position', [
            'pageTitle' => 'Override Kompetensi: '.$pos->title,
            'pageNote' => $pos->code,
            'position' => $pos,
            'jobProfile' => $this->profiles->forJob((int) $pos->job_id),
            'effective' => $this->profiles->forPosition($pos->id),
            'rows' => old('items', TenantScope::table('position_competencies')->where('position_id', $pos->id)->get()
                ->map(fn ($o) => ['competency_id' => $o->competency_id, 'required_level' => $o->required_level, 'criticality' => $o->criticality,
                    'is_excluded' => (bool) $o->is_excluded, 'reason' => $o->reason])->values()->all()),
            'competencies' => $this->competencyOptions($version),
            'canEdit' => $this->can('is_edit') && $version,
            'criticality' => CompetencyProfile::CRITICALITY,
        ]);
    }

    public function updatePosition(Request $request, int $position)
    {
        $this->authorizeTask('is_edit');
        $pos = $this->positionRow($position);
        $version = $this->framework->published() ?? abort(422, 'Belum ada kamus kompetensi yang berlaku.');
        $jobCompetencies = TenantScope::table('job_competencies')->where('job_id', $pos->job_id)->pluck('competency_id')->map(fn ($v) => (int) $v)->all();

        $data = $request->validate([
            'items' => ['nullable', 'array', 'max:30'],
            'items.*.competency_id' => ['required', 'integer', 'distinct', $this->activeCompetencyRule($version)],
            'items.*.required_level' => ['nullable', 'integer', 'between:1,5'],
            'items.*.criticality' => ['nullable', Rule::in(array_keys(CompetencyProfile::CRITICALITY))],
            'items.*.is_excluded' => ['nullable', 'boolean'],
            'items.*.reason' => ['required', 'string', 'max:255'],
        ]);

        // Tambahan khusus posisi (bukan dari job) wajib punya level & bobot sendiri
        foreach ($data['items'] ?? [] as $i => $item) {
            $fromJob = in_array((int) $item['competency_id'], $jobCompetencies, true);
            if (! $fromJob && (empty($item['is_excluded']) && (empty($item['required_level']) || empty($item['criticality'])))) {
                return back()->withInput()->withErrors(["items.$i.required_level" => 'Kompetensi tambahan (tidak ada di profil job) wajib diisi level dan bobotnya.']);
            }
        }

        DB::transaction(function () use ($pos, $data) {
            $before = $this->snapshot('position_competencies', 'position_id', $pos->id);
            TenantScope::table('position_competencies')->where('position_id', $pos->id)->delete();
            foreach ($data['items'] ?? [] as $item) {
                DB::table('position_competencies')->insert(TenantScope::fillForInsert('position_competencies', [
                    'position_id' => $pos->id, 'competency_id' => (int) $item['competency_id'],
                    // select yang dinonaktifkan (dikecualikan) tidak ikut terkirim
                    'required_level' => ($item['required_level'] ?? null) ?: null, 'criticality' => ($item['criticality'] ?? null) ?: null,
                    'is_excluded' => ! empty($item['is_excluded']), 'reason' => $item['reason'],
                    'updated_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
                ]));
            }
            $after = $this->snapshot('position_competencies', 'position_id', $pos->id);
            if ($before !== $after) {
                AuditLog::updated('position_competencies', $pos->id, ['override' => $before], ['override' => $after], 'Override kompetensi posisi '.$pos->code);
            }
        });

        return redirect('competency/positions/'.$pos->id)->with('message', __('core.note_success'))->with('status', 'success');
    }

    // ---------------------------------------------------------------- bantuan

    private function competencyOptions(?object $version): array
    {
        if (! $version) {
            return [];
        }

        return TenantScope::table('competencies')
            ->join('competency_categories', 'competency_categories.id', '=', 'competencies.category_id')
            ->where('competencies.framework_version_id', $version->id)->where('competencies.is_active', true)
            ->orderBy('competency_categories.sort_order')->orderBy('competencies.code')
            ->get(['competencies.id', 'competencies.code', 'competencies.name', 'competency_categories.name as category'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->category.' · '.$c->code.' — '.$c->name])->all();
    }

    private function activeCompetencyRule(object $version): Exists
    {
        return TenantScope::existsRule('competencies', 'id')->where('framework_version_id', $version->id)->where('is_active', true);
    }

    private function snapshot(string $table, string $key, int $id): string
    {
        return TenantScope::table($table)->join('competencies', 'competencies.id', '=', $table.'.competency_id')
            ->where($table.'.'.$key, $id)->orderBy('competencies.code')
            ->get(['competencies.code', $table.'.required_level', $table.'.criticality'] + ($table === 'position_competencies' ? [3 => $table.'.is_excluded'] : []))
            ->map(fn ($r) => $r->code.':L'.($r->required_level ?? '-').'/B'.($r->criticality ?? '-').(! empty($r->is_excluded) ? '/X' : ''))
            ->implode(', ');
    }

    private function job(int $id): object
    {
        return TenantScope::table('jobs_catalog')->where('id', $id)->whereNull('deleted_at')->first() ?? abort(404);
    }

    private function positionRow(int $id): object
    {
        return TenantScope::table('positions')->where('id', $id)->whereNull('deleted_at')->first() ?? abort(404);
    }
}
