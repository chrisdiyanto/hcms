<?php

namespace App\Domain\Talent;

use App\Domain\Employee\Services\EmployeeScope;
use App\Domain\Organization\Services\OrgUnitClosure;
use App\Domain\Performance\Services\PerformancePeriods;
use App\Domain\Performance\Services\PerformancePolicy;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penilaian potensi & talent review (laporan CBHRM 9; kamus metrik §9, K7).
 *
 * Nine Box: sumbu kinerja = rating final terakhir pada periode review (Outstanding/Exceeds = tinggi,
 * Meets = sedang, Below/Unsatisfactory = rendah; band kustom dipetakan menurut urutan), sumbu potensi
 * = hasil instrumen potensi tervalidasi yang masih berlaku pada tanggal review. Kotak 1 = kinerja &
 * potensi rendah … kotak 9 = keduanya tinggi; kotak = indeks potensi × 3 + indeks kinerja + 1.
 * Not rated / tanpa potensi berlaku tidak diberi kotak (data belum lengkap).
 * Panel dapat menggeser kotak (kalibrasi) dengan justifikasi; flight risk dinilai panel dan hanya
 * terlihat dengan izin sensitif. Pengesahan oleh pemegang izin Approve selain pembuat review (SoD).
 */
class TalentReviews
{
    public const LEVELS = ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi'];

    public const LEVEL_INDEX = ['low' => 0, 'medium' => 1, 'high' => 2];

    public const BOX_LABELS = [
        1 => 'Perlu perhatian', 2 => 'Kontributor efektif', 3 => 'Profesional andal',
        4 => 'Inkonsisten', 5 => 'Pemain inti', 6 => 'Performer tinggi',
        7 => 'Berlian kasar', 8 => 'Bintang potensial', 9 => 'Bintang',
    ];

    public const STATUS = ['draft' => 'Draft', 'calibration' => 'Kalibrasi panel', 'approved' => 'Disahkan'];

    public const DATA_STATUS = ['complete' => 'Lengkap', 'missing_rating' => 'Tanpa rating final', 'missing_potential' => 'Tanpa potensi berlaku', 'missing_both' => 'Tanpa rating & potensi'];

    /** K7 */
    public const PERFORMANCE_MAP = ['outstanding' => 'high', 'exceeds' => 'high', 'meets' => 'medium', 'below' => 'low', 'unsatisfactory' => 'low'];

    public function __construct(private PerformancePeriods $periods) {}

    public static function box(?string $performance, ?string $potential): ?int
    {
        return $performance && $potential ? self::LEVEL_INDEX[$potential] * 3 + self::LEVEL_INDEX[$performance] + 1 : null;
    }

    /** @return array{0: string, 1: string} [kinerja, potensi] dari nomor kotak */
    public static function axes(int $box): array
    {
        $levels = array_keys(self::LEVEL_INDEX);

        return [$levels[($box - 1) % 3], $levels[intdiv($box - 1, 3)]];
    }

    /** K7; band kustom: dua band teratas = tinggi, band ketiga = sedang, sisanya rendah. */
    public static function performanceLevel(?string $band, array $policyBands): ?string
    {
        if ($band === null) {
            return null;
        }
        if (isset(self::PERFORMANCE_MAP[$band])) {
            return self::PERFORMANCE_MAP[$band];
        }
        $rank = collect($policyBands)->sortByDesc('min')->values()->search(fn ($b) => $b['code'] === $band);

        return $rank === false ? null : ($rank <= 1 ? 'high' : ($rank === 2 ? 'medium' : 'low'));
    }

    // ------------------------------------------------------------ penilaian potensi

    /**
     * @param  array<string, float>  $scores  kode kriteria => skor
     */
    public function assessPotential(int $employeeId, array $scores, string $confidence, string $evidence, int $assessorId): int
    {
        $instruments = app(PotentialInstruments::class);
        $instrument = $instruments->published() ?? throw ValidationException::withMessages(['instrument' => 'Belum ada instrumen potensi yang berlaku. Terbitkan di Talenta › Instrumen Potensi.']);
        $criteria = $instruments->criteria((int) $instrument->id);
        $missing = $criteria->reject(fn ($c) => isset($scores[$c->code]));
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['scores' => 'Semua kriteria wajib dinilai: '.$missing->pluck('code')->implode(', ').'.']);
        }
        foreach ($scores as $code => $score) {
            if ($score < 1 || $score > (int) $instrument->scale_max) {
                throw ValidationException::withMessages(['scores' => "Skor {$code} harus 1–{$instrument->scale_max}."]);
            }
        }
        $result = PotentialInstruments::evaluate($instrument, $criteria, $scores);

        return DB::transaction(function () use ($employeeId, $instrument, $criteria, $scores, $result, $confidence, $evidence, $assessorId) {
            $id = DB::table('potential_assessments')->insertGetId(TenantScope::fillForInsert('potential_assessments', [
                'employee_id' => $employeeId, 'instrument_id' => $instrument->id, 'assessor_user_id' => $assessorId,
                'assessed_at' => now()->toDateString(), 'valid_until' => now()->addMonths((int) $instrument->validity_months)->toDateString(),
                'score' => $result['score'], 'level' => $result['level'], 'complete' => $result['complete'], 'confidence' => $confidence,
                'evidence' => $evidence, 'status' => 'final', 'created_at' => now(), 'updated_at' => now(),
            ]));
            foreach ($criteria as $c) {
                DB::table('potential_assessment_scores')->insert(TenantScope::fillForInsert('potential_assessment_scores', [
                    'assessment_id' => $id, 'criterion_id' => $c->id, 'criterion_code' => $c->code, 'score' => $scores[$c->code],
                ]));
            }
            AuditLog::created('potential_assessments', $id, ['pegawai' => $employeeId, 'instrumen' => 'v'.$instrument->version,
                'skor' => $result['score'], 'tingkat' => $result['level'], 'keyakinan' => $confidence], 'Asesmen potensi dicatat');

            return $id;
        });
    }

    public function cancelPotential(int $id, string $reason): void
    {
        $row = $this->visiblePotential()->where('potential_assessments.id', $id)->where('potential_assessments.status', 'final')->first(['potential_assessments.id']) ?? abort(404);
        TenantScope::table('potential_assessments')->where('id', $row->id)->update(['status' => 'cancelled', 'cancel_reason' => $reason, 'updated_at' => now()]);
        AuditLog::updated('potential_assessments', $row->id, ['status' => 'final'], ['status' => 'cancelled', 'alasan' => $reason], 'Asesmen potensi dibatalkan');
    }

    public function visiblePotential()
    {
        return EmployeeScope::apply(TenantScope::table('potential_assessments')
            ->join('employees', 'employees.id', '=', 'potential_assessments.employee_id'), 'potential_assessments.employee_id');
    }

    // ------------------------------------------------------------ talent review

    public function review(int $id): ?object
    {
        $row = TenantScope::table('talent_reviews')->join('performance_periods as pp', 'pp.id', '=', 'talent_reviews.performance_period_id')
            ->leftJoin('unit as u', 'u.id_unit', '=', 'talent_reviews.unit_id')->where('talent_reviews.id', $id)
            ->first(['talent_reviews.*', 'pp.name as period_name', 'u.unit_name']);
        if ($row) {
            $row->panel = array_map('intval', json_decode($row->panel_user_ids, true) ?: []);
        }

        return $row;
    }

    /** Isi/perbarui peserta review dari data scope pembuat; keputusan panel yang ada dipertahankan. */
    public function populate(int $reviewId): array
    {
        $review = $this->editable($reviewId);
        $period = $this->periods->find((int) $review->performance_period_id);
        $policy = $period->policy ?? PerformancePolicy::DEFAULTS;
        $today = $review->review_date;

        $employees = EmployeeScope::query()->where('employees.status', 'active')
            ->join('employee_assignments as a', function ($j) use ($today) {
                $j->on('a.employee_id', '=', 'employees.id')->where('a.type', 'primary')->where('a.effective_from', '<=', $today)
                    ->where(fn ($w) => $w->whereNull('a.effective_to')->orWhere('a.effective_to', '>=', $today));
            })->join('positions as p', 'p.id', '=', 'a.position_id')
            ->when($review->unit_id, fn ($q) => $q->whereIn('p.unit_id', OrgUnitClosure::descendantsOf([(int) $review->unit_id])))
            ->get(['employees.id', 'p.unit_id'])->unique('id')->keyBy('id');
        $ratings = TenantScope::table('performance_ratings')->where('period_id', $period->id)->where('is_current', true)->where('status', 'final')
            ->where('rated', true)->whereIn('employee_id', $employees->keys())->get(['id', 'employee_id', 'band'])->keyBy('employee_id');
        $potentials = TenantScope::table('potential_assessments')->whereIn('employee_id', $employees->keys())->where('status', 'final')
            ->where('assessed_at', '<=', $today)->where('valid_until', '>=', $today)->orderBy('assessed_at')->orderBy('id')
            ->get(['id', 'employee_id', 'level'])->keyBy('employee_id'); // terakhir menang

        return DB::transaction(function () use ($review, $employees, $ratings, $potentials, $policy) {
            $existing = TenantScope::table('talent_review_entries')->where('review_id', $review->id)->get()->keyBy('employee_id');
            $added = 0;
            foreach ($employees as $employeeId => $e) {
                $rating = $ratings->get($employeeId);
                $potential = $potentials->get($employeeId);
                $perf = self::performanceLevel($rating->band ?? null, $policy['bands']);
                $proposed = self::box($perf, $potential->level ?? null);
                $data = [
                    'unit_id' => $e->unit_id, 'rating_id' => $rating->id ?? null, 'performance_band' => $rating->band ?? null, 'performance_level' => $perf,
                    'potential_assessment_id' => $potential->id ?? null, 'potential_level' => $potential->level ?? null, 'proposed_box' => $proposed,
                    'data_status' => match (true) {
                        ! $perf && ! $potential => 'missing_both', ! $perf => 'missing_rating', ! $potential => 'missing_potential', default => 'complete'
                    },
                    'updated_at' => now(),
                ];
                $old = $existing->get($employeeId);
                if ($old) {
                    // Kotak final mengikuti usulan baru kecuali panel sudah menggesernya dengan justifikasi
                    $data['final_box'] = $old->final_box !== null && $old->final_box != $old->proposed_box ? $old->final_box : $proposed;
                    TenantScope::table('talent_review_entries')->where('id', $old->id)->update($data);
                } else {
                    DB::table('talent_review_entries')->insert(TenantScope::fillForInsert('talent_review_entries', $data + [
                        'review_id' => $review->id, 'employee_id' => $employeeId, 'final_box' => $proposed, 'created_at' => now()]));
                    $added++;
                }
            }
            $removed = $existing->keys()->diff($employees->keys());
            TenantScope::table('talent_review_entries')->where('review_id', $review->id)->whereIn('employee_id', $removed)->delete();
            AuditLog::updated('talent_reviews', $review->id, null, ['peserta' => $employees->count(), 'baru' => $added, 'keluar' => $removed->count()], 'Peserta talent review diperbarui');

            return ['total' => $employees->count(), 'added' => $added, 'removed' => $removed->count()];
        });
    }

    /** Keputusan panel atas satu pegawai. */
    public function decide(int $entryId, array $data, int $userId): void
    {
        $entry = TenantScope::table('talent_review_entries')->where('id', $entryId)->first() ?? abort(404);
        $review = $this->editable((int) $entry->review_id);
        $data = collect($data)->only(['final_box', 'justification', 'flight_risk', 'flight_risk_reason', 'development_action', 'action_owner_user_id', 'next_review_date'])->all();
        if ($entry->proposed_box === null && ! empty($data['final_box'])) {
            throw ValidationException::withMessages(['final_box' => 'Data kinerja/potensi belum lengkap: pegawai ini belum dapat ditempatkan di Nine Box.']);
        }
        if (! empty($data['final_box']) && (int) $data['final_box'] !== (int) $entry->proposed_box && trim((string) ($data['justification'] ?? '')) === '') {
            throw ValidationException::withMessages(['justification' => 'Justifikasi panel wajib bila kotak final berbeda dari usulan.']);
        }
        if (! empty($data['flight_risk']) && trim((string) ($data['flight_risk_reason'] ?? '')) === '') {
            throw ValidationException::withMessages(['flight_risk_reason' => 'Alasan risiko kehilangan wajib diisi.']);
        }
        TenantScope::table('talent_review_entries')->where('id', $entryId)->update($data + ['updated_at' => now()]);
        AuditLog::updated('talent_review_entries', $entryId, collect((array) $entry)->only(array_keys($data))->all(), $data, 'Keputusan panel talent review');
        if ($review->status === 'draft') {
            TenantScope::table('talent_reviews')->where('id', $review->id)->update(['status' => 'calibration', 'updated_at' => now()]);
        }
    }

    /** Pengesahan: pemegang izin Approve, bukan pembuat review (SoD). */
    public function approve(int $reviewId, int $userId): void
    {
        $review = $this->editable($reviewId);
        if ((int) $review->created_by === $userId) {
            throw ValidationException::withMessages(['status' => 'Pembuat review tidak dapat mengesahkan review-nya sendiri.']);
        }
        if (! TenantScope::table('talent_review_entries')->where('review_id', $reviewId)->exists()) {
            throw ValidationException::withMessages(['status' => 'Review belum memiliki peserta.']);
        }
        TenantScope::table('talent_reviews')->where('id', $reviewId)->update(['status' => 'approved', 'approved_by' => $userId, 'approved_at' => now(), 'updated_at' => now()]);
        AuditLog::updated('talent_reviews', $reviewId, ['status' => $review->status], ['status' => 'approved'], 'Talent review disahkan');
    }

    /** Peserta review dengan unit, nama, dan kotak review sah sebelumnya (pergerakan antar-siklus). */
    public function entries(object $review): Collection
    {
        $previous = TenantScope::table('talent_reviews')->where('status', 'approved')->where('id', '!=', $review->id)
            ->where('review_date', '<', $review->review_date)->orderByDesc('review_date')->value('id');

        return TenantScope::table('talent_review_entries')->join('employees', 'employees.id', '=', 'talent_review_entries.employee_id')
            ->leftJoin('unit as u', 'u.id_unit', '=', 'talent_review_entries.unit_id')
            ->leftJoin('talent_review_entries as prev', fn ($j) => $j->on('prev.employee_id', '=', 'talent_review_entries.employee_id')->where('prev.review_id', (int) $previous))
            ->where('talent_review_entries.review_id', $review->id)->orderBy('employees.full_name')
            ->get(['talent_review_entries.*', 'employees.full_name', 'employees.employee_number', 'u.unit_name', 'prev.final_box as previous_box']);
    }

    private function editable(int $reviewId): object
    {
        $review = $this->review($reviewId) ?? abort(404);
        if ($review->status === 'approved') {
            throw ValidationException::withMessages(['status' => 'Review yang sudah disahkan tidak dapat diubah.']);
        }

        return $review;
    }
}
