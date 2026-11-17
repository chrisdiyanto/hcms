<?php

namespace App\Domain\Development;

use App\Domain\Development\Workflow\IdpSubject;
use App\Domain\Employee\Services\EmployeeScope;
use App\Domain\Talent\CareerAspirations;
use App\Domain\Workflow\WorkflowEngine;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rencana Pengembangan Individu (laporan CBHRM 6; kamus metrik §6).
 *
 * - Satu IDP aktif per pegawai per tahun. Draft baru diisi otomatis dari kebutuhan TNA yang sudah
 *   disetujui dan saran IDP kompetensi kritis (P6), serta aspirasi karier terkini sebagai sasaran karier.
 * - Pegawai menyusun aktivitas lalu mengajukan ke atasan langsung (workflow `idp`, ikut delegasi/eskalasi).
 * - Disetujui: kebutuhan TNA terkait → `planned`, saran IDP terkait → `accepted`.
 * - Pelaksanaan: status aktivitas, bukti, biaya realisasi, sertifikasi. Kebutuhan TNA ditutup (`closed`)
 *   bila semua aktivitasnya selesai; IDP selesai bila tidak ada aktivitas yang masih terbuka.
 */
class Idps
{
    public const STATUS = ['draft' => 'Draft', 'submitted' => 'Menunggu atasan', 'returned' => 'Dikembalikan', 'approved' => 'Berjalan',
        'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];

    public const ACTIVITY_STATUS = ['planned' => 'Direncanakan', 'in_progress' => 'Berjalan', 'completed' => 'Selesai', 'deferred' => 'Tertunda', 'cancelled' => 'Dibatalkan'];

    public const TYPES = ['training' => 'Pelatihan', 'elearning' => 'E-learning', 'coaching' => 'Coaching', 'mentoring' => 'Mentoring',
        'assignment' => 'Penugasan', 'certification' => 'Sertifikasi', 'other' => 'Lainnya'];

    public const OPEN_ACTIVITY = ['planned', 'in_progress', 'deferred'];

    public function __construct(private CareerAspirations $aspirations) {}

    public function find(int $id): ?object
    {
        return TenantScope::table('idps')->join('employees', 'employees.id', '=', 'idps.employee_id')
            ->where('idps.id', $id)->first(['idps.*', 'employees.full_name', 'employees.employee_number', 'employees.user_id']);
    }

    public function current(int $employeeId, int $year): ?object
    {
        $id = TenantScope::table('idps')->where('employee_id', $employeeId)->where('period_year', $year)
            ->where('status', '!=', 'cancelled')->orderByDesc('id')->value('id');

        return $id ? $this->find((int) $id) : null;
    }

    public function activities(int $idpId): Collection
    {
        return TenantScope::table('idp_activities')->leftJoin('development_interventions as i', 'i.id', '=', 'idp_activities.intervention_id')
            ->where('idp_activities.idp_id', $idpId)->orderBy('idp_activities.sort_order')->orderBy('idp_activities.id')
            ->get(['idp_activities.*', 'i.name as intervention_name', 'i.provider as intervention_provider']);
    }

    /** Draft baru dengan aktivitas awal dari TNA disetujui & saran IDP yang belum ditindaklanjuti. */
    public function createDraft(int $employeeId, int $year, int $userId): int
    {
        if ($this->current($employeeId, $year)) {
            throw ValidationException::withMessages(['period_year' => 'IDP tahun '.$year.' sudah ada.']);
        }
        $aspiration = $this->aspirations->current($employeeId);
        $linkedNeeds = TenantScope::table('idp_activities')->whereNotNull('training_need_id')->pluck('training_need_id');
        $linkedSuggestions = TenantScope::table('idp_activities')->whereNotNull('idp_suggestion_id')->pluck('idp_suggestion_id');
        $needs = TenantScope::table('training_needs')->where('employee_id', $employeeId)->where('period_year', $year)->where('status', 'approved')
            ->whereNotIn('id', $linkedNeeds)->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")->get();
        $suggestions = TenantScope::table('competency_idp_suggestions')->join('competencies', 'competencies.id', '=', 'competency_idp_suggestions.competency_id')
            ->where('competency_idp_suggestions.employee_id', $employeeId)->where('competency_idp_suggestions.status', 'draft')
            ->whereNotIn('competency_idp_suggestions.id', $linkedSuggestions)
            ->whereNotIn('competency_idp_suggestions.competency_code', $needs->pluck('competency_code')->filter())
            ->get(['competency_idp_suggestions.*', 'competencies.name']);

        return DB::transaction(function () use ($employeeId, $year, $userId, $aspiration, $needs, $suggestions) {
            $id = DB::table('idps')->insertGetId(TenantScope::fillForInsert('idps', [
                'employee_id' => $employeeId, 'period_year' => $year, 'status' => 'draft', 'created_by' => $userId,
                'career_goal' => $aspiration ? trim(($aspiration->job_title ?? $aspiration->target_role ?? $aspiration->family_name).' — '
                    .(CareerAspirations::HORIZONS[$aspiration->horizon] ?? '')) : null,
                'created_at' => now(), 'updated_at' => now(),
            ]));
            $order = 0;
            $due = $year.'-12-31';
            foreach ($needs as $n) {
                $this->insertActivity($id, ++$order, [
                    'training_need_id' => $n->id, 'competency_id' => $n->competency_id, 'competency_code' => $n->competency_code,
                    'current_level' => $n->current_level, 'target_level' => $n->target_level,
                    'goal' => $n->target_level ? 'Mencapai level '.$n->target_level.' pada '.$n->topic : $n->topic,
                    'activity_type' => 'training', 'title' => $n->suggested_intervention ?: $n->topic,
                    'due_date' => $n->due_date ?: $due, 'cost_estimate' => $n->estimated_cost,
                ]);
            }
            foreach ($suggestions as $s) {
                $this->insertActivity($id, ++$order, [
                    'idp_suggestion_id' => $s->id, 'competency_id' => $s->competency_id, 'competency_code' => $s->competency_code,
                    'current_level' => $s->current_level, 'target_level' => $s->target_level,
                    'goal' => 'Mencapai level '.$s->target_level.' pada '.$s->name, 'activity_type' => 'training', 'title' => $s->name, 'due_date' => $due,
                ]);
            }
            AuditLog::created('idps', $id, ['pegawai' => $employeeId, 'tahun' => $year, 'aktivitas_awal' => $order], 'Draft IDP dibuat');

            return $id;
        });
    }

    /**
     * Simpan isi draft (sasaran karier + aktivitas). Rujukan TNA/saran pada aktivitas lama dipertahankan
     * selama barisnya tidak dihapus.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function saveDraft(int $idpId, ?string $careerGoal, array $items): void
    {
        $idp = $this->editable($idpId);
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'IDP membutuhkan minimal satu aktivitas.']);
        }
        DB::transaction(function () use ($idp, $careerGoal, $items) {
            $existing = TenantScope::table('idp_activities')->where('idp_id', $idp->id)->get()->keyBy('id');
            $keep = [];
            foreach (array_values($items) as $i => $item) {
                $data = collect($item)->only(['competency_id', 'target_level', 'goal', 'intervention_id', 'activity_type', 'title', 'due_date', 'cost_estimate', 'employee_note'])->all();
                $data['competency_code'] = ! empty($data['competency_id']) ? TenantScope::table('competencies')->where('id', $data['competency_id'])->value('code') : null;
                $data['sort_order'] = $i + 1;
                $id = (int) ($item['id'] ?? 0);
                if ($id && $existing->has($id)) {
                    TenantScope::table('idp_activities')->where('id', $id)->update($data + ['updated_at' => now()]);
                    $keep[] = $id;
                } else {
                    $keep[] = $this->insertActivity($idp->id, $i + 1, $data);
                }
            }
            TenantScope::table('idp_activities')->where('idp_id', $idp->id)->whereNotIn('id', $keep)->delete();
            TenantScope::table('idps')->where('id', $idp->id)->update(['career_goal' => $careerGoal, 'updated_at' => now()]);
            AuditLog::updated('idps', $idp->id, ['aktivitas' => $existing->count()], ['aktivitas' => count($keep)], 'Draft IDP diubah');
        });
    }

    public function submit(int $idpId, int $userId): void
    {
        $idp = $this->editable($idpId);
        if (! TenantScope::table('idp_activities')->where('idp_id', $idp->id)->exists()) {
            throw ValidationException::withMessages(['items' => 'IDP membutuhkan minimal satu aktivitas.']);
        }
        DB::transaction(function () use ($idp, $userId) {
            TenantScope::table('idps')->where('id', $idp->id)->update(['status' => 'submitted', 'submitted_at' => now(), 'return_note' => null,
                'revision' => $idp->revision + 1, 'updated_at' => now()]);
            $instance = app(WorkflowEngine::class)->start(IdpSubject::CODE, $idp->id, $userId, (int) $idp->employee_id);
            TenantScope::table('idps')->where('id', $idp->id)->update(['workflow_instance_id' => $instance->id]);
            AuditLog::updated('idps', $idp->id, ['status' => $idp->status], ['status' => 'submitted'], 'IDP diajukan ke atasan');
        });
    }

    /** Dipanggil subjek workflow saat atasan menyetujui. */
    public function markApproved(int $idpId): void
    {
        TenantScope::table('idps')->where('id', $idpId)->update(['status' => 'approved', 'approved_at' => now(), 'updated_at' => now()]);
        $activities = TenantScope::table('idp_activities')->where('idp_id', $idpId);
        TenantScope::table('training_needs')->whereIn('id', (clone $activities)->whereNotNull('training_need_id')->select('training_need_id'))
            ->where('status', 'approved')->update(['status' => 'planned', 'updated_at' => now()]);
        TenantScope::table('competency_idp_suggestions')->whereIn('id', (clone $activities)->whereNotNull('idp_suggestion_id')->select('idp_suggestion_id'))
            ->where('status', 'draft')->update(['status' => 'accepted', 'updated_at' => now()]);
        AuditLog::updated('idps', $idpId, ['status' => 'submitted'], ['status' => 'approved'], 'IDP disetujui atasan');
    }

    /**
     * Pembaruan pelaksanaan satu aktivitas (IDP berjalan). Pegawai pemilik mengisi status/bukti;
     * atasan atau HR boleh menambah catatan atasan.
     */
    public function updateActivity(int $activityId, array $data, bool $asManager): void
    {
        $activity = TenantScope::table('idp_activities')->where('id', $activityId)->first() ?? abort(404);
        $idp = $this->find((int) $activity->idp_id);
        if ($idp->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'Pelaksanaan hanya dapat dicatat pada IDP yang sudah disetujui.']);
        }
        $allowed = $asManager ? ['manager_note'] : ['status', 'evidence', 'cost_actual', 'certification_name', 'certification_expiry', 'completed_at', 'employee_note'];
        $data = collect($data)->only($allowed)->all();
        if (($data['status'] ?? null) === 'completed') {
            if (trim((string) ($data['evidence'] ?? $activity->evidence)) === '') {
                throw ValidationException::withMessages(['evidence' => 'Aktivitas selesai membutuhkan bukti (mis. sertifikat, laporan, catatan mentor).']);
            }
            $data['completed_at'] = $data['completed_at'] ?? now()->toDateString();
        } elseif (isset($data['status'])) {
            $data['completed_at'] = null;
        }

        DB::transaction(function () use ($activity, $idp, $data) {
            TenantScope::table('idp_activities')->where('id', $activity->id)->update($data + ['updated_at' => now()]);
            AuditLog::updated('idp_activities', $activity->id, collect((array) $activity)->only(array_keys($data))->all(), $data, 'Pelaksanaan aktivitas IDP diperbarui');

            // Kebutuhan TNA selesai bila semua aktivitasnya selesai/dibatalkan
            if ($activity->training_need_id) {
                $open = TenantScope::table('idp_activities')->where('training_need_id', $activity->training_need_id)->whereIn('status', self::OPEN_ACTIVITY)->exists();
                $done = TenantScope::table('idp_activities')->where('training_need_id', $activity->training_need_id)->where('status', 'completed')->exists();
                TenantScope::table('training_needs')->where('id', $activity->training_need_id)->whereIn('status', ['planned', 'closed'])
                    ->update(['status' => ! $open && $done ? 'closed' : 'planned', 'updated_at' => now()]);
            }
            $remaining = TenantScope::table('idp_activities')->where('idp_id', $idp->id)->whereIn('status', self::OPEN_ACTIVITY)->exists();
            if (! $remaining) {
                TenantScope::table('idps')->where('id', $idp->id)->update(['status' => 'completed', 'completed_at' => now(), 'updated_at' => now()]);
                AuditLog::updated('idps', $idp->id, ['status' => 'approved'], ['status' => 'completed'], 'IDP selesai');
            }
        });
    }

    // ---------------------------------------------------------------- akses

    /** Pemilik, atasan langsung (posisi), atau pengguna HR dengan izin Rekap IDP dalam data scope. */
    public function canView(object $idp, int $userId, bool $hrAccess): bool
    {
        return (int) $idp->user_id === $userId || $this->isManager($idp, $userId)
            || ($hrAccess && EmployeeScope::query()->where('employees.id', $idp->employee_id)->exists());
    }

    public function isManager(object $idp, int $userId): bool
    {
        return app(TrainingNeeds::class)->directReports($userId)->contains('id', (int) $idp->employee_id);
    }

    // ---------------------------------------------------------------- bantuan

    private function editable(int $idpId): object
    {
        $idp = $this->find($idpId) ?? abort(404);
        if (! in_array($idp->status, ['draft', 'returned'], true)) {
            throw ValidationException::withMessages(['status' => 'IDP hanya dapat diubah saat draft atau dikembalikan.']);
        }

        return $idp;
    }

    private function insertActivity(int $idpId, int $order, array $data): int
    {
        return DB::table('idp_activities')->insertGetId(TenantScope::fillForInsert('idp_activities', $data + [
            'idp_id' => $idpId, 'status' => 'planned', 'sort_order' => $order, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }
}
