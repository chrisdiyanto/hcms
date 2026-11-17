<?php

namespace App\Domain\Competency\Services;

use App\Domain\Workflow\Services\ApproverResolver;
use App\Domain\Workflow\Services\Notifier;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Siklus asesmen kompetensi (blueprint 7.3–7.5):
 *
 *   buka (HR) → penilai mengisi skor 1–5 + bukti → [hasil assessment center] → tutup (HR)
 *
 * Saat ditutup: skor digabung per kompetensi (RaterAggregator; assessment center menggantikan),
 * disimpan ke employee_competencies (berlaku 24 bulan), lalu gap & JFI terhadap posisi
 * dihitung ulang dan draf IDP dibuat untuk kompetensi kritis yang gap-nya negatif.
 *
 * Penilai atasan ditentukan otomatis dari posisi (solid line, naik bila kosong);
 * diri sendiri dari akun pegawai; rekan, bawahan & HR dipilih HR.
 */
final class CompetencyAssessment
{
    public const VALID_MONTHS = 24;

    public const RATER_TYPES = [
        'self' => 'Diri sendiri', 'manager' => 'Atasan', 'peer' => 'Rekan', 'subordinate' => 'Bawahan',
        'hr' => 'HR', 'assessment_center' => 'Assessment center',
    ];

    public const STATUS = ['open' => 'Berjalan', 'closed' => 'Selesai', 'cancelled' => 'Dibatalkan'];

    public function __construct(
        private CompetencyFramework $framework,
        private CompetencyProfile $profiles,
        private ApproverResolver $resolver,
        private Notifier $notifier,
        private CompetencyHistory $history,
    ) {}

    // ------------------------------------------------------------------ buka

    /**
     * @param  array{title?:?string, due_date?:?string, include_self?:bool, include_manager?:bool, peers?:int[], subordinates?:int[], hr?:int[]}  $options
     */
    public function open(int $employeeId, array $options, ?int $actorId): int
    {
        $employee = TenantScope::table('employees')->where('id', $employeeId)->whereNull('deleted_at')->first();
        if (! $employee || $employee->status !== 'active') {
            throw ValidationException::withMessages(['employee_id' => 'Pegawai tidak ditemukan atau sudah tidak aktif.']);
        }
        $version = $this->framework->published()
            ?? throw ValidationException::withMessages(['employee_id' => 'Belum ada kamus kompetensi yang berlaku.']);
        $positionId = $this->currentPosition($employeeId)
            ?? throw ValidationException::withMessages(['employee_id' => 'Pegawai belum memiliki penugasan primer yang aktif.']);
        if ($this->profiles->forPosition($positionId)->isEmpty()) {
            throw ValidationException::withMessages(['employee_id' => 'Posisi pegawai belum memiliki profil kompetensi.']);
        }
        if (TenantScope::table('competency_assessments')->where('employee_id', $employeeId)->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['employee_id' => 'Pegawai ini masih memiliki asesmen yang berjalan.']);
        }

        $raters = $this->plannedRaters($employee, $positionId, $options);
        if ($raters === []) {
            throw ValidationException::withMessages(['employee_id' => 'Tidak ada penilai: pegawai belum punya akun, atasan tidak ditemukan, dan tidak ada penilai yang dipilih.']);
        }

        return DB::transaction(function () use ($employee, $version, $positionId, $options, $actorId, $raters) {
            $now = now();
            $id = DB::table('competency_assessments')->insertGetId(TenantScope::fillForInsert('competency_assessments', [
                'uuid' => (string) Str::uuid7(),
                'employee_id' => $employee->id,
                'framework_version_id' => $version->id,
                'position_id' => $positionId,
                'title' => trim((string) ($options['title'] ?? '')) ?: 'Asesmen kompetensi '.$now->translatedFormat('F Y'),
                'status' => 'open',
                'due_date' => $options['due_date'] ?? null,
                'created_by' => $actorId,
                'created_at' => $now, 'updated_at' => $now,
            ]));

            foreach ($raters as [$userId, $type]) {
                DB::table('assessment_raters')->insert(TenantScope::fillForInsert('assessment_raters', [
                    'assessment_id' => $id, 'rater_user_id' => $userId, 'rater_type' => $type, 'status' => 'pending',
                    'created_at' => $now, 'updated_at' => $now,
                ]));
                $this->notifier->send($userId, 'Penilaian kompetensi',
                    'Mohon nilai kompetensi '.$employee->full_name.' ('.self::RATER_TYPES[$type].').', 'competency/my-assessments', 'fa fa-star-half-o');
            }

            AuditLog::created('competency_assessments', $id, [
                'pegawai' => $employee->employee_number, 'posisi' => $positionId, 'penilai' => count($raters),
            ], 'Asesmen kompetensi dibuka: '.$employee->full_name);

            return $id;
        });
    }

    /** @return array<int, array{0:int, 1:string}> [user_id, rater_type] tanpa duplikat */
    private function plannedRaters(object $employee, int $positionId, array $options): array
    {
        $list = [];
        $self = $employee->user_id ? (int) $employee->user_id : null;
        $add = function (?int $userId, string $type) use (&$list, $self) {
            if (! $userId || ($type !== 'self' && $userId === $self) || isset($list[$userId.'|'.$type])) {
                return;
            }
            if (! TenantScope::table('tb_users')->where('id', $userId)->where('active', 1)->exists()) {
                return;
            }
            $list[$userId.'|'.$type] = [$userId, $type];
        };

        if (($options['include_self'] ?? true) && $self) {
            $add($self, 'self');
        }
        if ($options['include_manager'] ?? true) {
            foreach ($this->resolver->escalationFrom($positionId, $self ?? 0) as $candidate) {
                $add((int) $candidate['user_id'], 'manager');
            }
        }
        foreach (['peers' => 'peer', 'subordinates' => 'subordinate', 'hr' => 'hr'] as $key => $type) {
            foreach ($options[$key] ?? [] as $userId) {
                $add((int) $userId, $type);
            }
        }

        return array_values($list);
    }

    // ------------------------------------------------------------------ nilai

    /** Kompetensi yang dinilai = profil posisi saat ini (job + override). */
    public function competencies(object $assessment): Collection
    {
        return $this->profiles->forPosition((int) $assessment->position_id);
    }

    /**
     * @param  array<int|string, array{score:int|string, evidence?:?string}>  $scores  competency_id => nilai
     */
    public function submit(int $raterId, int $userId, array $scores): void
    {
        $rater = TenantScope::table('assessment_raters')->where('id', $raterId)->first();
        if (! $rater || (int) $rater->rater_user_id !== $userId || $rater->rater_type === 'assessment_center') {
            abort(403, 'Anda bukan penilai pada asesmen ini.');
        }
        $assessment = $this->assessment((int) $rater->assessment_id);
        if ($assessment->status !== 'open') {
            throw ValidationException::withMessages(['scores' => 'Asesmen sudah ditutup.']);
        }

        DB::transaction(function () use ($rater, $assessment, $scores) {
            $this->storeScores($rater, $assessment, $scores);
            DB::table('assessment_raters')->where('id', $rater->id)->update(['status' => 'submitted', 'submitted_at' => now(), 'updated_at' => now()]);
            AuditLog::updated('assessment_raters', $rater->id, ['status' => $rater->status], ['status' => 'submitted'],
                'Penilaian kompetensi dikirim ('.self::RATER_TYPES[$rater->rater_type].')');
        });
    }

    /** Hasil assessment center (diinput HR) — menggantikan metode lain saat ditutup. */
    public function recordAssessmentCenter(int $assessmentId, int $actorId, array $scores): void
    {
        $assessment = $this->assessment($assessmentId);
        if ($assessment->status !== 'open') {
            throw ValidationException::withMessages(['scores' => 'Asesmen sudah ditutup.']);
        }

        DB::transaction(function () use ($assessment, $actorId, $scores) {
            $rater = TenantScope::table('assessment_raters')->where('assessment_id', $assessment->id)->where('rater_type', 'assessment_center')->first();
            if (! $rater) {
                $id = DB::table('assessment_raters')->insertGetId(TenantScope::fillForInsert('assessment_raters', [
                    'assessment_id' => $assessment->id, 'rater_user_id' => $actorId, 'rater_type' => 'assessment_center',
                    'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
                ]));
                $rater = TenantScope::table('assessment_raters')->where('id', $id)->first();
            }
            $this->storeScores($rater, $assessment, $scores);
            DB::table('assessment_raters')->where('id', $rater->id)->update(['status' => 'submitted', 'submitted_at' => now(), 'updated_at' => now()]);
            AuditLog::updated('competency_assessments', $assessment->id, null, ['assessment_center' => count($scores).' kompetensi'], 'Hasil assessment center diinput');
        });
    }

    private function storeScores(object $rater, object $assessment, array $scores): void
    {
        $required = $this->competencies($assessment)->pluck('competency_id')->map(fn ($v) => (int) $v)->all();
        $errors = [];
        foreach ($required as $cid) {
            $score = $scores[$cid]['score'] ?? null;
            if (! is_numeric($score) || (int) $score < 1 || (int) $score > 5) {
                $errors["scores.$cid.score"] = 'Skor wajib diisi 1–5.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        DB::table('competency_assessment_details')->where('rater_id', $rater->id)->delete();
        foreach ($required as $cid) {
            DB::table('competency_assessment_details')->insert(TenantScope::fillForInsert('competency_assessment_details', [
                'assessment_id' => $assessment->id, 'rater_id' => $rater->id, 'competency_id' => $cid,
                'score' => (int) $scores[$cid]['score'],
                'evidence' => Str::limit(trim((string) ($scores[$cid]['evidence'] ?? '')), 2000, '') ?: null,
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }
    }

    // ------------------------------------------------------------------ tutup

    /** @return array{jfi: ?float, band: string, complete: bool, coverage: float, scored: int} */
    public function close(int $assessmentId, int $actorId): array
    {
        $assessment = $this->assessment($assessmentId);
        if ($assessment->status !== 'open') {
            throw ValidationException::withMessages(['status' => 'Asesmen sudah ditutup atau dibatalkan.']);
        }
        if (! TenantScope::table('assessment_raters')->where('assessment_id', $assessmentId)->where('status', 'submitted')->exists()) {
            throw ValidationException::withMessages(['status' => 'Belum ada penilai yang mengirim penilaian.']);
        }

        return DB::transaction(function () use ($assessment, $actorId) {
            $today = now()->toDateString();
            $validUntil = now()->addMonths(self::VALID_MONTHS)->toDateString();
            $aggregator = new RaterAggregator($this->raterWeights());
            $results = $this->aggregate($assessment, $aggregator);

            $scored = 0;
            foreach ($results as $cid => $result) {
                if ($result['score'] === null) {
                    continue;
                }
                $scored++;
                $code = $result['code'];
                $row = [
                    'competency_id' => $cid, 'score' => $result['score'], 'source' => $result['source'],
                    'assessment_id' => $assessment->id, 'assessed_at' => $today, 'valid_until' => $validUntil,
                    'breakdown' => json_encode($result['groups']), 'updated_at' => now(),
                ];
                $existing = TenantScope::table('employee_competencies')->where('employee_id', $assessment->employee_id)->where('competency_code', $code)->value('id');
                if ($existing) {
                    DB::table('employee_competencies')->where('id', $existing)->update($row);
                } else {
                    DB::table('employee_competencies')->insert(TenantScope::fillForInsert('employee_competencies', $row + [
                        'employee_id' => $assessment->employee_id, 'competency_code' => $code, 'created_at' => now(),
                    ]));
                }
                $this->history->recordScore($assessment, (int) $cid, $code, $row);
            }

            DB::table('competency_assessments')->where('id', $assessment->id)->update([
                'status' => 'closed', 'closed_at' => now(), 'closed_by' => $actorId, 'valid_until' => $validUntil, 'updated_at' => now(),
            ]);
            TenantScope::table('assessment_raters')->where('assessment_id', $assessment->id)->where('status', 'pending')->delete();

            $fit = $this->refreshFit((int) $assessment->employee_id, (int) $assessment->position_id, $assessment->id);
            $this->suggestIdp($assessment, $fit['items']);

            AuditLog::updated('competency_assessments', $assessment->id, ['status' => 'open'],
                ['status' => 'closed', 'jfi' => $fit['jfi'] === null ? null : round($fit['jfi'], 1), 'band' => $fit['band']], 'Asesmen kompetensi ditutup');

            $userId = TenantScope::table('employees')->where('id', $assessment->employee_id)->value('user_id');
            if ($userId) {
                $this->notifier->send((int) $userId, 'Hasil asesmen kompetensi', 'Asesmen "'.$assessment->title.'" telah selesai. Lihat profil kompetensi Anda.',
                    'competency/my-assessments', 'fa fa-check-circle');
            }

            return ['jfi' => $fit['jfi'], 'band' => $fit['band'], 'complete' => $fit['complete'], 'coverage' => $fit['coverage'], 'scored' => $scored];
        });
    }

    public function cancel(int $assessmentId, string $reason): void
    {
        $assessment = $this->assessment($assessmentId);
        if ($assessment->status !== 'open') {
            throw ValidationException::withMessages(['status' => 'Hanya asesmen yang berjalan yang dapat dibatalkan.']);
        }
        DB::table('competency_assessments')->where('id', $assessmentId)->update(['status' => 'cancelled', 'updated_at' => now()]);
        AuditLog::updated('competency_assessments', $assessmentId, ['status' => 'open'], ['status' => 'cancelled', 'alasan' => $reason], 'Asesmen kompetensi dibatalkan');
    }

    /**
     * Skor gabungan per kompetensi dari penilai yang sudah mengirim.
     *
     * @return array<int, array{code:string, score:?float, source:string, groups:array}>
     */
    public function aggregate(object $assessment, ?RaterAggregator $aggregator = null): array
    {
        $aggregator ??= new RaterAggregator($this->raterWeights());
        $details = TenantScope::table('competency_assessment_details')
            ->join('assessment_raters', 'assessment_raters.id', '=', 'competency_assessment_details.rater_id')
            ->where('competency_assessment_details.assessment_id', $assessment->id)
            ->where('assessment_raters.status', 'submitted')
            ->get(['competency_assessment_details.competency_id', 'competency_assessment_details.score', 'assessment_raters.rater_type'])
            ->groupBy('competency_id');

        $out = [];
        foreach ($this->competencies($assessment) as $c) {
            $rows = $details->get($c->competency_id, collect());
            $groups = [];
            $center = null;
            foreach ($rows as $r) {
                if ($r->rater_type === 'assessment_center') {
                    $center = (float) $r->score;

                    continue;
                }
                $groups[$r->rater_type][] = (int) $r->score;
            }
            $out[(int) $c->competency_id] = ['code' => $c->code] + $aggregator->aggregate($groups, $center);
        }

        return $out;
    }

    // ------------------------------------------------------------ gap & JFI

    /**
     * Hitung ulang gap & JFI pegawai terhadap sebuah posisi dari skor kompetensi yang masih berlaku.
     *
     * @return array{jfi: ?float, band: string, complete: bool, coverage: float, items: array}
     */
    /** Versi cache agregat Laporan Kompetensi per tenant; naik setiap gap/JFI dihitung ulang. */
    public static function reportVersion(): int
    {
        return (int) Cache::get('competency-report-version:'.TenantScope::tenantId(), 0);
    }

    public static function bumpReportVersion(): void
    {
        Cache::forever('competency-report-version:'.TenantScope::tenantId(), self::reportVersion() + 1);
    }

    public function refreshFit(int $employeeId, int $positionId, ?int $assessmentId = null): array
    {
        $fit = $this->fitFor($employeeId, $positionId);
        $now = now();
        self::bumpReportVersion();

        TenantScope::table('competency_gaps')->where('employee_id', $employeeId)->where('position_id', $positionId)->delete();
        foreach ($fit['items'] as $item) {
            DB::table('competency_gaps')->insert(TenantScope::fillForInsert('competency_gaps', [
                'employee_id' => $employeeId, 'position_id' => $positionId, 'competency_id' => $item['competency_id'],
                'competency_code' => $item['code'], 'required_level' => (int) $item['required'], 'criticality' => (int) $item['weight'],
                'actual' => $item['actual'], 'gap' => $item['gap'] === null ? null : round($item['gap'], 2), 'computed_at' => $now,
            ]));
        }

        $row = ['jfi' => $fit['jfi'] === null ? null : round($fit['jfi'], 2), 'coverage' => round($fit['coverage'], 2),
            'band' => $fit['band'], 'complete' => $fit['complete'], 'computed_at' => $now];
        if ($assessmentId) {
            $row['assessment_id'] = $assessmentId;
        }
        $existing = TenantScope::table('job_fit_results')->where('employee_id', $employeeId)->where('position_id', $positionId)->value('id');
        $existing
            ? DB::table('job_fit_results')->where('id', $existing)->update($row)
            : DB::table('job_fit_results')->insert(TenantScope::fillForInsert('job_fit_results', $row + ['employee_id' => $employeeId, 'position_id' => $positionId]));
        // Tabel di atas = kondisi terkini (ditimpa); riwayat menyimpan setiap perhitungan
        $this->history->recordFit($employeeId, $positionId, $assessmentId, $fit, $assessmentId ? 'assessment' : 'recalculation', $now);

        return $fit;
    }

    /** JFI tanpa menyimpan (dipakai juga untuk simulasi kandidat posisi lain). */
    public function fitFor(int $employeeId, int $positionId): array
    {
        $actuals = $this->currentScores($employeeId);
        $items = $this->profiles->forPosition($positionId)->map(fn ($c) => [
            'competency_id' => (int) $c->competency_id, 'code' => $c->code, 'name' => $c->name,
            'weight' => (int) $c->criticality, 'required' => (int) $c->required_level,
            'actual' => $actuals[$c->code] ?? null,
        ])->all();

        return (new JobFitCalculator($this->jfiBands()))->calculate($items);
    }

    /** @return array<string, float> competency_code => skor yang masih berlaku */
    public function currentScores(int $employeeId): array
    {
        return TenantScope::table('employee_competencies')->where('employee_id', $employeeId)
            ->where('valid_until', '>=', now()->toDateString())
            ->pluck('score', 'competency_code')->map(fn ($v) => (float) $v)->all();
    }

    private function suggestIdp(object $assessment, array $items): void
    {
        foreach ($items as $item) {
            // Draf IDP untuk setiap gap negatif kompetensi kritis (lebih luas dari metrik gap kritis K3)
            if ((int) $item['weight'] !== JobFitCalculator::CRITICAL_WEIGHT || $item['gap'] === null || $item['gap'] >= 0) {
                continue;
            }
            DB::table('competency_idp_suggestions')->insertOrIgnore(TenantScope::fillForInsert('competency_idp_suggestions', [
                'employee_id' => $assessment->employee_id, 'competency_id' => $item['competency_id'], 'competency_code' => $item['code'],
                'current_level' => (int) floor((float) ($item['actual'] ?? 0)), 'target_level' => (int) $item['required'],
                'assessment_id' => $assessment->id, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
            ]));
        }
    }

    // ------------------------------------------------------------ pengaturan

    public function raterWeights(): array
    {
        $value = $this->setting('competency.rater_weights');

        return is_array($value) ? array_merge(RaterAggregator::DEFAULT_WEIGHTS, array_map('floatval', $value)) : RaterAggregator::DEFAULT_WEIGHTS;
    }

    public function jfiBands(): array
    {
        $value = $this->setting('competency.jfi_bands');

        return is_array($value) && count($value) === 2 ? [(float) $value[0], (float) $value[1]] : [90.0, 75.0];
    }

    private function setting(string $key): mixed
    {
        $raw = DB::table('tenant_settings')->where('tenant_id', TenantScope::tenantId())->where('key', $key)->value('value');

        return $raw === null ? null : json_decode($raw, true);
    }

    // ------------------------------------------------------------------ bantuan

    public function assessment(int $id): object
    {
        return TenantScope::table('competency_assessments')->where('id', $id)->first() ?? abort(404);
    }

    public function currentPosition(int $employeeId): ?int
    {
        $today = now()->toDateString();
        $id = TenantScope::table('employee_assignments')->where('employee_id', $employeeId)->where('type', 'primary')
            ->where('effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->orderByDesc('effective_from')->value('position_id');

        return $id ? (int) $id : null;
    }
}
