<?php

namespace App\Domain\Talent;

use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aspirasi karier pegawai (prasyarat P3 CBHRM; masukan talent pool & suksesi).
 * Diisi pegawai lewat Layanan Mandiri. Setiap pembaruan menambah baris baru dan menandai
 * baris lama bukan terkini, sehingga perubahan minat dapat ditelusuri. Aspirasi adalah
 * masukan pembicaraan karier, bukan janji penempatan.
 */
class CareerAspirations
{
    public const HORIZONS = ['1_2' => '1–2 tahun', '3_5' => '3–5 tahun', '5_plus' => 'Lebih dari 5 tahun'];

    public const MOBILITY = ['yes' => 'Bersedia pindah lokasi', 'discuss' => 'Bisa dibicarakan', 'no' => 'Tidak bersedia pindah lokasi'];

    public function current(int $employeeId): ?object
    {
        return $this->query()->where('employee_career_aspirations.employee_id', $employeeId)->where('employee_career_aspirations.is_current', true)->first();
    }

    /** Riwayat, terbaru lebih dulu. */
    public function history(int $employeeId): Collection
    {
        return $this->query()->where('employee_career_aspirations.employee_id', $employeeId)
            ->orderByDesc('employee_career_aspirations.submitted_at')->orderByDesc('employee_career_aspirations.id')->get();
    }

    /** @param  array{target_job_family_id: ?int, target_job_id: ?int, target_role: ?string, horizon: string, mobility: string, interests: ?string, development_needs: ?string}  $data */
    public function submit(int $employeeId, array $data, int $userId): int
    {
        return DB::transaction(function () use ($employeeId, $data, $userId) {
            $previous = TenantScope::table('employee_career_aspirations')->where('employee_id', $employeeId)->where('is_current', true)->lockForUpdate()->first();
            if ($previous) {
                TenantScope::table('employee_career_aspirations')->where('id', $previous->id)->update(['is_current' => false, 'updated_at' => now()]);
            }
            $id = DB::table('employee_career_aspirations')->insertGetId(TenantScope::fillForInsert('employee_career_aspirations', $data + [
                'employee_id' => $employeeId, 'is_current' => true, 'submitted_by' => $userId, 'submitted_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]));
            $previous
                ? AuditLog::updated('employee_career_aspirations', $id, (array) $previous, $data, 'Aspirasi karier diperbarui')
                : AuditLog::created('employee_career_aspirations', $id, $data, 'Aspirasi karier diisi');

            return $id;
        });
    }

    private function query()
    {
        return TenantScope::table('employee_career_aspirations')
            ->leftJoin('job_families', 'job_families.id', '=', 'employee_career_aspirations.target_job_family_id')
            ->leftJoin('jobs_catalog', 'jobs_catalog.id', '=', 'employee_career_aspirations.target_job_id')
            ->select(['employee_career_aspirations.*', 'job_families.name as family_name', 'jobs_catalog.title as job_title']);
    }
}
