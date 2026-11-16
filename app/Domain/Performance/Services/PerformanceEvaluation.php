<?php

namespace App\Domain\Performance\Services;

use App\Domain\Workflow\Services\ApproverResolver;
use App\Domain\Workflow\Services\Notifier;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penilaian akhir per rencana (blueprint 8.9 langkah 8): self assessment → penilaian atasan →
 * review atasan dari atasan (one-over-one). Skor:
 *   What        = capaian KPI terakhir (monitoring), KPI tanpa realisasi = 0
 *   How         = rata-rata skor perilaku atasan → konversi 1–5
 *   Kontribusi  = skor atasan 1–5 → konversi
 *   Skor akhir  = bobot kebijakan (override per level jabatan); skor diri sendiri informatif.
 */
final class PerformanceEvaluation
{
    public const STATUS = ['self' => 'Penilaian diri', 'manager' => 'Penilaian atasan', 'review' => 'Review atasan dari atasan', 'completed' => 'Selesai'];

    public function __construct(
        private PerformancePlans $plans,
        private PerformancePeriods $periods,
        private PerformanceMonitoring $monitoring,
        private ApproverResolver $resolver,
        private Notifier $notifier,
    ) {}

    /** Dipanggil saat periode masuk tahap penilaian: buat penilaian untuk semua rencana yang disetujui. */
    public function openForPeriod(object $period): int
    {
        $count = 0;
        $plans = TenantScope::table('performance_plans')->join('employees', 'employees.id', '=', 'performance_plans.employee_id')
            ->where('performance_plans.period_id', $period->id)->where('performance_plans.status', 'approved')
            ->get(['performance_plans.id', 'employees.user_id']);
        foreach ($plans as $plan) {
            $created = DB::table('performance_assessments')->insertOrIgnore(TenantScope::fillForInsert('performance_assessments', [
                'plan_id' => $plan->id, 'status' => 'self', 'created_at' => now(), 'updated_at' => now(),
            ]));
            if ($created && $plan->user_id) {
                $count++;
                $this->notifier->send((int) $plan->user_id, 'Penilaian kinerja dibuka', 'Isi penilaian diri untuk '.$period->name.'.',
                    'performance/assessments/'.$plan->id, 'fa fa-clipboard-check');
            }
        }

        return $count;
    }

    public function forPlan(int $planId): object
    {
        return TenantScope::table('performance_assessments')->where('plan_id', $planId)->first() ?? abort(404);
    }

    /** @return Collection<string, Collection> rater => detail (dikunci per item_type:objective/kode) */
    public function details(int $assessmentId): Collection
    {
        return TenantScope::table('performance_assessment_details')->where('assessment_id', $assessmentId)->get()
            ->groupBy('rater')->map(fn ($rows) => $rows->keyBy(fn ($d) => $d->item_type.':'.($d->objective_id ?? $d->item_code ?? '-')));
    }

    /** Peran pengguna pada penilaian: owner, manager, reviewer, atau null. */
    public function roleOf(object $assessment, object $plan, int $userId): ?string
    {
        if ((int) $plan->user_id === $userId) {
            return 'owner';
        }
        $manager = $assessment->manager_user_id ? (int) $assessment->manager_user_id : ($this->supervisors($plan)[0] ?? null);
        if ($manager === $userId) {
            return 'manager';
        }
        if ($assessment->reviewer_user_id && (int) $assessment->reviewer_user_id === $userId) {
            return 'reviewer';
        }

        return null;
    }

    // ------------------------------------------------------------ simpan

    /**
     * @param  array{summary?:?string, how?:array<string,mixed>, how_comment?:array<string,?string>, contribution?:mixed, contribution_comment?:?string, kpi_comment?:array<int,?string>}  $data
     */
    public function saveSelf(int $planId, int $userId, array $data, bool $submit): void
    {
        $plan = $this->plans->plan($planId);
        $assessment = $this->forPlan($planId);
        if ($assessment->status !== 'self' || $this->roleOf($assessment, $plan, $userId) !== 'owner') {
            throw ValidationException::withMessages(['assessment' => 'Penilaian diri tidak dapat diubah pada tahap ini.']);
        }
        $period = $this->periods->find((int) $plan->period_id);

        DB::transaction(function () use ($assessment, $plan, $period, $data, $submit) {
            $this->storeDetails($assessment, 'self', $plan, $period, $data, $submit);
            $update = ['self_summary' => trim((string) ($data['summary'] ?? '')) ?: null, 'updated_at' => now()];
            if ($submit) {
                $manager = $this->supervisors($plan)[0]
                    ?? throw ValidationException::withMessages(['assessment' => 'Atasan penilai tidak ditemukan. Hubungi HR.']);
                $update += ['status' => 'manager', 'self_submitted_at' => now(), 'manager_user_id' => $manager];
                $this->notifier->send($manager, 'Penilaian kinerja bawahan', $plan->full_name.' telah mengirim penilaian diri.',
                    'performance/assessments/'.$plan->id, 'fa fa-clipboard-check');
                AuditLog::updated('performance_assessments', $assessment->id, ['status' => 'self'], ['status' => 'manager'], 'Penilaian diri dikirim');
            }
            DB::table('performance_assessments')->where('id', $assessment->id)->update($update);
        });
    }

    public function saveManager(int $planId, int $userId, array $data, bool $submit): void
    {
        $plan = $this->plans->plan($planId);
        $assessment = $this->forPlan($planId);
        if ($assessment->status !== 'manager' || $this->roleOf($assessment, $plan, $userId) !== 'manager') {
            throw ValidationException::withMessages(['assessment' => 'Penilaian atasan tidak dapat diubah pada tahap ini.']);
        }
        $period = $this->periods->find((int) $plan->period_id);

        DB::transaction(function () use ($assessment, $plan, $period, $data, $submit, $userId) {
            $this->storeDetails($assessment, 'manager', $plan, $period, $data, $submit);
            $update = ['manager_summary' => trim((string) ($data['summary'] ?? '')) ?: null, 'updated_at' => now()];
            if ($submit) {
                if (trim((string) ($data['summary'] ?? '')) === '') {
                    throw ValidationException::withMessages(['summary' => 'Ringkasan penilaian atasan wajib diisi.']);
                }
                $update += $this->scores($assessment->id, $plan, $period);
                // Atasan dari atasan: pemangku posisi di atas posisi atasan penilai (bukan pegawai/atasan itu sendiri)
                $reviewer = $this->reviewerFor($plan, $userId);
                $update += $reviewer
                    ? ['status' => 'review', 'manager_submitted_at' => now(), 'reviewer_user_id' => $reviewer]
                    : ['status' => 'completed', 'manager_submitted_at' => now(), 'reviewed_at' => now(), 'review_note' => 'Tanpa atasan dari atasan — selesai otomatis'];
                if ($reviewer) {
                    $this->notifier->send($reviewer, 'Review penilaian kinerja', 'Penilaian '.$plan->full_name.' menunggu review Anda.',
                        'performance/assessments/'.$plan->id, 'fa fa-clipboard-check');
                }
                AuditLog::updated('performance_assessments', $assessment->id, ['status' => 'manager'],
                    ['status' => $update['status'], 'skor' => $update['final_score'], 'band' => $update['band']], 'Penilaian atasan dikirim');
            }
            DB::table('performance_assessments')->where('id', $assessment->id)->update($update);
        });
    }

    /** Atasan dari atasan menyetujui, atau mengembalikan ke atasan dengan catatan. */
    public function review(int $planId, int $userId, bool $agree, ?string $note): void
    {
        $plan = $this->plans->plan($planId);
        $assessment = $this->forPlan($planId);
        if ($assessment->status !== 'review' || $this->roleOf($assessment, $plan, $userId) !== 'reviewer') {
            throw ValidationException::withMessages(['assessment' => 'Review tidak tersedia.']);
        }
        if (! $agree && trim((string) $note) === '') {
            throw ValidationException::withMessages(['review_note' => 'Catatan wajib diisi bila penilaian dikembalikan.']);
        }

        DB::table('performance_assessments')->where('id', $assessment->id)->update([
            'status' => $agree ? 'completed' : 'manager', 'review_note' => trim((string) $note) ?: null,
            'reviewed_at' => $agree ? now() : null, 'updated_at' => now(),
        ]);
        AuditLog::updated('performance_assessments', $assessment->id, ['status' => 'review'], ['status' => $agree ? 'completed' : 'manager'],
            $agree ? 'Penilaian disetujui atasan dari atasan' : 'Penilaian dikembalikan ke atasan');
        $notify = $agree ? (int) $plan->user_id : (int) $assessment->manager_user_id;
        if ($notify) {
            $this->notifier->send($notify, $agree ? 'Penilaian kinerja selesai' : 'Penilaian dikembalikan',
                $agree ? 'Penilaian kinerja Anda telah selesai direview.' : 'Penilaian '.$plan->full_name.' dikembalikan: '.$note,
                'performance/assessments/'.$plan->id, 'fa fa-clipboard-check');
        }
    }

    private function storeDetails(object $assessment, string $rater, object $plan, object $period, array $data, bool $submit): void
    {
        $policy = $period->policy;
        $errors = [];
        $rows = [];
        foreach ($policy['how_items'] as $item) {
            $score = $data['how'][$item['code']] ?? null;
            if ($submit && ! $this->validScore($score)) {
                $errors['how.'.$item['code']] = 'Skor "'.$item['label'].'" wajib 1–5.';
            }
            $rows[] = ['item_type' => 'how', 'item_code' => $item['code'], 'objective_id' => null, 'score' => $this->validScore($score) ? (int) $score : null,
                'comment' => $data['how_comment'][$item['code']] ?? null];
        }
        if ((float) $policy['weights']['contribution'] > 0 || isset($data['contribution'])) {
            $score = $data['contribution'] ?? null;
            if ($submit && ! $this->validScore($score)) {
                $errors['contribution'] = 'Skor Kontribusi tambahan wajib 1–5.';
            }
            $rows[] = ['item_type' => 'contribution', 'item_code' => 'CONTRIB', 'objective_id' => null, 'score' => $this->validScore($score) ? (int) $score : null,
                'comment' => $data['contribution_comment'] ?? null];
        }
        foreach ($this->monitoring->activeObjectives($plan->id) as $o) {
            $rows[] = ['item_type' => 'kpi', 'item_code' => null, 'objective_id' => (int) $o->id, 'score' => null, 'comment' => $data['kpi_comment'][$o->id] ?? null];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        TenantScope::table('performance_assessment_details')->where('assessment_id', $assessment->id)->where('rater', $rater)->delete();
        foreach ($rows as $row) {
            DB::table('performance_assessment_details')->insert(TenantScope::fillForInsert('performance_assessment_details', $row + [
                'assessment_id' => $assessment->id, 'rater' => $rater,
                'comment' => mb_substr(trim((string) $row['comment']), 0, 2000) ?: null, 'created_at' => now(), 'updated_at' => now(),
            ]));
        }
    }

    private function validScore(mixed $v): bool
    {
        return is_numeric($v) && (int) $v >= 1 && (int) $v <= 5;
    }

    // ------------------------------------------------------------ skor

    /** Skor dimensi & akhir dari skor atasan dan realisasi KPI terakhir. */
    public function scores(int $assessmentId, object $plan, object $period): array
    {
        $calc = $this->monitoring->calculator($period);
        $progress = $this->monitoring->progress($plan, $period);
        $what = $calc->whatScore($progress['items']->map(fn ($o) => ['weight' => (float) $o->weight, 'achievement' => $o->achievement ?? 0])->all());

        $manager = TenantScope::table('performance_assessment_details')->where('assessment_id', $assessmentId)->where('rater', 'manager')->get();
        $how = $calc->behaviorScore($manager->where('item_type', 'how')->pluck('score')->all());
        $contribScore = $manager->firstWhere('item_type', 'contribution')?->score;
        $contribution = $contribScore ? $calc->convert((float) $contribScore) : null;

        $weights = $calc->weightsFor($this->jobLevel((int) $plan->position_id));
        $final = $calc->finalScore($what, $how, $contribution, $weights);

        return [
            'what_score' => round($what, 2), 'how_score' => $how !== null ? round($how, 2) : null,
            'contribution_score' => $contribution !== null ? round($contribution, 2) : null,
            'final_raw' => $final['raw'], 'final_score' => $final['score'], 'band' => $final['band'], 'weights' => json_encode($weights),
            'calibrated_score' => null, 'calibrated_band' => null,
        ];
    }

    public function jobLevel(int $positionId): ?int
    {
        $level = TenantScope::table('positions')->leftJoin('job_grades as g', 'g.id', '=', 'positions.grade_id')
            ->leftJoin('jobs_catalog as j', 'j.id', '=', 'positions.job_id')->leftJoin('job_grades as gm', 'gm.id', '=', 'j.grade_min_id')
            ->where('positions.id', $positionId)->selectRaw('COALESCE(g.job_level_id, gm.job_level_id) as level')->value('level');

        return $level ? (int) $level : null;
    }

    /** @return int[] */
    public function supervisors(object $plan): array
    {
        return collect($this->resolver->escalationFrom((int) $plan->position_id, (int) $plan->user_id))->pluck('user_id')->map(fn ($v) => (int) $v)->all();
    }

    private function reviewerFor(object $plan, int $managerUserId): ?int
    {
        $managerPosition = $this->resolver->positionOf($managerUserId);
        if (! $managerPosition) {
            return null;
        }
        foreach ($this->resolver->escalationFrom($managerPosition, $managerUserId) as $c) {
            if ((int) $c['user_id'] !== (int) $plan->user_id) {
                return (int) $c['user_id'];
            }
        }

        return null;
    }

    /** Skor efektif (setelah kalibrasi) sebuah penilaian. */
    public static function effective(object $a): array
    {
        return [
            'score' => $a->calibrated_score !== null ? (float) $a->calibrated_score : ($a->final_score !== null ? (float) $a->final_score : null),
            'band' => $a->calibrated_band ?? $a->band,
        ];
    }
}
