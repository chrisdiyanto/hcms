<?php

namespace App\Domain\Competency\Services;

use App\Domain\Employee\Services\EmployeeScope;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Collection;

/**
 * Position Readiness (laporan CBHRM 4; backlog Fase 2): kandidat internal terhadap posisi sasaran.
 *
 * JFI kandidat dihitung terhadap profil posisi sasaran dari skor kompetensi yang masih berlaku
 * (K1–K2), gap kritis per K3, dan horizon kesiapan per K4 (docs/METRIK-CBHRM.md §4):
 *   siap sekarang  = band siap sekarang tanpa gap kritis
 *   siap 1–2 tahun = band siap 1–2 tahun, atau band siap sekarang dengan tepat 1 gap kritis
 *   siap ≥ 3 tahun = selain itu; JFI "data belum cukup" tidak diberi horizon.
 * Hasil adalah bahan pertimbangan, bukan rekomendasi atau keputusan penempatan.
 *
 * Skor semua kandidat dibaca dalam satu query (bukan fitFor per pegawai) agar tetap cepat
 * pada 10.000 pegawai; kandidat dibatasi data scope pengguna.
 */
class PositionReadiness
{
    public const NOW = 'now';

    public const SOON = '1_2';

    public const LATER = '3_plus';

    public const HORIZONS = [self::NOW => 'Siap sekarang', self::SOON => 'Siap 1–2 tahun', self::LATER => 'Siap ≥ 3 tahun'];

    public function __construct(private CompetencyProfile $profiles, private CompetencyAssessment $assessments) {}

    /** K4: horizon kesiapan dari band JFI dan jumlah gap kritis. */
    public static function horizon(string $band, int $criticalGaps): ?string
    {
        return match (true) {
            $band === JobFitCalculator::INSUFFICIENT => null,
            $band === JobFitCalculator::READY_NOW && $criticalGaps === 0 => self::NOW,
            $band === JobFitCalculator::READY_1_2, $band === JobFitCalculator::READY_NOW && $criticalGaps === 1 => self::SOON,
            default => self::LATER,
        };
    }

    /**
     * Kandidat dengan ≥ 1 skor berlaku pada kompetensi profil posisi sasaran.
     *
     * @return array{profile: Collection, candidates: Collection}
     */
    public function forPosition(int $positionId, ?int $unitId = null): array
    {
        $profile = $this->profiles->forPosition($positionId);
        if ($profile->isEmpty()) {
            return ['profile' => $profile, 'candidates' => collect()];
        }
        $today = now()->toDateString();
        $codes = $profile->pluck('code')->all();

        // Pegawai aktif dalam cakupan data + posisi primer saat ini
        $people = EmployeeScope::apply(TenantScope::table('employees')
            ->leftJoin('employee_assignments as a', function ($j) use ($today) {
                $j->on('a.employee_id', '=', 'employees.id')->where('a.type', 'primary')->where('a.effective_from', '<=', $today)
                    ->where(fn ($w) => $w->whereNull('a.effective_to')->orWhere('a.effective_to', '>=', $today));
            })
            ->leftJoin('positions as p', 'p.id', '=', 'a.position_id')
            ->leftJoin('unit as u', 'u.id_unit', '=', 'p.unit_id')
            ->whereNull('employees.deleted_at')->where('employees.status', 'active')
            ->when($unitId, fn ($q) => $q->where('p.unit_id', $unitId))
            ->whereExists(fn ($q) => $q->from('employee_competencies as ec')->whereColumn('ec.employee_id', 'employees.id')
                ->whereIn('ec.competency_code', $codes)->where('ec.valid_until', '>=', $today)), 'employees.id')
            ->get(['employees.id', 'employees.full_name', 'employees.employee_number', 'p.id as position_id', 'p.title as position_title', 'u.unit_name'])
            ->unique('id')->keyBy('id');
        if ($people->isEmpty()) {
            return ['profile' => $profile, 'candidates' => collect()];
        }

        $scores = TenantScope::table('employee_competencies')->whereIn('employee_id', $people->keys())->whereIn('competency_code', $codes)
            ->get(['employee_id', 'competency_code', 'score', 'assessed_at', 'valid_until'])->groupBy('employee_id');

        $calculator = new JobFitCalculator($this->assessments->jfiBands());
        $candidates = $people->map(function ($person) use ($profile, $scores, $calculator, $today, $positionId) {
            $mine = $scores->get($person->id, collect())->keyBy('competency_code');
            $valid = $mine->filter(fn ($s) => $s->valid_until >= $today);
            $fit = $calculator->calculate($profile->map(fn ($c) => [
                'competency_id' => (int) $c->competency_id, 'code' => $c->code, 'name' => $c->name,
                'weight' => (int) $c->criticality, 'required' => (int) $c->required_level,
                'actual' => isset($valid[$c->code]) ? (float) $valid[$c->code]->score : null,
            ])->all());
            $critical = collect($fit['items'])->where('critical', true);

            return (object) [
                'employee_id' => (int) $person->id, 'full_name' => $person->full_name, 'employee_number' => $person->employee_number,
                'position_title' => $person->position_title, 'unit_name' => $person->unit_name,
                'incumbent' => (int) $person->position_id === $positionId,
                'jfi' => $fit['jfi'], 'band' => $fit['band'], 'coverage' => $fit['coverage'], 'complete' => $fit['complete'],
                'met' => collect($fit['items'])->filter(fn ($i) => $i['gap'] !== null && $i['gap'] >= 0)->count(),
                'required_count' => count($fit['items']),
                'critical_gaps' => $critical->pluck('code')->values()->all(),
                'horizon' => self::horizon($fit['band'], $critical->count()),
                'last_assessed' => $valid->max('assessed_at'),
                'expired' => $mine->filter(fn ($s) => $s->valid_until < $today)->keys()->values()->all(),
            ];
        });

        $order = [self::NOW => 0, self::SOON => 1, self::LATER => 2];
        $sorted = $candidates->sort(fn ($a, $b) => [($order[$a->horizon] ?? 3), -($a->jfi ?? -1), $a->full_name]
            <=> [($order[$b->horizon] ?? 3), -($b->jfi ?? -1), $b->full_name])->values();

        return ['profile' => $profile, 'candidates' => $sorted];
    }
}
