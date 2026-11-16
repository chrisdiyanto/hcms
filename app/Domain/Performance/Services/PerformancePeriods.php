<?php

namespace App\Domain\Performance\Services;

use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Periode kinerja (blueprint 8.9 langkah 2): HR membuat periode, lalu membukanya —
 * saat dibuka, kebijakan aktif disalin dan dikunci ke periode (`policy_snapshot`).
 * Tahap: draft → planning → monitoring → assessment → calibration → closed.
 */
final class PerformancePeriods
{
    public const STATUS = [
        'draft' => 'Draft', 'planning' => 'Perencanaan', 'monitoring' => 'Monitoring',
        'assessment' => 'Penilaian', 'calibration' => 'Kalibrasi', 'closed' => 'Ditutup',
    ];

    public function __construct(private PerformancePolicy $policies) {}

    public function find(int $id): object
    {
        $row = TenantScope::table('performance_periods')->where('id', $id)->first() ?? abort(404);
        $row->policy = $row->policy_snapshot ? PerformancePolicy::withDefaults(json_decode($row->policy_snapshot, true)) : null;

        return $row;
    }

    /** Periode yang sedang berjalan (bukan draft/ditutup), terbaru dulu. */
    public function running()
    {
        return TenantScope::table('performance_periods')->whereNotIn('status', ['draft', 'closed'])->orderByDesc('start_date')->get()
            ->map(fn ($p) => $this->find((int) $p->id));
    }

    public function create(array $data, ?int $userId): int
    {
        $this->assertNoOverlap($data['start_date'], $data['end_date']);
        $id = DB::table('performance_periods')->insertGetId(TenantScope::fillForInsert('performance_periods', [
            'uuid' => (string) Str::uuid7(), 'code' => Str::upper($data['code']), 'name' => $data['name'],
            'start_date' => $data['start_date'], 'end_date' => $data['end_date'],
            'planning_deadline' => $data['planning_deadline'] ?? null, 'status' => 'draft',
            'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]));
        AuditLog::created('performance_periods', $id, $data, 'Periode kinerja dibuat: '.$data['name']);

        return $id;
    }

    /** Buka periode: kunci salinan kebijakan aktif, mulai tahap perencanaan. */
    public function open(int $id, ?int $userId): void
    {
        $period = $this->find($id);
        if ($period->status !== 'draft') {
            throw ValidationException::withMessages(['period' => 'Periode sudah dibuka.']);
        }
        $policy = $this->policies->active()
            ?? throw ValidationException::withMessages(['period' => 'Belum ada kebijakan kinerja yang berlaku. Terbitkan kebijakan lebih dulu.']);

        DB::table('performance_periods')->where('id', $id)->update([
            'status' => 'planning', 'policy_id' => $policy->id,
            'policy_snapshot' => json_encode($policy->config + ['_version' => $policy->version, '_name' => $policy->name]),
            'opened_at' => now(), 'opened_by' => $userId, 'updated_at' => now(),
        ]);
        AuditLog::updated('performance_periods', $id, ['status' => 'draft'], ['status' => 'planning', 'kebijakan' => 'v'.$policy->version],
            'Periode kinerja dibuka, kebijakan v'.$policy->version.' dikunci');
    }

    /**
     * Maju satu tahap: perencanaan → monitoring → penilaian → kalibrasi.
     * Ke penilaian: semua rencana yang disetujui sudah menjalani mid-year review (lalu form penilaian dibuka).
     * Ke kalibrasi: semua penilaian selesai. Kalibrasi → ditutup lewat pengesahan rating (PerformanceRatings).
     */
    public function advance(int $id): string
    {
        $period = $this->find($id);
        $next = ['planning' => 'monitoring', 'monitoring' => 'assessment', 'assessment' => 'calibration'][$period->status]
            ?? throw ValidationException::withMessages(['period' => 'Tahap berikutnya dilakukan lewat pengesahan rating.']);

        if ($next === 'calibration') {
            $open = TenantScope::table('performance_assessments')->join('performance_plans', 'performance_plans.id', '=', 'performance_assessments.plan_id')
                ->join('employees', 'employees.id', '=', 'performance_plans.employee_id')
                ->where('performance_plans.period_id', $period->id)->where('performance_assessments.status', '!=', 'completed')
                ->pluck('employees.full_name');
            if ($open->isNotEmpty()) {
                throw ValidationException::withMessages(['period' => 'Penilaian belum selesai untuk '.$open->count().' rencana: '
                    .$open->take(5)->implode(', ').($open->count() > 5 ? ', …' : '').'.']);
            }
        }

        if ($next === 'assessment') {
            $missing = app(PerformanceMonitoring::class)->missingMidyear($period->id);
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages(['period' => 'Mid-year review belum selesai untuk '.$missing->count().' rencana: '
                    .$missing->take(5)->pluck('full_name')->implode(', ').($missing->count() > 5 ? ', …' : '').'.']);
            }
        }

        DB::transaction(function () use ($id, $period, $next) {
            DB::table('performance_periods')->where('id', $id)->update(['status' => $next, 'updated_at' => now()]);
            AuditLog::updated('performance_periods', $id, ['status' => $period->status], ['status' => $next], 'Tahap periode kinerja: '.self::STATUS[$next]);
            if ($next === 'assessment') {
                app(PerformanceEvaluation::class)->openForPeriod($period);
            }
        });

        return $next;
    }

    private function assertNoOverlap(string $start, string $end): void
    {
        if (TenantScope::table('performance_periods')->where('start_date', '<=', $end)->where('end_date', '>=', $start)->exists()) {
            throw ValidationException::withMessages(['start_date' => 'Rentang tanggal beririsan dengan periode lain.']);
        }
    }
}
