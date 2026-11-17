<?php

namespace App\Domain\Talent;

use App\Domain\Competency\Services\PositionReadiness;
use App\Domain\Employee\Services\EmployeeScope;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Talent pool (laporan CBHRM 10; kamus metrik §10).
 *
 * - Kandidat: pegawai di kelompok talenta kunci (kotak 6/8/9) pada talent review sah terakhirnya,
 *   dalam data scope, belum menjadi anggota/nominasi pool. Nominasi manual wajib beralasan.
 * - Persetujuan oleh pemegang Approve selain penominasi (SoD); disetujui wajib menetapkan kesiapan
 *   (siap sekarang / 1–2 tahun / ≥ 3 tahun, K4) dan menjadwalkan tinjau ulang sesuai siklus pool.
 * - Keanggotaan bukan janji promosi. Setiap perubahan status tercatat di talent_pool_events.
 */
class TalentPools
{
    public const STATUS = ['nominated' => 'Dinominasikan', 'active' => 'Aktif', 'rejected' => 'Ditolak', 'exited' => 'Keluar'];

    public const KEY_BOXES = [6, 8, 9];

    public function __construct(private PositionReadiness $readiness) {}

    public function pool(int $id): ?object
    {
        return TenantScope::table('talent_pools')->leftJoin('job_families as f', 'f.id', '=', 'talent_pools.target_job_family_id')
            ->leftJoin('positions as p', 'p.id', '=', 'talent_pools.target_position_id')->where('talent_pools.id', $id)
            ->first(['talent_pools.*', 'f.name as family_name', 'p.title as position_title', 'p.job_id as position_job_id']);
    }

    /** Kandidat dari talent review sah terakhir tiap pegawai (talenta kunci). */
    public function candidates(object $pool): Collection
    {
        $taken = TenantScope::table('talent_pool_members')->where('pool_id', $pool->id)->whereIn('status', ['nominated', 'active'])->pluck('employee_id');
        $latest = TenantScope::table('talent_review_entries as e')->join('talent_reviews as r', 'r.id', '=', 'e.review_id')->where('r.status', 'approved')
            ->whereNotNull('e.final_box')->orderByDesc('r.review_date')->orderByDesc('e.id')
            ->get(['e.id', 'e.employee_id', 'e.final_box', 'r.name as review_name'])->unique('employee_id');
        $visible = EmployeeScope::query()->where('employees.status', 'active')->pluck('employees.full_name', 'employees.id');

        return $latest->filter(fn ($e) => in_array((int) $e->final_box, self::KEY_BOXES, true) && $visible->has($e->employee_id) && ! $taken->contains($e->employee_id))
            ->map(fn ($e) => (object) ['employee_id' => (int) $e->employee_id, 'full_name' => $visible[$e->employee_id], 'entry_id' => (int) $e->id,
                'box' => (int) $e->final_box, 'review_name' => $e->review_name])
            ->sortBy('full_name')->values();
    }

    /** @param  array<int, array{employee_id: int, entry_id?: ?int}>  $entries */
    public function nominate(int $poolId, array $entries, string $reason, int $userId): int
    {
        $pool = $this->pool($poolId) ?? abort(404);
        abort_unless($pool->is_active, 422, 'Pool tidak aktif.');
        $visible = EmployeeScope::query()->where('employees.status', 'active')->pluck('employees.id')->flip();

        return DB::transaction(function () use ($pool, $entries, $reason, $userId, $visible) {
            $count = 0;
            foreach (collect($entries)->unique('employee_id') as $e) {
                if (! $visible->has((int) $e['employee_id']) || TenantScope::table('talent_pool_members')->where('pool_id', $pool->id)
                    ->where('employee_id', $e['employee_id'])->whereIn('status', ['nominated', 'active'])->exists()) {
                    continue;
                }
                $id = DB::table('talent_pool_members')->insertGetId(TenantScope::fillForInsert('talent_pool_members', [
                    'pool_id' => $pool->id, 'employee_id' => $e['employee_id'], 'status' => 'nominated',
                    'source' => ! empty($e['entry_id']) ? 'talent_review' : 'manual', 'talent_review_entry_id' => $e['entry_id'] ?? null,
                    'nomination_reason' => $reason, 'nominated_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
                ]));
                $this->event($id, null, 'nominated', null, $reason, $userId);
                $count++;
            }
            AuditLog::updated('talent_pools', $pool->id, null, ['nominasi' => $count], 'Nominasi talent pool');

            return $count;
        });
    }

    /** Setujui/tolak nominasi (SoD: bukan penominasi). Disetujui wajib kesiapan. */
    public function decide(array $memberIds, string $decision, ?string $readiness, ?string $note, int $userId): int
    {
        if ($decision === 'active' && ! array_key_exists((string) $readiness, PositionReadiness::HORIZONS)) {
            throw ValidationException::withMessages(['readiness' => 'Tetapkan tingkat kesiapan untuk anggota yang disetujui.']);
        }
        if ($decision === 'rejected' && trim((string) $note) === '') {
            throw ValidationException::withMessages(['decision_note' => 'Alasan penolakan wajib diisi.']);
        }
        $rows = TenantScope::table('talent_pool_members')->join('talent_pools as p', 'p.id', '=', 'talent_pool_members.pool_id')
            ->whereIn('talent_pool_members.id', $memberIds)->where('talent_pool_members.status', 'nominated')
            ->get(['talent_pool_members.id', 'talent_pool_members.nominated_by', 'p.review_cycle_months']);
        if ($rows->contains(fn ($r) => (int) $r->nominated_by === $userId)) {
            throw ValidationException::withMessages(['ids' => 'Anda tidak dapat memutus nominasi yang Anda ajukan sendiri.']);
        }

        return DB::transaction(function () use ($rows, $decision, $readiness, $note, $userId) {
            foreach ($rows as $r) {
                $data = ['status' => $decision, 'decided_by' => $userId, 'decided_at' => now(), 'decision_note' => $note, 'updated_at' => now()];
                if ($decision === 'active') {
                    $data += ['readiness' => $readiness, 'joined_at' => now()->toDateString(),
                        'next_review_date' => now()->addMonthsNoOverflow((int) $r->review_cycle_months)->toDateString()];
                }
                TenantScope::table('talent_pool_members')->where('id', $r->id)->update($data);
                $this->event((int) $r->id, 'nominated', $decision, $decision === 'active' ? $readiness : null, $note, $userId);
            }

            return $rows->count();
        });
    }

    /** Tinjau ulang anggota aktif: perbarui kesiapan & jadwal tinjau berikutnya. */
    public function reviewMember(int $memberId, string $readiness, ?string $note, int $userId): void
    {
        $m = $this->activeMember($memberId);
        TenantScope::table('talent_pool_members')->where('id', $m->id)->update(['readiness' => $readiness,
            'next_review_date' => now()->addMonthsNoOverflow((int) $m->review_cycle_months)->toDateString(), 'updated_at' => now()]);
        $this->event((int) $m->id, 'active', 'active', $readiness, $note ?: 'Tinjau ulang', $userId);
    }

    public function exit(int $memberId, string $reason, int $userId): void
    {
        $m = $this->activeMember($memberId);
        TenantScope::table('talent_pool_members')->where('id', $m->id)->update(['status' => 'exited', 'exited_at' => now()->toDateString(),
            'exit_reason' => $reason, 'updated_at' => now()]);
        $this->event((int) $m->id, 'active', 'exited', null, $reason, $userId);
    }

    /**
     * Anggota & nominasi dengan data pendukung: kesiapan terhitung (K4, bila pool punya posisi sasaran),
     * gap kritis, keselarasan aspirasi, IDP tahun berjalan, flight risk dari talent review terakhir.
     */
    public function members(object $pool, bool $sensitive): Collection
    {
        $rows = TenantScope::table('talent_pool_members')->join('employees', 'employees.id', '=', 'talent_pool_members.employee_id')
            ->where('talent_pool_members.pool_id', $pool->id)->orderByRaw("FIELD(talent_pool_members.status, 'nominated', 'active', 'exited', 'rejected')")
            ->orderBy('employees.full_name')
            ->get(['talent_pool_members.*', 'employees.full_name', 'employees.employee_number', 'employees.gender']);
        if ($rows->isEmpty()) {
            return $rows;
        }
        $ids = $rows->pluck('employee_id');
        $computed = $pool->target_position_id ? $this->readiness->forPosition((int) $pool->target_position_id)['candidates']->keyBy('employee_id') : collect();
        $aspirations = TenantScope::table('employee_career_aspirations')->leftJoin('jobs_catalog as j', 'j.id', '=', 'employee_career_aspirations.target_job_id')
            ->whereIn('employee_career_aspirations.employee_id', $ids)->where('employee_career_aspirations.is_current', true)
            ->get(['employee_career_aspirations.employee_id', 'employee_career_aspirations.target_job_family_id', 'employee_career_aspirations.target_job_id', 'j.job_family_id'])
            ->keyBy('employee_id');
        $targetFamily = $pool->target_job_family_id ?: ($pool->position_job_id ? TenantScope::table('jobs_catalog')->where('id', $pool->position_job_id)->value('job_family_id') : null);
        $idps = TenantScope::table('idps')->whereIn('employee_id', $ids)->where('period_year', now()->year)->where('status', '!=', 'cancelled')
            ->selectRaw("employee_id, status, (SELECT COUNT(*) FROM idp_activities a WHERE a.idp_id = idps.id AND a.status != 'cancelled') total,
                (SELECT COUNT(*) FROM idp_activities a WHERE a.idp_id = idps.id AND a.status = 'completed') done")->get()->keyBy('employee_id');
        $risks = $sensitive ? TenantScope::table('talent_review_entries as e')->join('talent_reviews as r', 'r.id', '=', 'e.review_id')
            ->where('r.status', 'approved')->whereIn('e.employee_id', $ids)->orderByDesc('r.review_date')->get(['e.employee_id', 'e.flight_risk', 'e.final_box'])
            ->unique('employee_id')->keyBy('employee_id') : collect();

        return $rows->map(function ($m) use ($computed, $aspirations, $targetFamily, $idps, $risks) {
            $c = $computed->get($m->employee_id);
            $a = $aspirations->get($m->employee_id);
            $m->computed_horizon = $c->horizon ?? null;
            $m->jfi = $c->jfi ?? null;
            $m->critical_gaps = $c->critical_gaps ?? [];
            $m->aspiration = $a ? ($targetFamily && ((int) $a->target_job_family_id === (int) $targetFamily || (int) $a->job_family_id === (int) $targetFamily) ? 'aligned' : 'different') : 'none';
            $idp = $idps->get($m->employee_id);
            $m->idp_status = $idp->status ?? null;
            $m->idp_progress = $idp && $idp->total ? round($idp->done / $idp->total * 100) : null;
            $m->flight_risk = $risks->get($m->employee_id)->flight_risk ?? null;
            $m->latest_box = $risks->get($m->employee_id)->final_box ?? null;
            $m->review_overdue = $m->status === 'active' && $m->next_review_date && $m->next_review_date < now()->toDateString();

            return $m;
        });
    }

    public function events(int $poolId): Collection
    {
        return TenantScope::table('talent_pool_events as ev')->join('talent_pool_members as m', 'm.id', '=', 'ev.member_id')
            ->join('employees', 'employees.id', '=', 'm.employee_id')->leftJoin('tb_users as u', 'u.id', '=', 'ev.user_id')
            ->where('m.pool_id', $poolId)->orderByDesc('ev.created_at')->orderByDesc('ev.id')->limit(50)
            ->get(['ev.*', 'employees.full_name', 'u.first_name', 'u.last_name']);
    }

    private function activeMember(int $memberId): object
    {
        $m = TenantScope::table('talent_pool_members')->join('talent_pools as p', 'p.id', '=', 'talent_pool_members.pool_id')
            ->where('talent_pool_members.id', $memberId)->first(['talent_pool_members.*', 'p.review_cycle_months']) ?? abort(404);
        if ($m->status !== 'active') {
            throw ValidationException::withMessages(['status' => 'Hanya anggota aktif yang dapat ditinjau ulang atau dikeluarkan.']);
        }

        return $m;
    }

    private function event(int $memberId, ?string $from, string $to, ?string $readiness, ?string $note, int $userId): void
    {
        DB::table('talent_pool_events')->insert(TenantScope::fillForInsert('talent_pool_events', [
            'member_id' => $memberId, 'from_status' => $from, 'to_status' => $to, 'readiness' => $readiness, 'note' => $note,
            'user_id' => $userId, 'created_at' => now(),
        ]));
        AuditLog::updated('talent_pool_members', $memberId, ['status' => $from], ['status' => $to, 'kesiapan' => $readiness, 'catatan' => $note], 'Status anggota talent pool');
    }
}
