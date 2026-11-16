<?php

namespace App\Domain\Performance\Services;

use App\Domain\Workflow\Services\Notifier;
use App\Support\Audit\AuditLog;
use App\Support\Security\Roles;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pengesahan (blueprint 8.9 langkah 10–11): rating per pegawai per periode = prorata skor
 * (setelah kalibrasi) antar-rencana; disimpan bersama snapshot immutable yang memuat versi
 * kebijakan. Koreksi (mis. keberatan dikabulkan) = revisi baru + snapshot baru.
 * Keberatan dalam jendela `appeal_days` (default 14 hari), diputus pejabat di atas penilai (SoD).
 */
final class PerformanceRatings
{
    public const APPEAL_STATUS = ['submitted' => 'Menunggu keputusan', 'upheld' => 'Ditolak (rating tetap)', 'adjusted' => 'Dikabulkan (rating diubah)'];

    public function __construct(private PerformancePeriods $periods, private PerformanceMonitoring $monitoring, private Notifier $notifier) {}

    /**
     * Umumkan rating sementara seluruh pegawai periode; periode masuk tahap keberatan.
     * Rating baru final (dan di-snapshot) lewat finalize() setelah jendela keberatan berakhir.
     */
    public function announce(int $periodId, int $userId): int
    {
        $period = $this->periods->find($periodId);
        if ($period->status !== 'calibration') {
            throw ValidationException::withMessages(['period' => 'Rating hanya dapat diumumkan pada tahap kalibrasi.']);
        }
        if (TenantScope::table('calibration_sessions')->where('period_id', $period->id)->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['period' => 'Masih ada sesi kalibrasi yang terbuka.']);
        }

        $calc = $this->monitoring->calculator($period);
        $rows = TenantScope::table('performance_assessments')
            ->join('performance_plans', 'performance_plans.id', '=', 'performance_assessments.plan_id')
            ->join('employees', 'employees.id', '=', 'performance_plans.employee_id')
            ->where('performance_plans.period_id', $period->id)->where('performance_assessments.status', 'completed')
            ->orderBy('performance_plans.start_date')
            ->get(['performance_assessments.*', 'performance_plans.employee_id', 'performance_plans.start_date', 'performance_plans.end_date', 'employees.user_id'])
            ->groupBy('employee_id');

        $closesOn = now()->addDays((int) $period->policy['appeal_days'])->toDateString();

        return DB::transaction(function () use ($period, $calc, $rows, $userId, $closesOn) {
            $count = 0;
            foreach ($rows as $employeeId => $assessments) {
                $segments = $assessments->map(fn ($a) => [
                    'days' => Carbon::parse($a->start_date)->diffInDays(Carbon::parse($a->end_date)) + 1,
                    'score' => PerformanceEvaluation::effective($a)['score'],
                ])->values()->all();
                $result = $calc->prorate($segments);
                // Satu penugasan: band hasil kalibrasi dipakai apa adanya; beberapa penugasan: band dari skor prorata
                $band = ! $result['rated'] ? null
                    : ($assessments->count() === 1 ? PerformanceEvaluation::effective($assessments->first())['band'] : $calc->band($result['score']));
                $raw = $result['rated'] ? round($assessments->sum(fn ($a) => (float) $a->final_raw * $this->share($a, $segments, $assessments)), 2) : null;

                $this->createRating($period, (int) $employeeId, 1, $result['rated'], $raw, $result['score'], $band, null, $userId, $closesOn);
                $count++;
                if ($assessments->first()->user_id) {
                    $this->notifier->send((int) $assessments->first()->user_id, 'Rating kinerja diumumkan',
                        'Rating sementara '.$period->name.' telah diumumkan. Keberatan dapat diajukan s.d. '.$closesOn.'.',
                        'performance/my-ratings', 'fa fa-award');
                }
            }
            DB::table('performance_periods')->where('id', $period->id)->update(['status' => 'appeal', 'appeal_closes_on' => $closesOn, 'updated_at' => now()]);
            AuditLog::updated('performance_periods', $period->id, ['status' => 'calibration'], ['status' => 'appeal', 'rating' => $count],
                'Rating kinerja sementara diumumkan; keberatan s.d. '.$closesOn);

            return $count;
        });
    }

    /**
     * Pengesahan final: jendela keberatan berakhir dan semua keberatan sudah diputus.
     * Rating terkini menjadi final dan di-snapshot (immutable); periode ditutup.
     */
    public function finalize(int $periodId, int $userId): int
    {
        $period = $this->periods->find($periodId);
        if ($period->status !== 'appeal') {
            throw ValidationException::withMessages(['period' => 'Pengesahan final dilakukan setelah rating diumumkan.']);
        }
        if (now()->toDateString() <= $period->appeal_closes_on) {
            throw ValidationException::withMessages(['period' => 'Jendela keberatan masih terbuka s.d. '.$period->appeal_closes_on.'.']);
        }
        $ratingIds = TenantScope::table('performance_ratings')->where('period_id', $period->id)->pluck('id');
        $pending = TenantScope::table('performance_appeals')->whereIn('rating_id', $ratingIds)->where('status', 'submitted')->count();
        if ($pending) {
            throw ValidationException::withMessages(['period' => 'Masih ada '.$pending.' keberatan yang belum diputus.']);
        }

        return DB::transaction(function () use ($period, $userId) {
            $ratings = TenantScope::table('performance_ratings')->where('period_id', $period->id)->where('is_current', true)->get();
            foreach ($ratings as $rating) {
                DB::table('performance_ratings')->where('id', $rating->id)->update([
                    'status' => 'final', 'finalized_at' => now(), 'finalized_by' => $userId, 'updated_at' => now(),
                ]);
                $this->snapshot(TenantScope::table('performance_ratings')->where('id', $rating->id)->first(), $period);
                $owner = (int) TenantScope::table('employees')->where('id', $rating->employee_id)->value('user_id');
                if ($owner) {
                    $this->notifier->send($owner, 'Rating kinerja disahkan', 'Rating '.$period->name.' telah disahkan final.', 'performance/my-ratings', 'fa fa-award');
                }
            }
            DB::table('performance_periods')->where('id', $period->id)->update(['status' => 'closed', 'updated_at' => now()]);
            AuditLog::updated('performance_periods', $period->id, ['status' => 'appeal'], ['status' => 'closed', 'rating' => $ratings->count()],
                'Rating kinerja disahkan final & di-snapshot');

            return $ratings->count();
        });
    }

    private function share(object $a, array $segments, $assessments): float
    {
        $total = array_sum(array_column($segments, 'days')) ?: 1;
        $days = Carbon::parse($a->start_date)->diffInDays(Carbon::parse($a->end_date)) + 1;

        return $days / $total;
    }

    /** Rating sementara (provisional); snapshot dibuat saat pengesahan final. */
    private function createRating(object $period, int $employeeId, int $revision, bool $rated, ?float $raw, ?float $score, ?string $band, ?string $reason, int $userId, string $appealDeadline): object
    {
        $id = DB::table('performance_ratings')->insertGetId(TenantScope::fillForInsert('performance_ratings', [
            'uuid' => (string) Str::uuid7(), 'period_id' => $period->id, 'employee_id' => $employeeId, 'revision' => $revision, 'is_current' => true,
            'status' => 'provisional', 'rated' => $rated, 'raw_score' => $raw, 'final_score' => $score, 'band' => $band,
            'band_label' => $band ? (PerformancePolicy::bandLabels($period->policy)[$band] ?? $band) : 'Not rated',
            'reason' => $reason, 'published_at' => now(), 'appeal_deadline' => $appealDeadline,
            'published_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]));
        $rating = TenantScope::table('performance_ratings')->where('id', $id)->first();
        AuditLog::created('performance_ratings', $id, ['pegawai' => $employeeId, 'revisi' => $revision, 'skor' => $score, 'band' => $band],
            $revision > 1 ? 'Rating kinerja direvisi' : 'Rating kinerja sementara diumumkan');

        return $rating;
    }

    /** Snapshot immutable: rating, kebijakan terkunci, rencana & KPI (termasuk versi lama), penilaian, kalibrasi, keberatan. */
    private function snapshot(object $rating, object $period): void
    {
        $plans = TenantScope::table('performance_plans')->where('period_id', $period->id)->where('employee_id', $rating->employee_id)->get();
        $planIds = $plans->pluck('id');
        $assessments = TenantScope::table('performance_assessments')->whereIn('plan_id', $planIds)->get();
        $payload = [
            'rating' => (array) $rating,
            'policy' => $period->policy,
            'period' => ['id' => $period->id, 'code' => $period->code, 'name' => $period->name, 'start' => $period->start_date, 'end' => $period->end_date],
            'plans' => $plans->map(fn ($p) => (array) $p)->all(),
            'objectives' => TenantScope::table('performance_objectives')->whereIn('plan_id', $planIds)->get()->map(fn ($o) => (array) $o)->all(),
            'kpi_versions' => TenantScope::table('performance_plan_versions')->whereIn('plan_id', $planIds)->get()->map(fn ($v) => (array) $v)->all(),
            'updates' => TenantScope::table('performance_updates')->whereIn('plan_id', $planIds)->get()->map(fn ($u) => (array) $u)->all(),
            'assessments' => $assessments->map(fn ($a) => (array) $a)->all(),
            'assessment_details' => TenantScope::table('performance_assessment_details')->whereIn('assessment_id', $assessments->pluck('id'))->get()->map(fn ($d) => (array) $d)->all(),
            'calibrations' => TenantScope::table('calibration_adjustments')->whereIn('assessment_id', $assessments->pluck('id'))->get()->map(fn ($c) => (array) $c)->all(),
            'appeals' => TenantScope::table('performance_appeals')->where('employee_id', $rating->employee_id)
                ->whereIn('rating_id', TenantScope::table('performance_ratings')->where('period_id', $period->id)->pluck('id'))->get()->map(fn ($a) => (array) $a)->all(),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        DB::table('performance_snapshots')->insert(TenantScope::fillForInsert('performance_snapshots', [
            'rating_id' => $rating->id, 'payload' => $json, 'checksum' => hash('sha256', $json), 'created_at' => now(),
        ]));
    }

    // ------------------------------------------------------------ keberatan

    public function current(int $periodId, int $employeeId): ?object
    {
        return TenantScope::table('performance_ratings')->where('period_id', $periodId)->where('employee_id', $employeeId)->where('is_current', true)->first();
    }

    public function appeal(int $ratingId, int $userId, string $reason): int
    {
        $rating = TenantScope::table('performance_ratings')->where('id', $ratingId)->first() ?? abort(404);
        $employee = TenantScope::table('employees')->where('id', $rating->employee_id)->first();
        abort_unless((int) $employee->user_id === $userId, 404);
        if (! $rating->is_current || ! $rating->rated || $rating->status !== 'provisional'
            || TenantScope::table('performance_periods')->where('id', $rating->period_id)->value('status') !== 'appeal') {
            throw ValidationException::withMessages(['reason' => 'Keberatan hanya untuk rating sementara yang masih dalam tahap keberatan.']);
        }
        if (now()->toDateString() > $rating->appeal_deadline) {
            throw ValidationException::withMessages(['reason' => 'Jendela keberatan sudah berakhir pada '.$rating->appeal_deadline.'.']);
        }
        if (TenantScope::table('performance_appeals')->where('employee_id', $rating->employee_id)
            ->whereIn('rating_id', TenantScope::table('performance_ratings')->where('period_id', $rating->period_id)->pluck('id'))->exists()) {
            throw ValidationException::withMessages(['reason' => 'Keberatan untuk periode ini sudah pernah diajukan.']);
        }
        if (mb_strlen(trim($reason)) < 30) {
            throw ValidationException::withMessages(['reason' => 'Uraikan alasan keberatan (minimal 30 karakter) beserta buktinya.']);
        }

        $decider = $this->deciderFor($rating);
        $id = DB::table('performance_appeals')->insertGetId(TenantScope::fillForInsert('performance_appeals', [
            'rating_id' => $rating->id, 'employee_id' => $rating->employee_id, 'reason' => trim($reason), 'status' => 'submitted',
            'decider_user_id' => $decider, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]));
        AuditLog::created('performance_appeals', $id, ['rating' => $rating->id], 'Keberatan rating kinerja diajukan');
        if ($decider) {
            $this->notifier->send($decider, 'Keberatan rating kinerja', $employee->full_name.' mengajukan keberatan atas rating kinerjanya.',
                'performance/appeals', 'fa fa-gavel');
        }

        return $id;
    }

    /** Pejabat di atas penilai: reviewer (atasan dari atasan) penilaian terakhir; kosong = HR yang memutus. */
    public function deciderFor(object $rating): ?int
    {
        $assessment = TenantScope::table('performance_assessments')->join('performance_plans', 'performance_plans.id', '=', 'performance_assessments.plan_id')
            ->where('performance_plans.period_id', $rating->period_id)->where('performance_plans.employee_id', $rating->employee_id)
            ->orderByDesc('performance_plans.start_date')->first(['performance_assessments.reviewer_user_id']);

        return $assessment && $assessment->reviewer_user_id ? (int) $assessment->reviewer_user_id : null;
    }

    /** Pengguna yang boleh memutus: decider yang ditetapkan, atau HR (Tenant/HR Admin) bila tidak ada; tidak boleh penilai/pegawai sendiri. */
    public function canDecide(object $appeal, int $userId, bool $isHr): bool
    {
        $rating = TenantScope::table('performance_ratings')->where('id', $appeal->rating_id)->first();
        $raters = TenantScope::table('performance_assessments')->join('performance_plans', 'performance_plans.id', '=', 'performance_assessments.plan_id')
            ->where('performance_plans.period_id', $rating->period_id)->where('performance_plans.employee_id', $rating->employee_id)
            ->pluck('performance_assessments.manager_user_id')->map(fn ($v) => (int) $v)->all();
        $owner = (int) TenantScope::table('employees')->where('id', $rating->employee_id)->value('user_id');
        if ($userId === $owner || in_array($userId, $raters, true)) {
            return false;
        }

        return $appeal->decider_user_id ? (int) $appeal->decider_user_id === $userId || $isHr : $isHr;
    }

    public function decide(int $appealId, int $userId, bool $isHr, bool $adjust, string $note, ?float $newScore): void
    {
        $appeal = TenantScope::table('performance_appeals')->where('id', $appealId)->first() ?? abort(404);
        $periodStatus = TenantScope::table('performance_ratings')->join('performance_periods', 'performance_periods.id', '=', 'performance_ratings.period_id')
            ->where('performance_ratings.id', $appeal->rating_id)->value('performance_periods.status');
        if ($appeal->status !== 'submitted' || $periodStatus !== 'appeal') {
            throw ValidationException::withMessages(['decision' => 'Keberatan sudah diputus.']);
        }
        if (! $this->canDecide($appeal, $userId, $isHr)) {
            abort(403, 'Keberatan diputus oleh pejabat di atas penilai (bukan penilai atau pegawai sendiri).');
        }
        if (mb_strlen(trim($note)) < 10) {
            throw ValidationException::withMessages(['decision_note' => 'Alasan keputusan wajib diisi.']);
        }
        $rating = TenantScope::table('performance_ratings')->where('id', $appeal->rating_id)->first();
        $period = $this->periods->find((int) $rating->period_id);
        $calc = $this->monitoring->calculator($period);
        if ($adjust && ($newScore === null || $newScore < 0 || $newScore > 100 || round($newScore, 2) == round((float) $rating->final_score, 2))) {
            throw ValidationException::withMessages(['new_score' => 'Skor baru (0–100) wajib diisi dan berbeda dari skor sekarang.']);
        }

        DB::transaction(function () use ($appeal, $rating, $period, $calc, $adjust, $note, $newScore, $userId) {
            $newBand = $adjust ? $calc->band($newScore) : null;
            DB::table('performance_appeals')->where('id', $appeal->id)->update([
                'status' => $adjust ? 'adjusted' : 'upheld', 'decision_note' => trim($note), 'decided_at' => now(),
                'decider_user_id' => $userId, 'new_score' => $adjust ? $newScore : null, 'new_band' => $newBand, 'updated_at' => now(),
            ]);
            AuditLog::updated('performance_appeals', $appeal->id, ['status' => 'submitted'], ['status' => $adjust ? 'adjusted' : 'upheld', 'skor_baru' => $newScore],
                'Keberatan rating diputus');

            if ($adjust) {
                DB::table('performance_ratings')->where('id', $rating->id)->update(['is_current' => false, 'updated_at' => now()]);
                $this->createRating($period, (int) $rating->employee_id, $rating->revision + 1, true, $rating->raw_score, round($newScore, 2), $newBand,
                    'Keberatan #'.$appeal->id.' dikabulkan: '.mb_substr(trim($note), 0, 400), $userId, $rating->appeal_deadline);
            }
            $owner = (int) TenantScope::table('employees')->where('id', $rating->employee_id)->value('user_id');
            if ($owner) {
                $this->notifier->send($owner, 'Keputusan keberatan', $adjust ? 'Keberatan Anda dikabulkan; rating diperbarui.' : 'Keberatan Anda ditolak; rating tetap.',
                    'performance/my-ratings', 'fa fa-gavel');
            }
        });
    }

    public static function hrGroups(): array
    {
        return array_filter([Roles::id(Roles::TENANT_ADMIN), Roles::id(Roles::HR_ADMIN), Roles::id(Roles::SUPER_ADMIN)]);
    }
}
