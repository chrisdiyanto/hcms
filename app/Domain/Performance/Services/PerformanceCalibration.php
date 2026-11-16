<?php

namespace App\Domain\Performance\Services;

use App\Domain\Employee\Services\EmployeeScope;
use App\Domain\Organization\Services\OrgUnitClosure;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kalibrasi (blueprint 8.9 langkah 9): sesi per periode (opsional per unit beserta turunannya).
 * Memindahkan pegawai ke band lain wajib beralasan; skor disesuaikan seminimal mungkin agar
 * konsisten dengan band tujuan (naik → batas bawah band, turun → batas atas band), nilai
 * sebelum/sesudah tercatat di calibration_adjustments dan audit trail.
 */
final class PerformanceCalibration
{
    public function __construct(private PerformancePeriods $periods) {}

    public function create(int $periodId, string $name, ?int $unitId, ?string $notes, int $userId): int
    {
        $period = $this->periods->find($periodId);
        if ($period->status !== 'calibration') {
            throw ValidationException::withMessages(['session' => 'Sesi kalibrasi hanya dapat dibuat pada tahap kalibrasi.']);
        }
        $id = DB::table('calibration_sessions')->insertGetId(TenantScope::fillForInsert('calibration_sessions', [
            'period_id' => $period->id, 'name' => $name, 'unit_id' => $unitId, 'notes' => $notes,
            'status' => 'open', 'facilitator_user_id' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]));
        AuditLog::created('calibration_sessions', $id, ['periode' => $period->code, 'nama' => $name, 'unit' => $unitId], 'Sesi kalibrasi dibuat');

        return $id;
    }

    public function session(int $id): object
    {
        return TenantScope::table('calibration_sessions')->where('id', $id)->first() ?? abort(404);
    }

    /** Penilaian selesai dalam cakupan sesi (unit + turunan) dan data scope pengguna. */
    public function members(object $session): Collection
    {
        $units = $session->unit_id ? OrgUnitClosure::descendantsOf([(int) $session->unit_id]) : null;

        return TenantScope::table('performance_assessments')
            ->join('performance_plans', 'performance_plans.id', '=', 'performance_assessments.plan_id')
            ->join('employees', 'employees.id', '=', 'performance_plans.employee_id')
            ->join('positions', 'positions.id', '=', 'performance_plans.position_id')
            ->leftJoin('unit', 'unit.id_unit', '=', 'positions.unit_id')
            ->where('performance_plans.period_id', $session->period_id)->where('performance_assessments.status', 'completed')
            ->whereIn('performance_plans.employee_id', EmployeeScope::query()->select('employees.id'))
            ->when($units !== null, fn ($q) => $q->whereIn('positions.unit_id', $units))
            ->orderBy('employees.full_name')
            ->get(['performance_assessments.*', 'employees.full_name', 'employees.employee_number', 'positions.title as position_title', 'unit.unit_name'])
            ->map(function ($a) {
                $eff = PerformanceEvaluation::effective($a);
                $a->score = $eff['score'];
                $a->current_band = $eff['band'];

                return $a;
            });
    }

    /** @return array<string, array{label: string, count: int, pct: float}> */
    public function distribution(object $session, ?Collection $members = null): array
    {
        $members ??= $this->members($session);
        $policy = $this->periods->find((int) $session->period_id)->policy;
        $total = max(1, $members->count());
        $out = [];
        foreach ($policy['bands'] as $band) {
            $count = $members->where('current_band', $band['code'])->count();
            $out[$band['code']] = ['label' => $band['label'], 'count' => $count, 'pct' => round($count / $total * 100, 1)];
        }

        return $out;
    }

    public function move(int $sessionId, int $assessmentId, string $toBand, string $reason, int $userId): array
    {
        $session = $this->session($sessionId);
        if ($session->status !== 'open') {
            throw ValidationException::withMessages(['session' => 'Sesi kalibrasi sudah ditutup.']);
        }
        $period = $this->periods->find((int) $session->period_id);
        if ($period->status !== 'calibration') {
            throw ValidationException::withMessages(['session' => 'Periode tidak lagi dalam tahap kalibrasi.']);
        }
        $member = $this->members($session)->firstWhere('id', $assessmentId)
            ?? throw ValidationException::withMessages(['assessment_id' => 'Pegawai tidak termasuk dalam sesi kalibrasi ini.']);
        [$min, $max] = $this->range($period->policy['bands'], $toBand)
            ?? throw ValidationException::withMessages(['to_band' => 'Band tujuan tidak dikenal.']);
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Alasan kalibrasi wajib diisi (minimal 10 karakter).']);
        }
        if ($member->current_band === $toBand) {
            return $this->distribution($session);
        }

        $score = (float) $member->score;
        $toScore = $score < $min ? $min : ($score > $max ? $max : $score);

        DB::transaction(function () use ($session, $member, $toBand, $toScore, $reason, $userId) {
            DB::table('calibration_adjustments')->insert(TenantScope::fillForInsert('calibration_adjustments', [
                'session_id' => $session->id, 'assessment_id' => $member->id,
                'from_band' => $member->current_band, 'to_band' => $toBand, 'from_score' => $member->score, 'to_score' => $toScore,
                'reason' => trim($reason), 'adjusted_by' => $userId, 'created_at' => now(),
            ]));
            DB::table('performance_assessments')->where('id', $member->id)->update(['calibrated_score' => $toScore, 'calibrated_band' => $toBand, 'updated_at' => now()]);
            AuditLog::updated('performance_assessments', $member->id, ['band' => $member->current_band, 'skor' => $member->score],
                ['band' => $toBand, 'skor' => $toScore, 'alasan' => trim($reason)], 'Kalibrasi '.$member->full_name);
        });

        return $this->distribution($session);
    }

    public function close(int $sessionId): void
    {
        $session = $this->session($sessionId);
        DB::table('calibration_sessions')->where('id', $session->id)->update(['status' => 'closed', 'closed_at' => now(), 'updated_at' => now()]);
        AuditLog::updated('calibration_sessions', $session->id, ['status' => 'open'], ['status' => 'closed'], 'Sesi kalibrasi ditutup');
    }

    /** Rentang skor [min, max] sebuah band (max = batas bawah band di atasnya − 0,01, atau 100). */
    public function range(array $bands, string $code): ?array
    {
        usort($bands, fn ($a, $b) => $b['min'] <=> $a['min']);
        foreach ($bands as $i => $band) {
            if ($band['code'] === $code) {
                return [(float) $band['min'], $i === 0 ? 100.0 : round((float) $bands[$i - 1]['min'] - 0.01, 2)];
            }
        }

        return null;
    }
}
