<?php

namespace App\Domain\Development;

use App\Domain\Employee\Services\EmployeeScope;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pelaksanaan pelatihan (laporan CBHRM 7): kelas dari katalog, peserta, kehadiran, kelulusan.
 *
 * - Kandidat peserta: aktivitas IDP yang disetujui dan masih terbuka untuk intervensi ini (atau
 *   kompetensi sasarannya), serta kebutuhan TNA disetujui/direncanakan untuk kompetensi yang sama.
 * - Peserta lulus → aktivitas IDP terkait selesai dengan bukti kelulusan (lewat Idps, sehingga
 *   kebutuhan TNA ikut tertutup); peserta tanpa IDP menutup kebutuhan TNA-nya langsung.
 * - Evaluasi tingkat 1 diisi peserta sendiri; tingkat 2 (pre/post-test) oleh admin pelatihan.
 */
class TrainingDelivery
{
    public const SESSION_STATUS = ['planned' => 'Direncanakan', 'ongoing' => 'Berlangsung', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];

    public const PARTICIPANT_STATUS = ['registered' => 'Terdaftar', 'attended' => 'Hadir', 'completed' => 'Lulus', 'no_show' => 'Tidak hadir', 'cancelled' => 'Batal'];

    public const MODES = ['offline' => 'Tatap muka', 'online' => 'Daring', 'blended' => 'Campuran'];

    public function __construct(private Idps $idps) {}

    public function session(int $id): ?object
    {
        return TenantScope::table('training_sessions')->join('development_interventions as i', 'i.id', '=', 'training_sessions.intervention_id')
            ->where('training_sessions.id', $id)
            ->first(['training_sessions.*', 'i.name as intervention_name', 'i.type as intervention_type', 'i.competency_id', 'i.target_level']);
    }

    public function participants(int $sessionId): Collection
    {
        return TenantScope::table('training_participants')->join('employees', 'employees.id', '=', 'training_participants.employee_id')
            ->where('training_participants.session_id', $sessionId)->orderBy('employees.full_name')
            ->get(['training_participants.*', 'employees.full_name', 'employees.employee_number']);
    }

    /** Kandidat dari IDP & TNA (dalam data scope), belum terdaftar di kelas ini. */
    public function candidates(object $session): Collection
    {
        $enrolled = TenantScope::table('training_participants')->where('session_id', $session->id)->pluck('employee_id');
        $competencyCode = $session->competency_id ? TenantScope::table('competencies')->where('id', $session->competency_id)->value('code') : null;
        $visible = EmployeeScope::query()->select('employees.id');

        $fromIdp = TenantScope::table('idp_activities')->join('idps', 'idps.id', '=', 'idp_activities.idp_id')
            ->join('employees', 'employees.id', '=', 'idps.employee_id')
            ->where('idps.status', 'approved')->whereIn('idp_activities.status', Idps::OPEN_ACTIVITY)
            ->where(fn ($q) => $q->where('idp_activities.intervention_id', $session->intervention_id)
                ->when($competencyCode, fn ($w) => $w->orWhere(fn ($x) => $x->whereNull('idp_activities.intervention_id')->where('idp_activities.competency_code', $competencyCode))))
            ->whereIn('idps.employee_id', $visible)->whereNotIn('idps.employee_id', $enrolled)
            ->whereNotExists(fn ($q) => $q->from('training_participants as tp')->whereColumn('tp.idp_activity_id', 'idp_activities.id')->where('tp.status', '!=', 'cancelled'))
            ->get(['employees.id as employee_id', 'employees.full_name', 'idp_activities.id as idp_activity_id', 'idp_activities.training_need_id', 'idp_activities.title as detail'])
            ->map(fn ($r) => (object) ((array) $r + ['source' => 'IDP']));
        $fromNeeds = $competencyCode ? TenantScope::table('training_needs')->join('employees', 'employees.id', '=', 'training_needs.employee_id')
            ->whereIn('training_needs.status', ['approved', 'planned'])->where('training_needs.competency_code', $competencyCode)
            ->whereIn('training_needs.employee_id', $visible)->whereNotIn('training_needs.employee_id', $enrolled)
            ->whereNotIn('training_needs.employee_id', $fromIdp->pluck('employee_id'))
            ->get(['employees.id as employee_id', 'employees.full_name', 'training_needs.id as training_need_id', 'training_needs.topic as detail'])
            ->map(fn ($r) => (object) ((array) $r + ['idp_activity_id' => null, 'source' => 'TNA'])) : collect();

        return $fromIdp->concat($fromNeeds)->unique('employee_id')->sortBy('full_name')->values();
    }

    /**
     * Daftarkan peserta. $entries: [employee_id, idp_activity_id?, training_need_id?].
     *
     * @param  array<int, array{employee_id: int, idp_activity_id?: ?int, training_need_id?: ?int}>  $entries
     */
    public function enroll(int $sessionId, array $entries, int $userId): int
    {
        $session = $this->session($sessionId) ?? abort(404);
        if (in_array($session->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'Kelas yang selesai atau dibatalkan tidak dapat menerima peserta.']);
        }
        $visible = EmployeeScope::query()->pluck('employees.id')->flip();
        $current = TenantScope::table('training_participants')->where('session_id', $sessionId)->where('status', '!=', 'cancelled')->count();
        $entries = collect($entries)->filter(fn ($e) => $visible->has((int) $e['employee_id']))->unique('employee_id')->values();
        if ($session->capacity && $current + $entries->count() > $session->capacity) {
            throw ValidationException::withMessages(['employees' => 'Melebihi kapasitas kelas ('.$session->capacity.' peserta; terisi '.$current.').']);
        }

        return DB::transaction(function () use ($session, $entries, $userId) {
            $added = 0;
            foreach ($entries as $e) {
                $exists = TenantScope::table('training_participants')->where('session_id', $session->id)->where('employee_id', $e['employee_id'])->first();
                if ($exists && $exists->status !== 'cancelled') {
                    continue;
                }
                $data = ['idp_activity_id' => $e['idp_activity_id'] ?? null, 'training_need_id' => $e['training_need_id'] ?? null,
                    'status' => 'registered', 'enrolled_by' => $userId, 'updated_at' => now()];
                $exists
                    ? TenantScope::table('training_participants')->where('id', $exists->id)->update($data)
                    : DB::table('training_participants')->insert(TenantScope::fillForInsert('training_participants', $data + [
                        'session_id' => $session->id, 'employee_id' => $e['employee_id'], 'created_at' => now()]));
                if (! empty($e['idp_activity_id'])) {
                    TenantScope::table('idp_activities')->where('id', $e['idp_activity_id'])->where('status', 'planned')
                        ->update(['status' => 'in_progress', 'updated_at' => now()]);
                }
                $added++;
            }
            AuditLog::updated('training_sessions', $session->id, null, ['peserta_ditambah' => $added], 'Peserta pelatihan didaftarkan');

            return $added;
        });
    }

    /** Admin: kehadiran, nilai pre/post, kelulusan. */
    public function updateParticipant(int $participantId, array $data): void
    {
        $p = TenantScope::table('training_participants')->where('id', $participantId)->first() ?? abort(404);
        $session = $this->session((int) $p->session_id);
        if ($session->status === 'cancelled') {
            throw ValidationException::withMessages(['status' => 'Kelas dibatalkan.']);
        }
        $data = collect($data)->only(['status', 'attendance_pct', 'pre_score', 'post_score', 'certificate_no', 'completed_at'])->all();
        if (($data['status'] ?? null) === 'completed') {
            $data['completed_at'] = $data['completed_at'] ?? min(now()->toDateString(), $session->end_date);
        } elseif (isset($data['status'])) {
            $data['completed_at'] = null;
        }

        DB::transaction(function () use ($p, $session, $data) {
            TenantScope::table('training_participants')->where('id', $p->id)->update($data + ['updated_at' => now()]);
            AuditLog::updated('training_participants', $p->id, collect((array) $p)->only(array_keys($data))->all(), $data, 'Data peserta pelatihan diperbarui');

            if (($data['status'] ?? null) === 'completed' && $p->status !== 'completed') {
                $evidence = 'Lulus pelatihan '.$session->code.' — '.$session->title.($data['certificate_no'] ?? $p->certificate_no ? ' (sertifikat '.($data['certificate_no'] ?? $p->certificate_no).')' : '');
                $activity = $p->idp_activity_id ? TenantScope::table('idp_activities')->join('idps', 'idps.id', '=', 'idp_activities.idp_id')
                    ->where('idp_activities.id', $p->idp_activity_id)->first(['idp_activities.id', 'idp_activities.status', 'idps.status as idp_status']) : null;
                if ($activity && $activity->idp_status === 'approved' && in_array($activity->status, Idps::OPEN_ACTIVITY, true)) {
                    $this->idps->updateActivity((int) $activity->id, ['status' => 'completed', 'evidence' => $evidence, 'completed_at' => $data['completed_at'],
                        'certification_name' => ($data['certificate_no'] ?? $p->certificate_no) ? $session->title : null], false);
                } elseif ($p->training_need_id) {
                    TenantScope::table('training_needs')->where('id', $p->training_need_id)->whereIn('status', ['approved', 'planned'])
                        ->update(['status' => 'closed', 'updated_at' => now()]);
                }
            }
        });
    }

    /** Evaluasi tingkat 1 oleh peserta sendiri (sekali, setelah hadir). */
    public function react(int $participantId, int $employeeId, float $score, ?string $comment): void
    {
        $p = TenantScope::table('training_participants')->where('id', $participantId)->where('employee_id', $employeeId)->first() ?? abort(404);
        if (! in_array($p->status, ['attended', 'completed'], true)) {
            throw ValidationException::withMessages(['reaction_score' => 'Survei reaksi dapat diisi setelah Anda tercatat hadir.']);
        }
        if ($p->reaction_at) {
            throw ValidationException::withMessages(['reaction_score' => 'Survei reaksi sudah diisi.']);
        }
        TenantScope::table('training_participants')->where('id', $p->id)
            ->update(['reaction_score' => $score, 'reaction_comment' => $comment, 'reaction_at' => now(), 'updated_at' => now()]);
    }

    public function complete(int $sessionId, ?float $cost): void
    {
        $session = $this->session($sessionId) ?? abort(404);
        if ($session->status === 'cancelled') {
            throw ValidationException::withMessages(['status' => 'Kelas dibatalkan.']);
        }
        if (TenantScope::table('training_participants')->where('session_id', $sessionId)->where('status', 'registered')->exists()) {
            throw ValidationException::withMessages(['status' => 'Masih ada peserta berstatus Terdaftar. Catat kehadiran semua peserta sebelum menutup kelas.']);
        }
        TenantScope::table('training_sessions')->where('id', $sessionId)->update(['status' => 'completed', 'completed_at' => now(),
            'cost_total' => $cost ?? $session->cost_total, 'updated_at' => now()]);
        AuditLog::updated('training_sessions', $sessionId, ['status' => $session->status], ['status' => 'completed', 'biaya' => $cost], 'Kelas pelatihan ditutup');
    }
}
