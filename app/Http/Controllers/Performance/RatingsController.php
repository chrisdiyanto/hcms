<?php

namespace App\Http\Controllers\Performance;

use App\Domain\Employee\Services\EmployeeScope;
use App\Domain\Performance\Services\PerformancePeriods;
use App\Domain\Performance\Services\PerformanceRatings;
use App\Http\Controllers\Controller;
use App\Models\Sximo;
use App\Support\Audit\DataExport;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;

/**
 * Pengesahan rating (HR, izin Approve modul Periode), rating & keberatan pegawai,
 * dan daftar keberatan untuk pejabat pemutus / HR.
 */
class RatingsController extends Controller
{
    public function __construct(private PerformanceRatings $ratings, private PerformancePeriods $periods)
    {
        parent::__construct();
    }

    public function publish(Request $request, int $period)
    {
        abort_unless(! empty($this->periodAccess()['is_approve']), 403);
        $count = $this->ratings->announce($period, (int) auth()->id());

        return redirect('performance/periods/'.$period.'/ratings')->with('message', $count.' rating sementara diumumkan; jendela keberatan dibuka.')->with('status', 'success');
    }

    public function finalize(Request $request, int $period)
    {
        abort_unless(! empty($this->periodAccess()['is_approve']), 403);
        $count = $this->ratings->finalize($period, (int) auth()->id());

        return redirect('performance/periods/'.$period.'/ratings')->with('message', $count.' rating disahkan final; snapshot dibuat dan periode ditutup.')->with('status', 'success');
    }

    /** Rekap rating periode (HR, sesuai data scope). */
    public function periodRatings(int $period)
    {
        abort_unless(! empty($this->periodAccess()['is_view']), 403);
        $row = $this->periods->find($period);
        $ratings = TenantScope::table('performance_ratings')->join('employees', 'employees.id', '=', 'performance_ratings.employee_id')
            ->where('performance_ratings.period_id', $row->id)->where('performance_ratings.is_current', true)
            ->whereIn('performance_ratings.employee_id', EmployeeScope::query()->select('employees.id'))
            ->orderByDesc('performance_ratings.final_score')->orderBy('performance_ratings.id')
            ->select(['performance_ratings.*', 'employees.full_name', 'employees.employee_number'])
            ->paginate(50)->withQueryString();

        return view('performance.ratings.period', [
            'pageTitle' => 'Rating Kinerja: '.$row->name, 'pageNote' => PerformancePeriods::STATUS[$row->status],
            'period' => $row, 'ratings' => $ratings,
            'snapshots' => TenantScope::table('performance_snapshots')->whereIn('rating_id', $ratings->pluck('id'))->pluck('checksum', 'rating_id'),
        ]);
    }

    /** Ekspor rating periode (CSV): butuh izin ekspor modul Periode, alasan wajib, watermark, tercatat; > 500 baris butuh Approve. */
    public function export(Request $request, int $period)
    {
        $access = $this->periodAccess();
        abort_unless(! empty($access['is_csv']) || ! empty($access['is_excel']), 403);
        $row = $this->periods->find($period);

        $reason = (string) $request->input('export_reason');
        if (! DataExport::validReason($reason)) {
            return view('sximo.module.utility.export-reason', [
                'action' => $request->url(), 'query' => $request->except('export_reason'),
                'threshold' => DataExport::MASS_THRESHOLD, 'min' => DataExport::MIN_REASON,
                'error' => $request->has('export_reason') ? 'Alasan minimal '.DataExport::MIN_REASON.' karakter.' : null,
                'pageTitle' => 'Ekspor Rating Kinerja', 'pageNote' => $row->name,
            ]);
        }

        $ratings = TenantScope::table('performance_ratings')->join('employees', 'employees.id', '=', 'performance_ratings.employee_id')
            ->where('performance_ratings.period_id', $row->id)->where('performance_ratings.is_current', true)
            ->whereIn('performance_ratings.employee_id', EmployeeScope::query()->select('employees.id'))
            ->orderBy('employees.full_name')
            ->get(['performance_ratings.*', 'employees.full_name', 'employees.employee_number']);
        if (! DataExport::allowed($ratings->count(), $access)) {
            return redirect('performance/periods/'.$row->id.'/ratings')
                ->with('message', 'Ekspor lebih dari '.DataExport::MASS_THRESHOLD.' baris membutuhkan izin Approve.')->with('status', 'error');
        }

        $logId = DataExport::log('performance_ratings', 'csv', $ratings->count(), $reason, ['period' => $row->code]);
        $watermark = DataExport::watermark($logId);

        return response()->streamDownload(function () use ($ratings, $watermark, $row) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [$watermark]);
            fputcsv($out, ['Periode', 'NIP', 'Nama', 'Skor', 'Skor mentah', 'Rating', 'Status', 'Revisi', 'Diumumkan', 'Disahkan final']);
            foreach ($ratings as $r) {
                fputcsv($out, [$row->code, $r->employee_number, $r->full_name, $r->rated ? $r->final_score : '', $r->raw_score, $r->band_label,
                    $r->status === 'final' ? 'Final' : 'Sementara', $r->revision, $r->published_at, $r->finalized_at]);
            }
            fclose($out);
        }, 'rating-'.$row->code.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function mine()
    {
        $employee = TenantScope::table('employees')->where('user_id', (int) auth()->id())->first();
        $ratings = $employee ? TenantScope::table('performance_ratings')->join('performance_periods', 'performance_periods.id', '=', 'performance_ratings.period_id')
            ->where('performance_ratings.employee_id', $employee->id)->orderByDesc('performance_periods.start_date')->orderByDesc('performance_ratings.revision')
            ->get(['performance_ratings.*', 'performance_periods.name as period_name']) : collect();

        return view('performance.ratings.mine', [
            'pageTitle' => 'Rating Kinerja Saya', 'pageNote' => 'hasil penilaian yang telah disahkan',
            'ratings' => $ratings,
            'appeals' => $employee ? TenantScope::table('performance_appeals')->where('employee_id', $employee->id)->get()->keyBy('rating_id') : collect(),
            'pips' => $employee ? TenantScope::table('performance_improvement_plans')->where('employee_id', $employee->id)->orderByDesc('id')->get() : collect(),
            'appealStatus' => PerformanceRatings::APPEAL_STATUS,
        ]);
    }

    public function appeal(Request $request, int $rating)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:3000']], [], ['reason' => 'alasan keberatan']);
        $this->ratings->appeal($rating, (int) auth()->id(), $data['reason']);

        return redirect('performance/my-ratings')->with('message', 'Keberatan diajukan.')->with('status', 'success');
    }

    public function appeals()
    {
        $userId = (int) auth()->id();
        $isHr = ! empty($this->periodAccess()['is_approve']);
        $rows = TenantScope::table('performance_appeals')
            ->join('performance_ratings', 'performance_ratings.id', '=', 'performance_appeals.rating_id')
            ->join('employees', 'employees.id', '=', 'performance_appeals.employee_id')
            ->join('performance_periods', 'performance_periods.id', '=', 'performance_ratings.period_id')
            ->where(fn ($q) => $q->where('performance_appeals.decider_user_id', $userId)
                ->when($isHr, fn ($w) => $w->orWhereIn('performance_appeals.employee_id', EmployeeScope::query()->select('employees.id'))))
            ->orderByRaw("performance_appeals.status = 'submitted' DESC")->orderByDesc('performance_appeals.id')
            ->get(['performance_appeals.*', 'employees.full_name', 'performance_ratings.final_score', 'performance_ratings.band_label', 'performance_periods.name as period_name'])
            ->map(function ($a) use ($userId, $isHr) {
                $a->can_decide = $a->status === 'submitted' && $this->ratings->canDecide($a, $userId, $isHr);

                return $a;
            });

        return view('performance.ratings.appeals', [
            'pageTitle' => 'Keberatan Rating Kinerja', 'pageNote' => 'diputus pejabat di atas penilai',
            'appeals' => $rows, 'statuses' => PerformanceRatings::APPEAL_STATUS,
        ]);
    }

    public function decide(Request $request, int $appeal)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:upheld,adjusted'],
            'decision_note' => ['required', 'string', 'max:2000'],
            'new_score' => ['nullable', 'numeric', 'between:0,100'],
        ], [], ['decision_note' => 'alasan keputusan', 'new_score' => 'skor baru']);
        $this->ratings->decide($appeal, (int) auth()->id(), ! empty($this->periodAccess()['is_approve']), $data['decision'] === 'adjusted',
            $data['decision_note'], isset($data['new_score']) ? (float) $data['new_score'] : null);

        return redirect('performance/appeals')->with('message', 'Keputusan keberatan disimpan.')->with('status', 'success');
    }

    private function periodAccess(): array
    {
        $model = new Sximo;
        $info = $model->makeInfo('perfperiods');
        $access = isset($info['id']) ? $model->validAccess($info['id'], session('gid')) : false;

        return is_array($access) ? $access : [];
    }
}
