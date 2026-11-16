<?php

namespace App\Domain\Performance\Services;

use App\Domain\Workflow\Services\Notifier;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Monitoring kinerja (blueprint 8.9 langkah 5–6): pegawai mencatat realisasi & bukti per KPI,
 * capaian dihitung dengan kebijakan yang dikunci di periode; check-in berkala dan
 * mid-year review (wajib, diselesaikan atasan) sebelum periode masuk tahap penilaian.
 */
final class PerformanceMonitoring
{
    public const EVIDENCE_MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt'];

    public const EVIDENCE_MAX_KB = 5120;

    public const EVIDENCE_MAX_FILES = 5;

    public const CHECKIN_TYPES = ['regular' => 'Check-in berkala', 'midyear' => 'Mid-year review'];

    public function __construct(private PerformancePlans $plans, private PerformancePeriods $periods, private Notifier $notifier) {}

    /** KPI aktif (belum dipensiunkan lewat change request). */
    public function activeObjectives(int $planId): Collection
    {
        return $this->plans->objectives($planId)->whereNull('retired_at')->values();
    }

    public function calculator(object $period): ScoreCalculator
    {
        return new ScoreCalculator($period->policy);
    }

    /**
     * Catat realisasi satu KPI (+ bukti). Hanya pemilik rencana, saat periode monitoring dan rencana disetujui.
     *
     * @param  array{as_of:string, realization?:mixed, milestones?:array, score?:mixed, note?:?string}  $data
     * @param  UploadedFile[]  $files
     */
    public function recordUpdate(int $objectiveId, array $data, array $files, int $userId): int
    {
        $objective = TenantScope::table('performance_objectives')->where('id', $objectiveId)->whereNull('retired_at')->first() ?? abort(404);
        $plan = $this->plans->plan((int) $objective->plan_id);
        $period = $this->periods->find((int) $plan->period_id);
        if ($period->status !== 'monitoring' || $plan->status !== 'approved') {
            throw ValidationException::withMessages(['update' => 'Realisasi hanya dapat dicatat pada tahap monitoring untuk rencana yang sudah disetujui.']);
        }
        if ($data['as_of'] < $plan->start_date || $data['as_of'] > $plan->end_date) {
            throw ValidationException::withMessages(['as_of' => 'Tanggal realisasi harus dalam cakupan rencana ('.$plan->start_date.' s.d. '.$plan->end_date.').']);
        }
        if (count($files) > self::EVIDENCE_MAX_FILES) {
            throw ValidationException::withMessages(['evidences' => 'Maksimal '.self::EVIDENCE_MAX_FILES.' berkas per catatan.']);
        }

        $kpi = (array) $objective;
        $kpi['milestones'] = json_decode((string) $objective->milestones, true) ?: [];
        [$actual, $stored] = $this->actualFor($kpi, $data);
        $achievement = round($this->calculator($period)->achievement($kpi, $actual), 2);

        return DB::transaction(function () use ($objective, $plan, $data, $stored, $achievement, $files, $userId) {
            $id = DB::table('performance_updates')->insertGetId(TenantScope::fillForInsert('performance_updates', $stored + [
                'plan_id' => $plan->id, 'objective_id' => $objective->id, 'as_of' => $data['as_of'],
                'achievement' => $achievement, 'note' => trim((string) ($data['note'] ?? '')) ?: null,
                'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]));
            foreach ($files as $file) {
                $this->storeEvidence($plan, $id, $file, $userId);
            }
            AuditLog::created('performance_updates', $id, ['kpi' => $objective->title, 'tanggal' => $data['as_of'], 'capaian' => $achievement, 'bukti' => count($files)],
                'Realisasi KPI dicatat');

            return $id;
        });
    }

    /** @return array{0: mixed, 1: array} nilai untuk kalkulator, kolom yang disimpan */
    private function actualFor(array $kpi, array $data): array
    {
        switch ($kpi['kpi_type']) {
            case 'milestone':
                $state = [];
                $actual = [];
                foreach ($kpi['milestones'] as $i => $m) {
                    $done = ! empty($data['milestones'][$i]['done']);
                    $doneDate = $done ? (($data['milestones'][$i]['done_date'] ?? null) ?: $data['as_of']) : null;
                    $late = $done && ! empty($m['due_date']) && $doneDate > $m['due_date'];
                    $state[] = ['done' => $done, 'done_date' => $doneDate];
                    $actual[] = ['weight' => (float) $m['weight'], 'done' => $done, 'late' => $late];
                }

                return [$actual, ['milestones' => json_encode($state)]];
            case 'qualitative':
                $score = $data['score'] ?? null;
                if (! is_numeric($score) || $score < 1 || $score > 5) {
                    throw ValidationException::withMessages(['score' => 'Skor rubrik wajib 1–5.']);
                }

                return [(int) $score, ['score' => (int) $score]];
            default:
                $value = $data['realization'] ?? null;
                if (! is_numeric($value)) {
                    throw ValidationException::withMessages(['realization' => 'Realisasi wajib berupa angka.']);
                }

                return [(float) $value, ['realization' => $value]];
        }
    }

    private function storeEvidence(object $plan, int $updateId, UploadedFile $file, int $userId): void
    {
        $ext = strtolower((string) $file->guessExtension() ?: $file->getClientOriginalExtension());
        if (! in_array($ext, self::EVIDENCE_MIMES, true) || $file->getSize() > self::EVIDENCE_MAX_KB * 1024) {
            throw ValidationException::withMessages(['evidences' => 'Bukti harus PDF, gambar, atau dokumen Office maks. '.(self::EVIDENCE_MAX_KB / 1024).' MB.']);
        }
        // Privat per tenant; nama acak (nama asli hanya disimpan sebagai metadata)
        $path = $file->storeAs('tenants/'.TenantScope::tenantId().'/performance/'.$plan->id, Str::uuid7().'.'.$ext, 'local');

        DB::table('performance_evidences')->insert(TenantScope::fillForInsert('performance_evidences', [
            'plan_id' => $plan->id, 'update_id' => $updateId,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''), 'path' => $path,
            'mime' => (string) $file->getMimeType(), 'size' => (int) $file->getSize(),
            'uploaded_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    public function evidence(int $id): object
    {
        $row = TenantScope::table('performance_evidences')->where('id', $id)->first() ?? abort(404);
        abort_unless(Storage::disk('local')->exists($row->path), 404);

        return $row;
    }

    /**
     * Progres rencana: realisasi terakhir & capaian per KPI, skor What proyeksi.
     *
     * @return array{items: Collection, what: ?float, updated: int, total: int}
     */
    public function progress(object $plan, object $period): array
    {
        $updates = TenantScope::table('performance_updates')->where('plan_id', $plan->id)
            ->orderByDesc('as_of')->orderByDesc('id')->get()->groupBy('objective_id');
        $evidences = TenantScope::table('performance_evidences')->where('plan_id', $plan->id)->get()->groupBy('update_id');

        $items = $this->activeObjectives($plan->id)->map(function ($o) use ($updates, $evidences) {
            $o->updates = $updates->get($o->id, collect())->map(function ($u) use ($evidences) {
                $u->milestones = json_decode((string) $u->milestones, true) ?: [];
                $u->evidences = $evidences->get($u->id, collect());

                return $u;
            });
            $o->latest = $o->updates->first();
            $o->achievement = $o->latest ? (float) $o->latest->achievement : null;

            return $o;
        });

        $scored = $items->filter(fn ($o) => $o->achievement !== null);
        $what = $scored->isEmpty() ? null : round($this->calculator($period)->whatScore(
            $items->map(fn ($o) => ['weight' => (float) $o->weight, 'achievement' => $o->achievement ?? 0])->all()), 2);

        return ['items' => $items, 'what' => $what, 'updated' => $scored->count(), 'total' => $items->count()];
    }

    // ------------------------------------------------------------ check-in

    public function checkins(int $planId): Collection
    {
        return TenantScope::table('performance_checkins')->where('plan_id', $planId)->orderByDesc('checkin_date')->orderByDesc('id')->get();
    }

    /** Pegawai atau atasan membuka check-in; mid-year hanya satu per rencana. */
    public function openCheckin(object $plan, string $type, string $date, ?string $employeeNotes, int $userId, ?int $notifyUserId): int
    {
        $period = $this->periods->find((int) $plan->period_id);
        if ($period->status !== 'monitoring' || $plan->status !== 'approved') {
            throw ValidationException::withMessages(['checkin' => 'Check-in hanya pada tahap monitoring untuk rencana yang disetujui.']);
        }
        if ($type === 'midyear' && TenantScope::table('performance_checkins')->where('plan_id', $plan->id)->where('type', 'midyear')->exists()) {
            throw ValidationException::withMessages(['checkin' => 'Mid-year review untuk rencana ini sudah dibuat.']);
        }

        $id = DB::table('performance_checkins')->insertGetId(TenantScope::fillForInsert('performance_checkins', [
            'plan_id' => $plan->id, 'type' => $type, 'checkin_date' => $date,
            'employee_notes' => trim((string) $employeeNotes) ?: null, 'status' => 'open',
            'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]));
        AuditLog::created('performance_checkins', $id, ['jenis' => self::CHECKIN_TYPES[$type], 'tanggal' => $date], self::CHECKIN_TYPES[$type].' dibuka');
        if ($notifyUserId && $notifyUserId !== $userId) {
            $this->notifier->send($notifyUserId, self::CHECKIN_TYPES[$type], self::CHECKIN_TYPES[$type].' rencana kinerja '.$plan->full_name.' menunggu tanggapan.',
                'performance/plans/'.$plan->id.'/monitoring', 'fa fa-comments');
        }

        return $id;
    }

    /** Atasan menyelesaikan check-in dengan catatan (mid-year review wajib diselesaikan atasan). */
    public function completeCheckin(int $checkinId, string $managerNotes, int $userId): object
    {
        $row = TenantScope::table('performance_checkins')->where('id', $checkinId)->first() ?? abort(404);
        if ($row->status === 'completed') {
            throw ValidationException::withMessages(['checkin' => 'Check-in sudah diselesaikan.']);
        }
        DB::table('performance_checkins')->where('id', $row->id)->update([
            'manager_notes' => $managerNotes, 'status' => 'completed', 'completed_by' => $userId, 'completed_at' => now(), 'updated_at' => now(),
        ]);
        AuditLog::updated('performance_checkins', $row->id, ['status' => 'open'], ['status' => 'completed'], self::CHECKIN_TYPES[$row->type].' diselesaikan atasan');

        return $row;
    }

    /** Rencana disetujui di periode yang mid-year review-nya belum selesai (gerbang ke tahap penilaian). */
    public function missingMidyear(int $periodId): Collection
    {
        return TenantScope::table('performance_plans')->join('employees', 'employees.id', '=', 'performance_plans.employee_id')
            ->where('performance_plans.period_id', $periodId)->where('performance_plans.status', 'approved')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('performance_checkins')->whereColumn('performance_checkins.plan_id', 'performance_plans.id')
                    ->where('performance_checkins.type', 'midyear')->where('performance_checkins.status', 'completed');
            })
            ->get(['performance_plans.id', 'employees.full_name']);
    }
}
