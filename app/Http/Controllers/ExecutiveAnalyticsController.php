<?php

namespace App\Http\Controllers;

use App\Domain\Employee\Services\EmployeeScope;
use App\Domain\Employee\Services\WorkforceTrend;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Support\Security\DataScope;
use App\Support\Tenancy\TenantScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Aggregate HR dashboards built from existing employee, assignment and competency records. */
class ExecutiveAnalyticsController extends Controller
{
    use AuthorizesHcmsModule;

    private const REPORTS = [
        'turnover' => ['Turnover & Retensi', 'Pergerakan pegawai yang keluar dan tingkat retensi.'],
        'promotions' => ['Mobilitas & Kenaikan Grade', 'Perubahan penempatan yang diikuti kenaikan grade.'],
        'deib' => ['DEIB — Data Gender', 'Perbandingan headcount, pegawai baru, dan terminasi berdasarkan gender yang tercatat.'],
        'workforce' => ['Demografi Tenaga Kerja', 'Komposisi pegawai berdasarkan usia, unit, dan lokasi kerja.'],
        'development' => ['Pengembangan Kompetensi', 'Asesmen, tingkat kompetensi, kesenjangan, dan saran pengembangan.'],
    ];

    protected function moduleName(): string
    {
        return 'execdashboard';
    }

    public function show(Request $request, string $report)
    {
        $this->authorizeTask('is_view');
        abort_unless(isset(self::REPORTS[$report]), 404);
        [$start, $end, $month] = $this->period($request);
        // Cakupan selama bulan laporan: pegawai yang keluar/pindah tetap terhitung di unit asalnya
        $visible = EmployeeScope::forPeriod($start, $end)->select('employees.id');

        $data = match ($report) {
            'turnover' => $this->turnover($visible, $start, $end, $month),
            'promotions' => $this->promotions($start, $end, $month),
            'deib' => $this->deib($visible, $start, $end),
            'workforce' => $this->workforce($end),
            'development' => $this->development($visible),
        };

        return view('executive.analytics', $data + [
            'report' => $report, 'pageTitle' => self::REPORTS[$report][0], 'description' => self::REPORTS[$report][1],
            'month' => $month, 'start' => $start, 'end' => $end, 'scoped' => ! DataScope::current()->isAll(),
        ]);
    }

    private function period(Request $request): array
    {
        $month = $request->query('month');
        if (! is_string($month) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = now()->format('Y-m');
        }
        $date = Carbon::createFromFormat('Y-m', $month);

        return [$date->copy()->startOfMonth()->toDateString(), $date->copy()->endOfMonth()->toDateString(), $month];
    }

    /** Pegawai aktif pada tanggal tertentu, dalam cakupan pada tanggal itu. */
    private function activeAt(string $date)
    {
        return EmployeeScope::forPeriod($date, $date)->where('employees.join_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('employees.terminated_at')->orWhere('employees.terminated_at', '>', $date));
    }

    private function headcount(string $date): int
    {
        return $this->activeAt($date)->count();
    }

    private function turnover($visible, string $start, string $end, string $month): array
    {
        $scope = DataScope::current();
        // 13 titik: bulan pertama hanya menjadi headcount pembuka untuk tingkat turnover 12 bulan
        $series = WorkforceTrend::monthly($month, $end, $scope, 13);
        $openingDate = Carbon::parse($start)->subDay()->toDateString();
        // Cakupan "semua": headcount awal/akhir = titik tren; cakupan unit dihitung per tanggal itu
        $opening = $scope->isAll() ? $series[11]['headcount'] : $this->headcount($openingDate);
        $closing = $scope->isAll() ? $series[12]['headcount'] : $this->headcount($end);
        $terms = TenantScope::table('employee_terminations')->where('state', 'processed')
            ->whereBetween('termination_date', [$start, $end])->whereIn('employee_id', $visible)->count();
        $openingCohortTerms = TenantScope::table('employee_terminations')->join('employees', 'employees.id', '=', 'employee_terminations.employee_id')
            ->whereIn('employee_terminations.employee_id', $visible)->where('employee_terminations.state', 'processed')
            ->whereBetween('employee_terminations.termination_date', [$start, $end])->where('employees.join_date', '<', $start)
            ->where(fn ($q) => $q->whereNull('employees.terminated_at')->orWhere('employees.terminated_at', '>=', $start))->count();
        $average = ($opening + $closing) / 2;
        $rate = $average > 0 ? round($terms / $average * 100, 1) : null;
        $retention = $opening > 0 ? round(max(0, ($opening - $openingCohortTerms) / $opening * 100), 1) : null;

        $trend = collect($series)->slice(1)->values()->map(function ($point, $i) use ($series) {
            $avg = ($series[$i]['headcount'] + $point['headcount']) / 2;

            return $point + ['rate' => $avg > 0 ? round($point['terminations'] / $avg * 100, 2) : 0.0];
        })->all();

        return [
            'metrics' => [
                ['Pegawai awal periode', $opening, 'aktif per '.Carbon::parse($openingDate)->format('d/m/Y')],
                ['Terminasi diproses', $terms, 'selama bulan laporan'],
                ['Pegawai akhir periode', $closing, 'aktif per '.Carbon::parse($end)->format('d/m/Y')],
                ['Tingkat turnover', $rate !== null ? $rate.'%' : '—', 'terminasi ÷ rata-rata headcount'],
                ['Retensi pegawai awal', $retention !== null ? $retention.'%' : '—', 'pegawai awal yang tetap bekerja hingga akhir'],
            ],
            'opening' => $opening, 'closing' => $closing, 'terms' => $terms, 'rate' => $rate, 'retention' => $retention,
            'trend' => $trend,
            'rows' => TenantScope::table('employee_terminations')->where('state', 'processed')->whereBetween('termination_date', [$start, $end])
                ->whereIn('employee_id', $visible)->selectRaw('type, COUNT(*) as total')->groupBy('type')->orderByDesc('total')->get()
                ->map(fn ($row) => ['label' => $this->terminationLabel($row->type), 'value' => (int) $row->total]),
            'note' => 'Retensi dihitung dari pegawai yang aktif sebelum bulan laporan. Terminasi terjadwal dan dibatalkan tidak dihitung.',
        ];
    }

    private function promotions(string $start, string $end, string $month): array
    {
        // Jendela 12 bulan untuk tren; angka utama tetap bulan laporan
        $windowStart = Carbon::createFromFormat('Y-m-d', $month.'-01')->subMonthsNoOverflow(11)->toDateString();
        $visible = EmployeeScope::forPeriod($windowStart, $end)->select('employees.id');
        $previousPosition = DB::table('employee_assignments as prior_a')->select('prior_a.position_id')
            ->whereColumn('prior_a.tenant_id', 'next_a.tenant_id')->whereColumn('prior_a.employee_id', 'next_a.employee_id')
            ->where('prior_a.type', 'primary')->whereColumn('prior_a.effective_from', '<', 'next_a.effective_from')
            ->whereNotNull('prior_a.effective_to')->whereColumn('prior_a.effective_to', '<=', 'next_a.effective_from')
            ->orderByDesc('prior_a.effective_from')->orderByDesc('prior_a.id')->limit(1);
        $all = DB::table('employee_assignments as next_a')->where('next_a.tenant_id', TenantScope::tenantId())
            ->join('positions as next_p', 'next_p.id', '=', 'next_a.position_id')
            ->join('job_grades as next_g', 'next_g.id', '=', 'next_p.grade_id')
            // Binding subquery ditempelkan ke klausa JOIN (bukan mergeBindings, yang menaruhnya di
            // bagian WHERE sehingga nilai parameter tertukar dan laporan selalu kosong)
            ->join('positions as prior_p', function ($join) use ($previousPosition) {
                $join->on('prior_p.id', '=', DB::raw('('.$previousPosition->toSql().')'))
                    ->addBinding($previousPosition->getBindings(), 'join');
            })
            ->join('job_grades as prior_g', 'prior_g.id', '=', 'prior_p.grade_id')
            ->join('unit', 'unit.id_unit', '=', 'next_p.unit_id')
            ->where('next_a.type', 'primary')->whereBetween('next_a.effective_from', [$windowStart, $end])
            ->whereIn('next_a.employee_id', $visible)->whereColumn('next_g.rank', '>', 'prior_g.rank')
            ->select('next_a.employee_id', 'next_a.effective_from', 'unit.unit_name', 'prior_g.name as from_grade', 'next_g.name as to_grade')->get();
        $moves = $all->filter(fn ($m) => $m->effective_from >= $start)->values();
        $byUnit = $moves->groupBy('unit_name')->map(fn ($items, $unit) => ['label' => $unit, 'value' => $items->count()])->sortByDesc('value')->values();
        $transitions = $moves->groupBy(fn ($m) => $m->from_grade.'→'.$m->to_grade)
            ->map(fn ($items) => ['from' => $items[0]->from_grade, 'to' => $items[0]->to_grade, 'value' => $items->count()])
            ->sortByDesc('value')->values();
        $perMonth = $all->countBy(fn ($m) => substr((string) $m->effective_from, 0, 7));
        $trend = collect(range(11, 0))->map(function ($i) use ($month, $perMonth) {
            $m = Carbon::createFromFormat('Y-m-d', $month.'-01')->subMonthsNoOverflow($i);

            return ['ym' => $m->format('Y-m'), 'label' => $m->translatedFormat('M y'), 'value' => (int) ($perMonth[$m->format('Y-m')] ?? 0)];
        })->all();

        return [
            'metrics' => [
                ['Kenaikan grade terindikasi', $moves->count(), 'perubahan penempatan primer bulan ini'],
                ['Pegawai terdampak', $moves->pluck('employee_id')->unique()->count(), 'berdasarkan perubahan grade yang tersedia'],
                ['Unit tujuan', $moves->pluck('unit_name')->unique()->count(), 'unit dengan pergerakan terindikasi'],
            ],
            'rows' => $byUnit, 'transitions' => $transitions, 'trend' => $trend, 'yearTotal' => $all->count(),
            'note' => 'Ini adalah indikasi kenaikan grade berdasarkan urutan grade sebelum dan sesudah penempatan. Database belum mencatat alasan perpindahan sebagai promosi resmi; mutasi tanpa kenaikan grade tidak masuk hitungan.',
        ];
    }

    private function deib($visible, string $start, string $end): array
    {
        $headcount = $this->activeAt($end)
            ->selectRaw("COALESCE(employees.gender, 'unknown') as gender, COUNT(*) as total")->groupBy('gender')->pluck('total', 'gender');
        $hires = (clone $visible)->whereBetween('employees.join_date', [$start, $end])
            ->selectRaw("COALESCE(employees.gender, 'unknown') as gender, COUNT(*) as total")->groupBy('gender')->pluck('total', 'gender');
        $terms = TenantScope::table('employee_terminations')->join('employees', 'employees.id', '=', 'employee_terminations.employee_id')
            ->whereIn('employee_terminations.employee_id', $visible)->where('employee_terminations.state', 'processed')
            ->whereBetween('employee_terminations.termination_date', [$start, $end])
            ->selectRaw("COALESCE(employees.gender, 'unknown') as gender, COUNT(*) as total")->groupBy('gender')->pluck('total', 'gender');
        $rows = collect(['F' => 'Perempuan', 'M' => 'Laki-laki', 'unknown' => 'Belum diisi'])
            ->map(fn ($label, $gender) => ['key' => $gender, 'label' => $label, 'headcount' => (int) ($headcount[$gender] ?? 0), 'hires' => (int) ($hires[$gender] ?? 0), 'terminations' => (int) ($terms[$gender] ?? 0)])
            ->values();

        return [
            'metrics' => [
                ['Gender tercatat', (int) ($headcount['F'] ?? 0) + (int) ($headcount['M'] ?? 0), 'pegawai aktif akhir periode'],
                ['Pegawai baru', (int) $hires->sum(), 'selama bulan laporan'],
                ['Terminasi', (int) $terms->sum(), 'sudah diproses selama bulan laporan'],
            ],
            'genderRows' => $rows,
            'note' => 'Skema saat ini mendukung analisis berdasarkan gender. Etnis, disabilitas, serta kandidat dan hasil seleksi belum dicatat; dashboard ini tidak menyajikan metrik untuk kategori tersebut.',
        ];
    }

    private function workforce(string $end): array
    {
        $asOf = $this->activeAt($end);
        $toRows = fn ($rows) => $rows->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->total]);

        // Kelompok usia diurutkan sesuai rentang (bukan abjad)
        $ages = (clone $asOf)->selectRaw("CASE WHEN employees.birth_date IS NULL THEN 'Belum diisi' WHEN TIMESTAMPDIFF(YEAR, employees.birth_date, ?) < 30 THEN 'Di bawah 30' WHEN TIMESTAMPDIFF(YEAR, employees.birth_date, ?) < 40 THEN '30–39' WHEN TIMESTAMPDIFF(YEAR, employees.birth_date, ?) < 50 THEN '40–49' ELSE '50 ke atas' END as label, COUNT(*) as total", [$end, $end, $end])
            ->groupBy('label')->get()->keyBy('label');
        $ages = $toRows(collect(['Di bawah 30', '30–39', '40–49', '50 ke atas', 'Belum diisi'])
            ->filter(fn ($l) => $ages->has($l))->map(fn ($l) => $ages[$l])->values());

        // Penempatan primer per akhir bulan, JOIN ke pegawai aktif (bukan IN-subquery: 1,1 dtk pada 10.000 pegawai)
        $placements = fn () => EmployeeScope::applyForPeriod(
            TenantScope::table('employee_assignments')
                ->join('employees', function ($j) use ($end) {
                    $j->on('employees.id', '=', 'employee_assignments.employee_id')->whereNull('employees.deleted_at')
                        ->where('employees.join_date', '<=', $end)
                        ->where(fn ($q) => $q->whereNull('employees.terminated_at')->orWhere('employees.terminated_at', '>', $end));
                })
                ->join('positions', 'positions.id', '=', 'employee_assignments.position_id')
                ->where('employee_assignments.type', 'primary')->where('employee_assignments.effective_from', '<=', $end)
                ->where(fn ($q) => $q->whereNull('employee_assignments.effective_to')->orWhere('employee_assignments.effective_to', '>=', $end)),
            'employee_assignments.employee_id', $end, $end);

        $unitTotals = $placements()->join('unit', 'unit.id_unit', '=', 'positions.unit_id')
            ->selectRaw('unit.unit_name as label, COUNT(DISTINCT employee_assignments.employee_id) as total')->groupBy('unit.unit_name')->orderByDesc('total')->get();
        $locationTotals = $placements()
            ->selectRaw("COALESCE(NULLIF(positions.work_location, ''), 'Belum diisi') as label, COUNT(DISTINCT employee_assignments.employee_id) as total")
            ->groupBy('label')->orderByDesc('total')->get();
        // Urutan tetap Perempuan, Laki-laki, Belum diisi (slot warna mengikuti entitas, bukan peringkat)
        $genderCounts = (clone $asOf)->selectRaw("CASE employees.gender WHEN 'F' THEN 'Perempuan' WHEN 'M' THEN 'Laki-laki' ELSE 'Belum diisi' END as label, COUNT(*) as total")
            ->groupBy('label')->pluck('total', 'label');
        $gender = collect(['Perempuan', 'Laki-laki', 'Belum diisi'])->filter(fn ($l) => isset($genderCounts[$l]))
            ->map(fn ($l) => ['label' => $l, 'value' => (int) $genderCounts[$l]])->values();
        $headcount = (int) $gender->sum('value');

        return [
            'metrics' => [
                ['Pegawai aktif', $headcount, 'per '.Carbon::parse($end)->format('d/m/Y')],
                // Hitung dari seluruh unit/lokasi, bukan dari daftar 20 teratas yang ditampilkan
                ['Unit terisi', $unitTotals->count(), 'dengan penempatan primer aktif'],
                ['Lokasi kerja', $locationTotals->count(), 'berdasarkan lokasi pada posisi'],
            ],
            'headcount' => $headcount, 'ages' => $ages, 'gender' => $gender,
            'units' => $toRows($unitTotals), 'locations' => $toRows($locationTotals),
            'note' => 'Usia dihitung dari tanggal lahir. Lokasi berasal dari lokasi posisi, bukan alamat rumah pegawai. Etnis dan disabilitas belum tersedia dalam skema.',
        ];
    }

    private function development($visible): array
    {
        $assessments = TenantScope::table('competency_assessments')->whereIn('employee_id', $visible);
        $closed = (clone $assessments)->where('status', 'closed')->count();
        $open = (clone $assessments)->where('status', 'open')->count();
        $gaps = TenantScope::table('competency_gaps')->whereIn('employee_id', $visible)->selectRaw('competency_code, COUNT(*) as total, AVG(gap) as average_gap')
            ->groupBy('competency_code')->orderBy('average_gap')->limit(10)->get();
        $names = $gaps->isEmpty() ? collect() : TenantScope::table('competencies')->whereIn('code', $gaps->pluck('competency_code'))
            ->orderBy('id')->pluck('name', 'code');
        $skills = TenantScope::table('employee_competencies')->whereIn('employee_id', $visible)->distinct('employee_id')->count('employee_id');
        $idp = TenantScope::table('competency_idp_suggestions')->whereIn('employee_id', $visible)->whereIn('status', ['draft', 'accepted'])->count();

        return [
            'metrics' => [
                ['Asesmen selesai', $closed, 'asesmen kompetensi ditutup'],
                ['Asesmen berjalan', $open, 'menunggu penyelesaian'],
                ['Pegawai dengan data kompetensi', $skills, 'memiliki hasil kompetensi tersimpan'],
                ['Saran IDP', $idp, 'draf atau diterima'],
            ],
            'closed' => $closed, 'open' => $open, 'skills' => $skills, 'idp' => $idp,
            'completion' => $closed + $open > 0 ? round($closed / ($closed + $open) * 100, 1) : null,
            'rows' => $gaps->map(fn ($r) => [
                'label' => $r->competency_code, 'name' => $names[$r->competency_code] ?? null, 'value' => (int) $r->total,
                'gap' => round((float) $r->average_gap, 2), 'detail' => number_format((float) $r->average_gap, 2, ',', '.'),
            ]),
            'note' => 'Data pendidikan, pengalaman, dan sertifikasi juga tersedia di profil pegawai. Riwayat kursus/pelatihan dan hasil belajar belum dicatat sebagai aktivitas pembelajaran.',
        ];
    }

    private function terminationLabel(string $type): string
    {
        return ['resign' => 'Mengundurkan diri', 'layoff' => 'Pengurangan pegawai', 'retirement' => 'Pensiun', 'contract_end' => 'Kontrak berakhir', 'death' => 'Meninggal dunia', 'other' => 'Lainnya'][$type] ?? $type;
    }
}
