<?php

namespace App\Domain\Employee\Services;

use App\Support\Security\DataScope;
use App\Support\Tenancy\TenantScope;
use Carbon\Carbon;

/**
 * Tren bulanan tenaga kerja yang berakhir di bulan laporan: headcount akhir bulan (satu query
 * dengan SUM bersyarat per bulan), pegawai baru, dan terminasi diproses per bulan.
 * Dipakai Ringkasan Eksekutif (12 bulan) dan laporan Turnover (13 bulan: bulan pertama = pembuka).
 */
class WorkforceTrend
{
    /**
     * @return array<int, array{ym: string, label: string, headcount: int, hires: int, terminations: int}>
     */
    public static function monthly(string $month, string $end, DataScope $scope, int $count = 12): array
    {
        $months = collect(range($count - 1, 0))->map(fn ($i) => Carbon::createFromFormat('Y-m-d', $month.'-01')->subMonthsNoOverflow($i));
        $windowStart = $months->first()->toDateString();
        $ends = $months->map(fn ($m) => $m->copy()->endOfMonth()->toDateString())->values();

        $select = $ends->keys()->map(fn ($i) => "SUM(employees.join_date <= ? AND (employees.terminated_at IS NULL OR employees.terminated_at > ?)) as m{$i}")->implode(', ');
        $row = EmployeeScope::applyForPeriod(TenantScope::table('employees')->whereNull('employees.deleted_at')->where('employees.join_date', '<=', $end),
            'employees.id', $windowStart, $end, $scope)
            ->selectRaw($select, $ends->flatMap(fn ($d) => [$d, $d])->all())->first();

        $hires = EmployeeScope::applyForPeriod(TenantScope::table('employees')->whereNull('employees.deleted_at')
            ->whereBetween('employees.join_date', [$windowStart, $end]), 'employees.id', $windowStart, $end, $scope)
            ->selectRaw("DATE_FORMAT(employees.join_date, '%Y-%m') as ym, COUNT(*) as n")->groupBy('ym')->pluck('n', 'ym');
        $terms = EmployeeScope::applyForPeriod(TenantScope::table('employee_terminations')->where('state', 'processed')
            ->whereBetween('termination_date', [$windowStart, $end]), 'employee_terminations.employee_id', $windowStart, $end, $scope)
            ->selectRaw("DATE_FORMAT(termination_date, '%Y-%m') as ym, COUNT(*) as n")->groupBy('ym')->pluck('n', 'ym');

        return $months->values()->map(fn ($m, $i) => [
            'ym' => $m->format('Y-m'), 'label' => $m->translatedFormat('M y'),
            'headcount' => (int) ($row->{'m'.$i} ?? 0),
            'hires' => (int) ($hires[$m->format('Y-m')] ?? 0), 'terminations' => (int) ($terms[$m->format('Y-m')] ?? 0),
        ])->all();
    }
}
