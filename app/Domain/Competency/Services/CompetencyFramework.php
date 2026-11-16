<?php

namespace App\Domain\Competency\Services;

use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kamus kompetensi berversi (blueprint 7.1).
 *
 * - Tepat satu versi `published` aktif dan paling banyak satu `draft` per tenant.
 * - Hanya draft yang boleh diubah. Draft baru menyalin versi terbit.
 * - Terbit: versi lama menjadi `retired`, profil kompetensi job & posisi dipindah
 *   ke kompetensi versi baru berdasarkan kode (kompetensi yang dihapus/nonaktif ikut gugur).
 * - Syarat terbit: setiap kompetensi aktif punya level 1–5 dan 2–4 indikator per level.
 */
final class CompetencyFramework
{
    public const CATEGORIES = ['CORE' => 'Core', 'LEAD' => 'Leadership', 'FUNC' => 'Functional', 'DIGI' => 'Digital'];

    /** Level & kategori deskripsi bawaan (blueprint 7.2). */
    public const LEVELS = [
        1 => ['Basic', 'Memahami konsep dasar'],
        2 => ['Developing', 'Mampu bekerja dengan supervisi'],
        3 => ['Proficient', 'Mampu bekerja mandiri'],
        4 => ['Advanced', 'Mampu menangani pekerjaan kompleks dan membimbing orang lain'],
        5 => ['Expert', 'Menjadi ahli dan rujukan organisasi'],
    ];

    public const MIN_INDICATORS = 2;

    public const MAX_INDICATORS = 4;

    public static function ensureCategories(int $tenantId): void
    {
        $order = 0;
        foreach (self::CATEGORIES as $code => $name) {
            if (! DB::table('competency_categories')->where('tenant_id', $tenantId)->where('code', $code)->exists()) {
                DB::table('competency_categories')->insert(['tenant_id' => $tenantId, 'code' => $code, 'name' => $name, 'sort_order' => ++$order, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function published(): ?object
    {
        return TenantScope::table('competency_framework_versions')->where('status', 'published')->orderByDesc('version')->first();
    }

    public function draft(): ?object
    {
        return TenantScope::table('competency_framework_versions')->where('status', 'draft')->first();
    }

    public function createDraft(?int $userId): int
    {
        if ($this->draft()) {
            throw ValidationException::withMessages(['version' => 'Sudah ada draft yang belum diterbitkan.']);
        }

        return DB::transaction(function () use ($userId) {
            $next = (int) TenantScope::table('competency_framework_versions')->max('version') + 1;
            $id = DB::table('competency_framework_versions')->insertGetId(TenantScope::fillForInsert('competency_framework_versions', [
                'version' => $next, 'name' => 'Kamus Kompetensi v'.$next, 'status' => 'draft',
                'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]));

            if ($source = $this->published()) {
                foreach (TenantScope::table('competencies')->where('framework_version_id', $source->id)->get() as $competency) {
                    $this->copyCompetency($competency, $id, $userId);
                }
            }

            AuditLog::created('competency_framework_versions', $id, ['version' => $next, 'status' => 'draft'], 'Draft kamus kompetensi');

            return $id;
        });
    }

    /** @return string[] daftar kekurangan; kosong = siap terbit */
    public function publishProblems(int $versionId): array
    {
        $problems = [];
        $competencies = TenantScope::table('competencies')->where('framework_version_id', $versionId)->where('is_active', true)->orderBy('code')->get();
        if ($competencies->isEmpty()) {
            return ['Belum ada kompetensi aktif.'];
        }

        foreach ($competencies as $c) {
            $levels = TenantScope::table('competency_levels')->where('competency_id', $c->id)->pluck('id', 'level');
            $missing = array_diff(array_keys(self::LEVELS), $levels->keys()->all());
            if ($missing) {
                $problems[] = $c->code.': level '.implode(', ', $missing).' belum didefinisikan.';
            }
            foreach ($levels as $level => $levelId) {
                $count = TenantScope::table('competency_indicators')->where('competency_level_id', $levelId)->count();
                if ($count < self::MIN_INDICATORS || $count > self::MAX_INDICATORS) {
                    $problems[] = $c->code.' level '.$level.': '.$count.' indikator (harus '.self::MIN_INDICATORS.'–'.self::MAX_INDICATORS.').';
                }
            }
        }

        return $problems;
    }

    public function publish(int $versionId, ?int $userId, ?string $effectiveFrom = null): void
    {
        $version = TenantScope::table('competency_framework_versions')->where('id', $versionId)->first() ?? abort(404);
        if ($version->status !== 'draft') {
            throw ValidationException::withMessages(['version' => 'Hanya draft yang dapat diterbitkan.']);
        }
        if ($problems = $this->publishProblems($versionId)) {
            throw ValidationException::withMessages(['version' => $problems]);
        }

        DB::transaction(function () use ($version, $userId, $effectiveFrom) {
            $previous = $this->published();

            if ($previous) {
                TenantScope::table('competency_framework_versions')->where('id', $previous->id)->update(['status' => 'retired', 'updated_at' => now()]);
                $this->migrateProfiles((int) $previous->id, (int) $version->id);
            }

            TenantScope::table('competency_framework_versions')->where('id', $version->id)->update([
                'status' => 'published', 'published_at' => now(), 'published_by' => $userId,
                'effective_from' => $effectiveFrom ?? now()->toDateString(), 'updated_at' => now(),
            ]);

            AuditLog::updated('competency_framework_versions', $version->id, ['status' => 'draft'], ['status' => 'published'],
                'Kamus kompetensi v'.$version->version.' diterbitkan'.($previous ? ' (v'.$previous->version.' dipensiunkan)' : ''));
        });
    }

    /** Pindahkan profil job & posisi ke kompetensi versi baru berdasarkan kode. */
    private function migrateProfiles(int $fromVersion, int $toVersion): void
    {
        $map = TenantScope::table('competencies as old')
            ->join('competencies as new', function ($join) use ($toVersion) {
                $join->on('new.code', '=', 'old.code')->where('new.framework_version_id', $toVersion)->where('new.is_active', true);
            })
            ->where('old.framework_version_id', $fromVersion)
            // alias wajib: pluck('new.id', 'old.id') memetakan id ke dirinya sendiri (keduanya bernama `id`)
            ->get(['new.id as new_id', 'old.id as old_id'])
            ->pluck('new_id', 'old_id');

        foreach (['job_competencies', 'position_competencies'] as $table) {
            $rows = TenantScope::table($table)->whereIn('competency_id', TenantScope::table('competencies')->where('framework_version_id', $fromVersion)->pluck('id'))->get();
            foreach ($rows as $row) {
                if (isset($map[$row->competency_id])) {
                    TenantScope::table($table)->where('id', $row->id)->update(['competency_id' => $map[$row->competency_id], 'updated_at' => now()]);
                } else {
                    TenantScope::table($table)->where('id', $row->id)->delete();
                    AuditLog::deleted($table, $row->id, $row, 'Kompetensi tidak ada di versi baru');
                }
            }
        }
    }

    private function copyCompetency(object $competency, int $toVersion, ?int $userId): void
    {
        $newId = DB::table('competencies')->insertGetId(TenantScope::fillForInsert('competencies', [
            'framework_version_id' => $toVersion, 'category_id' => $competency->category_id, 'code' => $competency->code,
            'name' => $competency->name, 'definition' => $competency->definition, 'is_active' => $competency->is_active,
            'uuid' => (string) Str::uuid7(), 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]));

        foreach (TenantScope::table('competency_levels')->where('competency_id', $competency->id)->orderBy('level')->get() as $level) {
            $levelId = DB::table('competency_levels')->insertGetId(TenantScope::fillForInsert('competency_levels', [
                'competency_id' => $newId, 'level' => $level->level, 'title' => $level->title, 'description' => $level->description,
            ]));
            foreach (TenantScope::table('competency_indicators')->where('competency_level_id', $level->id)->orderBy('sort_order')->get() as $indicator) {
                DB::table('competency_indicators')->insert(TenantScope::fillForInsert('competency_indicators', [
                    'competency_level_id' => $levelId, 'description' => $indicator->description, 'sort_order' => $indicator->sort_order,
                ]));
            }
        }
    }
}
