<?php

namespace App\Domain\Talent;

use App\Domain\Competency\Services\PositionReadiness;
use App\Domain\Employee\Services\EmployeeScope;
use App\Http\Controllers\Organization\PositionsController;
use App\Support\Audit\AuditLog;
use App\Support\Security\DataScope;
use App\Support\Tenancy\TenantScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Suksesi & bench strength (laporan CBHRM 11; kamus metrik §11, K8).
 *
 * - Posisi kritis = positions.is_critical (P3) yang aktif, dalam cakupan unit pengguna.
 * - Risiko kekosongan ≤ 12 bulan: posisi tanpa pemangku, pemangku mencapai usia pensiun
 *   (pengaturan tenant `talent.retirement_age`, bawaan 59), atau kontrak PKWT berakhir.
 * - Kandidat diusulkan dari Kesiapan Posisi (horizon siap sekarang / 1–2 tahun), talent pool yang
 *   menyasar posisi/rumpunnya, atau manual; alasan wajib. Perubahan apa pun mengembalikan rencana ke
 *   draft; pengesahan oleh pemegang Approve selain pengubah terakhir (SoD).
 * - Metrik dihitung dari rencana yang DISAHKAN: cakupan = posisi kritis dengan ≥ 1 suksesor siap
 *   sekarang ÷ posisi kritis; bench strength = rata-rata suksesor siap sekarang/1–2 tahun per posisi
 *   kritis; berisiko = dampak sangat tinggi tanpa suksesor siap sekarang atau risiko kekosongan ≤ 12 bulan.
 */
class Succession
{
    public const ROLES = ['primary' => 'Utama', 'backup' => 'Cadangan'];

    public const SOURCES = ['readiness' => 'Kesiapan Posisi', 'pool' => 'Talent pool', 'manual' => 'Manual'];

    public const RISKS = ['vacant' => 'Kosong', 'retirement' => 'Pensiun ≤ 12 bulan', 'contract' => 'Kontrak berakhir ≤ 12 bulan'];

    public const DEFAULT_RETIREMENT_AGE = 59;

    public function __construct(private PositionReadiness $readiness) {}

    public function retirementAge(): int
    {
        $raw = DB::table('tenant_settings')->where('tenant_id', TenantScope::tenantId())->where('key', 'talent.retirement_age')->value('value');
        $age = $raw === null ? null : json_decode($raw, true);

        return is_numeric($age) && $age >= 50 && $age <= 70 ? (int) $age : self::DEFAULT_RETIREMENT_AGE;
    }

    /** Posisi kritis dalam cakupan, dengan pemangku, risiko kekosongan, dan ringkasan rencana. */
    public function criticalPositions(?DataScope $scope = null): Collection
    {
        $scope ??= DataScope::current();
        $today = now()->toDateString();
        $horizon = now()->addMonths(12)->toDateString();
        $age = $this->retirementAge();

        $positions = TenantScope::table('positions')->leftJoin('unit as u', 'u.id_unit', '=', 'positions.unit_id')
            ->whereNull('positions.deleted_at')->where('positions.status', 'active')->where('positions.is_critical', true)
            ->when(! $scope->isAll(), fn ($q) => $q->whereIn('positions.unit_id', $scope->unitIds ?: [0]))
            ->orderByDesc('positions.critical_impact')->orderBy('positions.code')
            ->get(['positions.id', 'positions.code', 'positions.title', 'positions.critical_impact', 'positions.critical_reason', 'u.unit_name']);
        if ($positions->isEmpty()) {
            return $positions;
        }
        $incumbents = TenantScope::table('employee_assignments as a')->join('employees as e', 'e.id', '=', 'a.employee_id')
            ->whereIn('a.position_id', $positions->pluck('id'))->whereIn('a.type', ['primary', 'acting'])->where('a.effective_from', '<=', $today)
            ->where(fn ($w) => $w->whereNull('a.effective_to')->orWhere('a.effective_to', '>=', $today))->where('e.status', 'active')->whereNull('e.deleted_at')
            ->get(['a.position_id', 'a.type', 'e.id', 'e.full_name', 'e.birth_date', 'e.employment_status', 'e.contract_end_date'])->groupBy('position_id');
        $plans = TenantScope::table('succession_plans')->whereIn('position_id', $positions->pluck('id'))->get()->keyBy('position_id');
        $candidates = TenantScope::table('succession_candidates')->whereIn('plan_id', $plans->pluck('id'))->where('status', 'active')
            ->get(['plan_id', 'employee_id', 'role', 'readiness'])->groupBy('plan_id');

        return $positions->map(function ($p) use ($incumbents, $plans, $candidates, $horizon, $age) {
            $holders = $incumbents->get($p->id, collect())->where('type', 'primary');
            $p->incumbents = $holders->pluck('full_name')->all();
            $p->risks = [];
            if ($holders->isEmpty()) {
                $p->risks[] = 'vacant';
            }
            foreach ($holders as $h) {
                if ($h->birth_date && Carbon::parse($h->birth_date)->addYears($age)->toDateString() <= $horizon) {
                    $p->risks[] = 'retirement';
                }
                if ($h->employment_status === 'pkwt' && $h->contract_end_date && $h->contract_end_date <= $horizon) {
                    $p->risks[] = 'contract';
                }
            }
            $p->risks = array_values(array_unique($p->risks));
            $plan = $plans->get($p->id);
            $p->plan_id = $plan->id ?? null;
            $p->plan_status = $plan->status ?? null;
            $list = $plan ? $candidates->get($plan->id, collect()) : collect();
            $approved = $plan && $plan->status === 'approved';
            $p->candidate_count = $list->count();
            $p->ready_now = $approved ? $list->where('readiness', 'now')->count() : 0;
            $p->ready_soon = $approved ? $list->where('readiness', '1_2')->count() : 0;
            $p->at_risk = ((int) $p->critical_impact === 3 && $p->ready_now === 0) || $p->risks !== [];

            return $p;
        });
    }

    /** Ringkasan K8 dari daftar posisi kritis. */
    public static function metrics(Collection $positions, Collection $primaryCounts): array
    {
        $total = $positions->count();

        return [
            'total' => $total,
            'covered' => $positions->where('ready_now', '>', 0)->count(),
            'coverage' => $total ? round($positions->where('ready_now', '>', 0)->count() / $total * 100, 1) : null,
            'bench' => $total ? round($positions->sum(fn ($p) => $p->ready_now + $p->ready_soon) / $total, 2) : null,
            'at_risk' => $positions->where('at_risk', true)->count(),
            'no_plan' => $positions->whereNull('plan_id')->count(),
            'draft' => $positions->where('plan_status', 'draft')->count(),
            'duplicates' => $primaryCounts->filter(fn ($n) => $n > 1)->count(),
        ];
    }

    /** Pegawai yang menjadi suksesor utama di rencana disahkan: employee_id => jumlah posisi. */
    public function primaryCounts(): Collection
    {
        return TenantScope::table('succession_candidates as c')->join('succession_plans as p', 'p.id', '=', 'c.plan_id')
            ->where('p.status', 'approved')->where('c.status', 'active')->where('c.role', 'primary')
            ->selectRaw('c.employee_id, COUNT(*) n')->groupBy('c.employee_id')->pluck('n', 'employee_id');
    }

    public function plan(int $positionId, int $userId): object
    {
        $position = TenantScope::table('positions')->where('id', $positionId)->where('is_critical', true)->whereNull('deleted_at')->first() ?? abort(404);
        $plan = TenantScope::table('succession_plans')->where('position_id', $position->id)->first();
        if (! $plan) {
            $id = DB::table('succession_plans')->insertGetId(TenantScope::fillForInsert('succession_plans', [
                'position_id' => $position->id, 'status' => 'draft', 'created_by' => $userId, 'last_changed_by' => $userId,
                'created_at' => now(), 'updated_at' => now(),
            ]));
            AuditLog::created('succession_plans', $id, ['posisi' => $position->code], 'Rencana suksesi dibuat');
            $plan = TenantScope::table('succession_plans')->where('id', $id)->first();
        }

        return $plan;
    }

    public function candidates(int $planId): Collection
    {
        return TenantScope::table('succession_candidates as c')->join('employees as e', 'e.id', '=', 'c.employee_id')
            ->leftJoin('employee_assignments as a', function ($j) {
                $today = now()->toDateString();
                $j->on('a.employee_id', '=', 'e.id')->where('a.type', 'primary')->where('a.effective_from', '<=', $today)
                    ->where(fn ($w) => $w->whereNull('a.effective_to')->orWhere('a.effective_to', '>=', $today));
            })->leftJoin('positions as p', 'p.id', '=', 'a.position_id')
            ->where('c.plan_id', $planId)->orderByRaw("FIELD(c.status, 'active', 'removed')")->orderByRaw("FIELD(c.role, 'primary', 'backup')")
            ->orderByRaw("FIELD(c.readiness, 'now', '1_2', '3_plus')")
            ->get(['c.*', 'e.full_name', 'e.employee_number', 'p.title as current_position']);
    }

    /** Usulan kandidat: Kesiapan Posisi (siap sekarang/1–2 tahun) & talent pool yang menyasar posisi/rumpunnya. */
    public function suggestions(object $plan): Collection
    {
        $position = TenantScope::table('positions')->join('jobs_catalog as j', 'j.id', '=', 'positions.job_id')
            ->where('positions.id', $plan->position_id)->first(['positions.id', 'j.job_family_id']);
        $taken = TenantScope::table('succession_candidates')->where('plan_id', $plan->id)->where('status', 'active')->pluck('employee_id');
        $fromReadiness = $this->readiness->forPosition((int) $position->id)['candidates']
            ->filter(fn ($c) => ! $c->incumbent && in_array($c->horizon, ['now', '1_2'], true) && ! $taken->contains($c->employee_id))
            ->map(fn ($c) => (object) ['employee_id' => $c->employee_id, 'full_name' => $c->full_name, 'readiness' => $c->horizon, 'source' => 'readiness',
                'pool_member_id' => null, 'detail' => 'JFI '.number_format((float) $c->jfi, 1, ',', '.').'%']);
        $visible = EmployeeScope::query()->pluck('employees.id')->flip();
        $fromPools = TenantScope::table('talent_pool_members as m')->join('talent_pools as tp', 'tp.id', '=', 'm.pool_id')
            ->join('employees as e', 'e.id', '=', 'm.employee_id')->where('m.status', 'active')->where('tp.is_active', true)
            ->whereIn('m.readiness', ['now', '1_2'])
            ->where(fn ($q) => $q->where('tp.target_position_id', $position->id)->orWhere('tp.target_job_family_id', $position->job_family_id))
            ->whereNotIn('m.employee_id', $taken)->get(['m.id', 'm.employee_id', 'm.readiness', 'e.full_name', 'tp.name as pool_name'])
            ->filter(fn ($m) => $visible->has($m->employee_id))
            ->map(fn ($m) => (object) ['employee_id' => (int) $m->employee_id, 'full_name' => $m->full_name, 'readiness' => $m->readiness, 'source' => 'pool',
                'pool_member_id' => (int) $m->id, 'detail' => 'pool: '.$m->pool_name]);

        return $fromPools->concat($fromReadiness)->unique('employee_id')->sortBy('full_name')->values();
    }

    public function addCandidate(object $plan, array $data, int $userId): void
    {
        $exists = TenantScope::table('succession_candidates')->where('plan_id', $plan->id)->where('employee_id', $data['employee_id'])->where('status', 'active')->exists();
        if ($exists) {
            throw ValidationException::withMessages(['employee_id' => 'Pegawai sudah menjadi kandidat pada rencana ini.']);
        }
        $incumbent = TenantScope::table('employee_assignments')->where('employee_id', $data['employee_id'])->where('position_id', $plan->position_id)
            ->where('type', 'primary')->whereNull('effective_to')->exists();
        if ($incumbent) {
            throw ValidationException::withMessages(['employee_id' => 'Pemangku posisi tidak dapat menjadi suksesornya sendiri.']);
        }
        DB::transaction(function () use ($plan, $data, $userId) {
            $id = DB::table('succession_candidates')->insertGetId(TenantScope::fillForInsert('succession_candidates', collect($data)
                ->only(['employee_id', 'role', 'readiness', 'source', 'talent_pool_member_id', 'rationale', 'development_action'])->all() + [
                    'plan_id' => $plan->id, 'status' => 'active', 'added_by' => $userId, 'created_at' => now(), 'updated_at' => now()]));
            AuditLog::created('succession_candidates', $id, collect($data)->only(['employee_id', 'role', 'readiness', 'source', 'rationale'])->all(), 'Kandidat suksesor ditambahkan');
            $this->touch($plan->id, $userId);
        });
    }

    public function updateCandidate(int $candidateId, array $data, int $userId): void
    {
        $c = TenantScope::table('succession_candidates')->where('id', $candidateId)->where('status', 'active')->first() ?? abort(404);
        $changes = collect($data)->only(['role', 'readiness', 'development_action'])->all();
        DB::transaction(function () use ($c, $changes, $data, $userId) {
            TenantScope::table('succession_candidates')->where('id', $c->id)->update($changes + ['updated_at' => now()]);
            AuditLog::updated('succession_candidates', $c->id, collect((array) $c)->only(array_keys($changes))->all(), $changes + ['alasan' => $data['reason']], 'Kandidat suksesor diubah');
            $this->touch((int) $c->plan_id, $userId);
        });
    }

    public function removeCandidate(int $candidateId, string $reason, int $userId): void
    {
        $c = TenantScope::table('succession_candidates')->where('id', $candidateId)->where('status', 'active')->first() ?? abort(404);
        DB::transaction(function () use ($c, $reason, $userId) {
            TenantScope::table('succession_candidates')->where('id', $c->id)->update(['status' => 'removed', 'removal_reason' => $reason, 'updated_at' => now()]);
            AuditLog::updated('succession_candidates', $c->id, ['status' => 'active'], ['status' => 'removed', 'alasan' => $reason], 'Kandidat suksesor dikeluarkan');
            $this->touch((int) $c->plan_id, $userId);
        });
    }

    /** Pengesahan: bukan pengubah terakhir (SoD); tinjau ulang dijadwalkan 12 bulan. */
    public function approve(int $planId, int $userId): void
    {
        $plan = TenantScope::table('succession_plans')->where('id', $planId)->first() ?? abort(404);
        if ($plan->status === 'approved') {
            throw ValidationException::withMessages(['status' => 'Rencana sudah disahkan.']);
        }
        if ((int) $plan->last_changed_by === $userId) {
            throw ValidationException::withMessages(['status' => 'Pengubah terakhir rencana tidak dapat mengesahkannya sendiri.']);
        }
        TenantScope::table('succession_plans')->where('id', $planId)->update(['status' => 'approved', 'approved_by' => $userId, 'approved_at' => now(),
            'next_review_date' => now()->addYear()->toDateString(), 'updated_at' => now()]);
        AuditLog::updated('succession_plans', $planId, ['status' => 'draft'], ['status' => 'approved'], 'Rencana suksesi disahkan');
    }

    private function touch(int $planId, int $userId): void
    {
        TenantScope::table('succession_plans')->where('id', $planId)->update(['status' => 'draft', 'last_changed_by' => $userId,
            'approved_by' => null, 'approved_at' => null, 'updated_at' => now()]);
    }

    public static function impactLabel(?int $impact): string
    {
        return PositionsController::IMPACTS[$impact] ?? 'belum diisi';
    }
}
