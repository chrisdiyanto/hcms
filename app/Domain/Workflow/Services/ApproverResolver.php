<?php

namespace App\Domain\Workflow\Services;

use App\Support\Security\Roles;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;

/**
 * Menentukan approver dari posisi, bukan nama orang (blueprint 3.2):
 * - pemangku posisi = penugasan primer atau Plt. yang aktif hari ini dengan akun login aktif;
 * - posisi kosong (atau pemangkunya pengaju sendiri) → naik ke atasan solid line berikutnya;
 * - rantai habis → fallback ke HR Administrator, lalu Tenant Administrator;
 * - delegasi aktif mengalihkan tugas ke penerima delegasi (satu tingkat, tanpa rantai).
 * Pengaju tidak pernah menjadi approver pengajuannya sendiri (SoD, blueprint 3.4).
 */
final class ApproverResolver
{
    private const MAX_CLIMB = 15;

    /**
     * @return array<int, array{user_id: int, position_id: ?int, delegated_from: ?int}>
     */
    public function resolve(array $approver, int $requesterUserId): array
    {
        $candidates = match ($approver['type'] ?? null) {
            'supervisor' => $this->fromPosition($this->supervisorOf($this->positionOf($requesterUserId), max(1, (int) ($approver['levels'] ?? 1))), $requesterUserId),
            'position' => $this->fromPosition(isset($approver['position_id']) ? (int) $approver['position_id'] : null, $requesterUserId),
            'role' => $this->fromRole((string) ($approver['role'] ?? ''), $requesterUserId),
            default => [],
        };

        if ($candidates === []) {
            $candidates = $this->fromRole(Roles::HR_ADMIN, $requesterUserId) ?: $this->fromRole(Roles::TENANT_ADMIN, $requesterUserId);
        }

        return array_values(array_map(fn ($c) => $this->applyDelegation($c, $requesterUserId), $candidates));
    }

    /** Pemangku posisi di atas $positionId (untuk eskalasi). */
    public function escalationFrom(?int $positionId, int $requesterUserId): array
    {
        $candidates = $positionId ? $this->fromPosition($this->supervisorOf($positionId, 1), $requesterUserId) : [];

        return array_values(array_map(fn ($c) => $this->applyDelegation($c, $requesterUserId), $candidates));
    }

    /** Posisi primer aktif pengguna (lewat data pegawai yang ditautkan). */
    public function positionOf(int $userId): ?int
    {
        $today = now()->toDateString();

        $id = TenantScope::table('employee_assignments')
            ->join('employees', 'employees.id', '=', 'employee_assignments.employee_id')
            ->where('employees.user_id', $userId)->where('employees.status', 'active')
            ->where('employee_assignments.type', 'primary')
            ->where('employee_assignments.effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('employee_assignments.effective_to')->orWhere('employee_assignments.effective_to', '>=', $today))
            ->value('employee_assignments.position_id');

        return $id ? (int) $id : null;
    }

    private function supervisorOf(?int $positionId, int $levels): ?int
    {
        $current = $positionId;
        for ($i = 0; $i < $levels && $current; $i++) {
            $current = $this->parentOf($current);
        }

        return $current;
    }

    private function parentOf(int $positionId): ?int
    {
        $id = TenantScope::table('position_relationships')->where('position_id', $positionId)
            ->where('type', 'solid')->whereNull('effective_to')->value('reports_to_id');

        return $id ? (int) $id : null;
    }

    /** Pemangku posisi; kosong → naik solid line. */
    private function fromPosition(?int $positionId, int $requesterUserId): array
    {
        $seen = [];
        $current = $positionId;

        while ($current && ! isset($seen[$current]) && count($seen) < self::MAX_CLIMB) {
            $seen[$current] = true;
            $holders = array_values(array_filter($this->holdersOf($current), fn ($u) => $u !== $requesterUserId));
            if ($holders !== []) {
                return [['user_id' => $holders[0], 'position_id' => $current, 'delegated_from' => null]];
            }
            $current = $this->parentOf($current);
        }

        return [];
    }

    /** @return int[] user id pemangku aktif (primer dulu, lalu Plt.) */
    private function holdersOf(int $positionId): array
    {
        $today = now()->toDateString();

        return TenantScope::table('employee_assignments')
            ->join('employees', 'employees.id', '=', 'employee_assignments.employee_id')
            ->join('tb_users', 'tb_users.id', '=', 'employees.user_id')
            ->where('employee_assignments.position_id', $positionId)
            ->whereIn('employee_assignments.type', ['primary', 'acting'])
            ->where('employee_assignments.effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('employee_assignments.effective_to')->orWhere('employee_assignments.effective_to', '>=', $today))
            ->where('employees.status', 'active')->where('tb_users.active', 1)
            ->orderByRaw("FIELD(employee_assignments.type, 'primary', 'acting')")
            ->pluck('tb_users.id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function fromRole(string $roleCode, int $requesterUserId): array
    {
        $groupId = Roles::id($roleCode);
        if (! $groupId) {
            return [];
        }

        return TenantScope::table('tb_users')
            ->join('tb_users_groups', 'tb_users_groups.user_id', '=', 'tb_users.id')
            ->where('tb_users_groups.group_id', $groupId)->where('tb_users.active', 1)
            ->where('tb_users.id', '!=', $requesterUserId)
            ->orderBy('tb_users.id')->pluck('tb_users.id')
            ->map(fn ($id) => ['user_id' => (int) $id, 'position_id' => $this->positionOf((int) $id), 'delegated_from' => null])
            ->all();
    }

    private function applyDelegation(array $candidate, int $requesterUserId): array
    {
        $today = now()->toDateString();
        $delegate = TenantScope::table('approval_delegations')->where('delegator_user_id', $candidate['user_id'])
            ->whereNull('revoked_at')->where('starts_on', '<=', $today)->where('ends_on', '>=', $today)
            ->orderByDesc('id')->value('delegate_user_id');

        if ($delegate && (int) $delegate !== $requesterUserId && DB::table('tb_users')->where('id', $delegate)->where('active', 1)->exists()) {
            return ['user_id' => (int) $delegate, 'position_id' => $candidate['position_id'], 'delegated_from' => $candidate['user_id']];
        }

        return $candidate;
    }
}
