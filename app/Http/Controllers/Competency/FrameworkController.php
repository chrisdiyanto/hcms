<?php

namespace App\Http\Controllers\Competency;

use App\Domain\Competency\Services\CompetencyFramework;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Kamus kompetensi berversi: daftar versi, kompetensi per versi, draft & terbit. */
class FrameworkController extends Controller
{
    use AuthorizesHcmsModule;

    public function __construct(private CompetencyFramework $framework)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'competencies';
    }

    public function index(Request $request)
    {
        $access = $this->authorizeTask('is_view');
        CompetencyFramework::ensureCategories(TenantScope::tenantId());

        $versions = TenantScope::table('competency_framework_versions')->orderByDesc('version')->get();
        $selected = $request->filled('version')
            ? $versions->firstWhere('id', (int) $request->input('version'))
            : ($versions->firstWhere('status', 'draft') ?? $versions->firstWhere('status', 'published') ?? $versions->first());

        $competencies = $selected ? TenantScope::table('competencies')
            ->join('competency_categories', 'competency_categories.id', '=', 'competencies.category_id')
            ->where('competencies.framework_version_id', $selected->id)
            ->orderBy('competency_categories.sort_order')->orderBy('competencies.code')
            ->get(['competencies.*', 'competency_categories.name as category'])
            ->map(function ($c) {
                $c->indicator_count = TenantScope::table('competency_indicators')
                    ->join('competency_levels', 'competency_levels.id', '=', 'competency_indicators.competency_level_id')
                    ->where('competency_levels.competency_id', $c->id)->count();
                $c->job_count = TenantScope::table('job_competencies')->where('competency_id', $c->id)->count();

                return $c;
            }) : collect();

        return view('competency.framework.index', [
            'pageTitle' => 'Kamus Kompetensi',
            'pageNote' => $selected ? $selected->name : 'Belum ada versi',
            'versions' => $versions,
            'selected' => $selected,
            'competencies' => $competencies->groupBy('category'),
            'problems' => $selected && $selected->status === 'draft' ? $this->framework->publishProblems((int) $selected->id) : [],
            'canEdit' => ! empty($access['is_edit']),
            'canApprove' => ! empty($access['is_approve']),
        ]);
    }

    public function createDraft(Request $request)
    {
        $this->authorizeTask('is_edit');
        $id = $this->framework->createDraft((int) auth()->id());
        $this->logActivity($request, 'Draft kamus kompetensi #'.$id.' dibuat');

        return redirect('competency/framework?version='.$id)->with('message', 'Draft dibuat dari versi terbit.')->with('status', 'success');
    }

    /** Terbit = keputusan kebijakan: butuh izin Approve (blueprint 3.4 SoD diterapkan di modul kebijakan). */
    public function publish(Request $request, int $version)
    {
        $this->authorizeTask('is_approve');
        $data = $request->validate(['effective_from' => ['nullable', 'date']]);
        $this->framework->publish($version, (int) auth()->id(), $data['effective_from'] ?? null);
        $this->logActivity($request, 'Kamus kompetensi #'.$version.' diterbitkan');

        return redirect('competency/framework?version='.$version)->with('message', 'Kamus kompetensi diterbitkan. Profil job dipindahkan ke versi ini.')->with('status', 'success');
    }

    public function create(int $version)
    {
        $this->authorizeTask('is_edit');

        return $this->form($this->draftVersion($version), null);
    }

    public function store(Request $request, int $version)
    {
        $this->authorizeTask('is_edit');
        $v = $this->draftVersion($version);
        $data = $this->validated($request, $v, null);

        $id = DB::transaction(function () use ($data, $v) {
            $row = TenantScope::fillForInsert('competencies', $this->columns($data) + [
                'framework_version_id' => $v->id, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $id = (int) DB::table('competencies')->insertGetId($row);
            $this->saveLevels($id, $data['levels']);
            AuditLog::created('competencies', $id, $row, 'Kamus v'.$v->version);

            return $id;
        });

        return redirect('competency/framework?version='.$v->id)->with('message', __('core.note_success'))->with('status', 'success');
    }

    public function edit(int $competency)
    {
        $this->authorizeTask('is_view');
        $c = $this->competency($competency);
        $version = TenantScope::table('competency_framework_versions')->where('id', $c->framework_version_id)->first();

        return $this->form($version, $c);
    }

    public function update(Request $request, int $competency)
    {
        $this->authorizeTask('is_edit');
        $before = $this->competency($competency);
        $version = $this->draftVersion((int) $before->framework_version_id);
        $data = $this->validated($request, $version, $before);

        DB::transaction(function () use ($data, $before, $version) {
            TenantScope::table('competencies')->where('id', $before->id)->update($this->columns($data) + ['updated_by' => auth()->id(), 'updated_at' => now()]);
            $this->saveLevels((int) $before->id, $data['levels']);
            AuditLog::updated('competencies', $before->id, $before, TenantScope::table('competencies')->where('id', $before->id)->first(), 'Kamus v'.$version->version);
        });

        return redirect('competency/framework?version='.$version->id)->with('message', __('core.note_success'))->with('status', 'success');
    }

    public function destroy(int $competency)
    {
        $this->authorizeTask('is_edit');
        $c = $this->competency($competency);
        $version = $this->draftVersion((int) $c->framework_version_id);
        TenantScope::table('competencies')->where('id', $c->id)->delete();
        AuditLog::deleted('competencies', $c->id, $c, 'Kamus v'.$version->version);

        return redirect('competency/framework?version='.$version->id)->with('message', __('core.note_success_delete'))->with('status', 'success');
    }

    // ---------------------------------------------------------------- bantuan

    private function form(object $version, ?object $competency)
    {
        $levels = [];
        foreach (CompetencyFramework::LEVELS as $level => [$title, $description]) {
            $levels[$level] = ['title' => $title, 'description' => $description, 'indicators' => ''];
        }
        if ($competency) {
            foreach (TenantScope::table('competency_levels')->where('competency_id', $competency->id)->get() as $l) {
                $levels[$l->level] = [
                    'title' => $l->title, 'description' => $l->description,
                    'indicators' => TenantScope::table('competency_indicators')->where('competency_level_id', $l->id)->orderBy('sort_order')->pluck('description')->implode("\n"),
                ];
            }
        }

        return view('competency.framework.form', [
            'pageTitle' => $competency ? $competency->code.' — '.$competency->name : 'Kompetensi Baru',
            'pageNote' => $version->name.($version->status !== 'draft' ? ' (hanya baca)' : ''),
            'version' => $version,
            'competency' => $competency,
            'levels' => $levels,
            'categories' => TenantScope::table('competency_categories')->orderBy('sort_order')->pluck('name', 'id'),
            'readOnly' => $version->status !== 'draft' || ! $this->can('is_edit'),
        ]);
    }

    private function validated(Request $request, object $version, ?object $competency): array
    {
        return $request->validate([
            'code' => ['required', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('competencies', 'code')->where('framework_version_id', $version->id)->ignore($competency?->id)],
            'name' => ['required', 'max:150'],
            'category_id' => ['required', 'integer', TenantScope::existsRule('competency_categories', 'id')],
            'definition' => ['nullable', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'levels' => ['required', 'array'],
            'levels.*.title' => ['required', 'max:60'],
            'levels.*.description' => ['nullable', 'max:1000'],
            'levels.*.indicators' => ['nullable', 'max:3000'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }

    private function columns(array $data): array
    {
        return ['code' => strtoupper($data['code']), 'name' => $data['name'], 'category_id' => (int) $data['category_id'],
            'definition' => $data['definition'] ?? null, 'is_active' => $data['is_active']];
    }

    /** Simpan level 1–5 beserta indikator (satu indikator per baris). */
    private function saveLevels(int $competencyId, array $levels): void
    {
        TenantScope::table('competency_levels')->where('competency_id', $competencyId)->delete();
        foreach (array_keys(CompetencyFramework::LEVELS) as $level) {
            $input = $levels[$level] ?? null;
            if (! $input) {
                continue;
            }
            $levelId = DB::table('competency_levels')->insertGetId(TenantScope::fillForInsert('competency_levels', [
                'competency_id' => $competencyId, 'level' => $level, 'title' => $input['title'], 'description' => $input['description'] ?? null,
            ]));
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($input['indicators'] ?? ''))), fn ($l) => $l !== ''));
            foreach ($lines as $i => $line) {
                DB::table('competency_indicators')->insert(TenantScope::fillForInsert('competency_indicators', [
                    'competency_level_id' => $levelId, 'description' => mb_substr($line, 0, 500), 'sort_order' => $i,
                ]));
            }
        }
    }

    private function draftVersion(int $id): object
    {
        $version = TenantScope::table('competency_framework_versions')->where('id', $id)->first() ?? abort(404);
        if ($version->status !== 'draft') {
            abort(redirect('competency/framework?version='.$id)->with('message', 'Versi yang sudah terbit tidak dapat diubah. Buat draft baru.')->with('status', 'error'));
        }

        return $version;
    }

    private function competency(int $id): object
    {
        return TenantScope::table('competencies')->where('id', $id)->first() ?? abort(404);
    }
}
