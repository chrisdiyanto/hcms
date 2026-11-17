<?php

namespace App\Http\Controllers;

use App\Domain\Employee\Services\EmployeeScope;
use App\Domain\Employee\Services\WorkforceTrend;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Support\Security\DataScope;
use App\Support\Tenancy\TenantScope;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan Eksekutif per bulan laporan: headcount akhir bulan, pegawai baru, terminasi,
 * status kerja (dari riwayat status), pegawai per unit, dan posisi terbuka.
 *
 * Cakupan data dihitung untuk periode laporan (EmployeeScope::forPeriod), sehingga pegawai yang
 * sudah keluar tetap terhitung di unit asalnya. Agregasi memakai JOIN, bukan IN-subquery, karena
 * pola IN + COUNT(DISTINCT) membuat halaman 1,4 dtk pada 10.000 pegawai (uji beban Fase 4).
 */
class ExecutiveDashboardController extends Controller
{
    use AuthorizesHcmsModule;

    protected function moduleName(): string
    {
        return 'execdashboard';
    }

    public function index(Request $request)
    {
        $this->authorizeTask('is_view');
        $month = $request->query('month');
        if (! is_string($month) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = now()->format('Y-m');
        }
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $end = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();
        $scope = DataScope::current();

        // Pegawai aktif per akhir bulan, dalam cakupan per akhir bulan
        $asOf = EmployeeScope::applyForPeriod(
            TenantScope::table('employees')->whereNull('employees.deleted_at')->where('employees.join_date', '<=', $end)
                ->where(fn ($q) => $q->whereNull('employees.terminated_at')->orWhere('employees.terminated_at', '>', $end)),
            'employees.id', $end, $end, $scope);

        $headcount = (clone $asOf)->count();
        $gender = (clone $asOf)->selectRaw("COALESCE(gender, 'unknown') as category, COUNT(*) as total")->groupBy('category')->pluck('total', 'category');
        // Status kerja per akhir bulan. Pegawai tanpa riwayat perubahan status = status saat ini (satu GROUP BY);
        // hanya yang punya riwayat dihitung dengan subquery riwayat (uji beban: subquery untuk semua 10.000 pegawai = 200 ms)
        $withHistory = fn ($q) => $q->whereIn('employees.id', TenantScope::table('employee_status_histories')
            ->where('field', 'employment_status')->select('employee_id'));
        $employment = (clone $asOf)->whereNotIn('employees.id', TenantScope::table('employee_status_histories')
            ->where('field', 'employment_status')->select('employee_id'))
            ->selectRaw('employees.employment_status as category, COUNT(*) as total')->groupBy('category')->pluck('total', 'category');
        $statusSnapshot = $withHistory(clone $asOf)->select('employees.id')->selectRaw(
            "COALESCE(
                (SELECT h.new_value FROM employee_status_histories h WHERE h.tenant_id = employees.tenant_id AND h.employee_id = employees.id AND h.field = 'employment_status' AND h.effective_date <= ? ORDER BY h.effective_date DESC, h.id DESC LIMIT 1),
                (SELECT h.old_value FROM employee_status_histories h WHERE h.tenant_id = employees.tenant_id AND h.employee_id = employees.id AND h.field = 'employment_status' AND h.effective_date > ? ORDER BY h.effective_date ASC, h.id ASC LIMIT 1),
                employees.employment_status
            ) as category", [$end, $end]
        );
        foreach (DB::query()->fromSub($statusSnapshot, 'employment_snapshot')->selectRaw('category, COUNT(*) as total')->groupBy('category')->pluck('total', 'category') as $category => $total) {
            $employment[$category] = (int) ($employment[$category] ?? 0) + (int) $total;
        }

        // Pergerakan selama bulan: cakupan dihitung untuk seluruh bulan (termasuk yang keluar)
        $hires = EmployeeScope::applyForPeriod(TenantScope::table('employees')->whereNull('employees.deleted_at')
            ->whereBetween('employees.join_date', [$start, $end]), 'employees.id', $start, $end, $scope)->count();
        $terminations = EmployeeScope::applyForPeriod(TenantScope::table('employee_terminations')->where('state', 'processed')
            ->whereBetween('termination_date', [$start, $end]), 'employee_terminations.employee_id', $start, $end, $scope)->count();

        // Penempatan primer yang berlaku di akhir bulan untuk pegawai aktif dalam cakupan
        $placements = fn (): Builder => EmployeeScope::applyForPeriod(
            TenantScope::table('employee_assignments')
                ->join('employees', function ($j) use ($end) {
                    $j->on('employees.id', '=', 'employee_assignments.employee_id')->whereNull('employees.deleted_at')
                        ->where('employees.join_date', '<=', $end)
                        ->where(fn ($q) => $q->whereNull('employees.terminated_at')->orWhere('employees.terminated_at', '>', $end));
                })
                ->join('positions', 'positions.id', '=', 'employee_assignments.position_id')
                ->where('employee_assignments.type', 'primary')->where('employee_assignments.effective_from', '<=', $end)
                ->where(fn ($q) => $q->whereNull('employee_assignments.effective_to')->orWhere('employee_assignments.effective_to', '>=', $end)),
            'employee_assignments.employee_id', $end, $end, $scope);

        // Satu agregasi per unit: semua penempatan (pegawai per unit) + yang di posisi aktif (keterisian)
        $byUnitRows = $placements()->join('unit', 'unit.id_unit', '=', 'positions.unit_id')
            ->selectRaw("unit.id_unit, unit.unit_name, COUNT(DISTINCT employee_assignments.employee_id) as total,
                COUNT(DISTINCT CASE WHEN positions.status = 'active' AND positions.effective_from <= ?
                    AND (positions.effective_to IS NULL OR positions.effective_to >= ?) THEN employee_assignments.employee_id END) as occupied", [$end, $end])
            ->groupBy('unit.id_unit', 'unit.unit_name')->orderBy('unit.unit_name')->get();
        $byUnit = $byUnitRows->map(fn ($r) => (object) ['id_unit' => $r->id_unit, 'unit_name' => $r->unit_name, 'total' => (int) $r->total]);
        $occupied = $byUnitRows->pluck('occupied', 'id_unit');

        $activePosition = fn ($q) => $q->where('positions.status', 'active')->where('positions.effective_from', '<=', $end)
            ->where(fn ($w) => $w->whereNull('positions.effective_to')->orWhere('positions.effective_to', '>=', $end));
        $positions = $activePosition(TenantScope::table('positions'))
            ->when(! $scope->isAll(), fn ($q) => $scope->unitIds !== []
                ? $q->whereIn('positions.unit_id', $scope->unitIds)
                : $q->whereRaw('1 = 0'))
            ->selectRaw('unit.id_unit, unit.unit_name, SUM(positions.headcount) as capacity')
            ->join('unit', 'unit.id_unit', '=', 'positions.unit_id')->groupBy('unit.id_unit', 'unit.unit_name')->get()->keyBy('id_unit');
        $openByUnit = $positions->map(fn ($row) => max(0, (int) $row->capacity - (int) ($occupied[$row->id_unit] ?? 0)));

        // KPI turunan: perubahan headcount vs bulan lalu, turnover bulan ini, keterisian posisi
        $capacityTotal = (int) $positions->sum('capacity');
        $filledTotal = $positions->keys()->sum(fn ($unit) => min((int) $positions[$unit]->capacity, (int) ($occupied[$unit] ?? 0)));

        $trend = WorkforceTrend::monthly($month, $end, $scope);
        // Cakupan "semua": headcount bulan lalu = titik tren sebelumnya; cakupan unit dihitung per tanggal itu
        $prevEnd = Carbon::parse($start)->subDay()->toDateString();
        $prevHeadcount = $scope->isAll() ? $trend[10]['headcount'] : EmployeeScope::applyForPeriod(TenantScope::table('employees')->whereNull('employees.deleted_at')
            ->where('employees.join_date', '<=', $prevEnd)
            ->where(fn ($q) => $q->whereNull('employees.terminated_at')->orWhere('employees.terminated_at', '>', $prevEnd)),
            'employees.id', $prevEnd, $prevEnd, $scope)->count();
        $averageHeadcount = ($prevHeadcount + $headcount) / 2;

        return view('executive.dashboard', [
            'pageTitle' => 'Ringkasan Eksekutif', 'pageNote' => Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y'),
            'month' => $month, 'start' => $start, 'end' => $end,
            'headcount' => $headcount, 'female' => (int) ($gender['F'] ?? 0), 'male' => (int) ($gender['M'] ?? 0),
            'unknownGender' => (int) ($gender['unknown'] ?? 0), 'hires' => $hires, 'terminations' => $terminations,
            'employment' => collect(['pkwtt' => 'Tetap', 'pkwt' => 'Kontrak', 'probation' => 'Masa percobaan', 'outsourcing' => 'Alih daya', 'internship' => 'Magang'])
                ->map(fn ($label, $key) => ['label' => $label, 'count' => (int) ($employment[$key] ?? 0)]),
            'byUnit' => $byUnit, 'positions' => $positions, 'openByUnit' => $openByUnit, 'openTotal' => $openByUnit->sum(),
            'prevHeadcount' => $prevHeadcount,
            'turnoverRate' => $averageHeadcount > 0 ? round($terminations / $averageHeadcount * 100, 1) : null,
            'fillRate' => $capacityTotal > 0 ? round($filledTotal / $capacityTotal * 100, 1) : null,
            'capacityTotal' => $capacityTotal, 'filledTotal' => $filledTotal,
            'trend' => $trend, 'scoped' => ! $scope->isAll(),
        ]);
    }
}
