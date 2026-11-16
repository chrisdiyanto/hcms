<?php

namespace App\Domain\Competency\Services;

use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Collection;

/**
 * Profil kompetensi yang berlaku (blueprint 5 & 7.1): standar ditetapkan per Job
 * lalu diwarisi semua posisi. Override posisi dapat mengubah level/bobot,
 * mengecualikan kompetensi job, atau menambah kompetensi khusus posisi.
 */
final class CompetencyProfile
{
    public const CRITICALITY = [3 => 'Kritis', 2 => 'Penting', 1 => 'Pendukung'];

    /** @return Collection<int, object> competency_id, code, name, category, required_level, criticality, source */
    public function forJob(int $jobId): Collection
    {
        return TenantScope::table('job_competencies')
            ->join('competencies', 'competencies.id', '=', 'job_competencies.competency_id')
            ->join('competency_categories', 'competency_categories.id', '=', 'competencies.category_id')
            ->where('job_competencies.job_id', $jobId)
            ->orderByDesc('job_competencies.criticality')->orderBy('competencies.code')
            ->get(['competencies.id as competency_id', 'competencies.code', 'competencies.name', 'competency_categories.name as category',
                'job_competencies.required_level', 'job_competencies.criticality'])
            ->map(fn ($r) => (object) ((array) $r + ['source' => 'job']));
    }

    public function forPosition(int $positionId): Collection
    {
        $jobId = (int) TenantScope::table('positions')->where('id', $positionId)->value('job_id');
        $profile = $this->forJob($jobId)->keyBy('competency_id');

        $overrides = TenantScope::table('position_competencies')
            ->join('competencies', 'competencies.id', '=', 'position_competencies.competency_id')
            ->join('competency_categories', 'competency_categories.id', '=', 'competencies.category_id')
            ->where('position_competencies.position_id', $positionId)
            ->get(['position_competencies.*', 'competencies.code', 'competencies.name', 'competency_categories.name as category']);

        foreach ($overrides as $o) {
            if ($o->is_excluded) {
                $profile->forget($o->competency_id);

                continue;
            }
            $base = $profile->get($o->competency_id);
            $required = $o->required_level ?? $base->required_level ?? null;
            $criticality = $o->criticality ?? $base->criticality ?? null;
            if ($required === null || $criticality === null) {
                continue; // tambahan posisi harus lengkap
            }
            $profile->put($o->competency_id, (object) [
                'competency_id' => $o->competency_id, 'code' => $o->code, 'name' => $o->name, 'category' => $o->category,
                'required_level' => (int) $required, 'criticality' => (int) $criticality, 'source' => $base ? 'override' : 'position',
            ]);
        }

        return $profile->sortBy([['criticality', 'desc'], ['code', 'asc']])->values();
    }
}
