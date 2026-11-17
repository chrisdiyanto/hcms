<?php

namespace App\Http\Controllers\Talent;

use App\Domain\Talent\TalentReviews;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Models\Sximo;
use App\Support\Privacy\SmallGroups;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;

/**
 * Laporan Nine Box (laporan CBHRM 9, agregat): sebaran kotak, cakupan & kelengkapan data, per unit,
 * pergerakan dibanding review sah sebelumnya, keragaman gender talenta kunci, dan risiko kehilangan
 * talenta kunci (hanya bagi pemegang izin sensitif Talent Review). Tanpa nama individu; kelompok
 * 1–4 orang disamarkan bagi pengguna tanpa akses data talent individual.
 */
class NineBoxController extends Controller
{
    use AuthorizesHcmsModule;

    public const GROUPS = ['key' => [6, 8, 9], 'core' => [2, 3, 5], 'attention' => [1, 4, 7]];

    public const GROUP_LABELS = ['key' => 'Talenta kunci (6, 8, 9)', 'core' => 'Inti (2, 3, 5)', 'attention' => 'Perlu tindak lanjut (1, 4, 7)'];

    protected function moduleName(): string
    {
        return 'nineboxreport';
    }

    /** Izin data sensitif pada modul Talent Review (risiko kehilangan talenta kunci). */
    private function talentSensitive(): bool
    {
        $model = new Sximo;
        $info = $model->makeInfo('talentreviews');
        $access = isset($info['id']) ? $model->validAccess($info['id'], session('gid')) : false;

        return is_array($access) && ! empty($access['is_sensitive']);
    }

    public function index(Request $request)
    {
        $this->authorizeTask('is_view');
        $reviews = TenantScope::table('talent_reviews')->where('status', 'approved')->orderByDesc('review_date')->get(['id', 'name', 'review_date']);
        $review = $reviews->firstWhere('id', (int) $request->query('review')) ?? $reviews->first();
        $sensitive = $this->talentSensitive();

        $data = ['pageTitle' => 'Laporan Nine Box', 'reviews' => $reviews, 'review' => $review, 'mask' => app(SmallGroups::class)->masks('talentreviews'),
            'boxLabels' => TalentReviews::BOX_LABELS, 'groupLabels' => self::GROUP_LABELS, 'sensitive' => $sensitive];
        if (! $review) {
            return view('talent.nine-box', $data + ['entries' => collect()]);
        }

        $entries = TenantScope::table('talent_review_entries')->join('employees', 'employees.id', '=', 'talent_review_entries.employee_id')
            ->leftJoin('unit as u', 'u.id_unit', '=', 'talent_review_entries.unit_id')->where('talent_review_entries.review_id', $review->id)
            ->get(['talent_review_entries.employee_id', 'talent_review_entries.final_box', 'talent_review_entries.data_status', 'talent_review_entries.flight_risk',
                'employees.gender', 'u.unit_name']);
        $placed = $entries->whereNotNull('final_box');
        $group = fn ($box) => collect(self::GROUPS)->search(fn ($boxes) => in_array((int) $box, $boxes, true));

        // Pergerakan: perubahan jumlah indeks kinerja + potensi dibanding review sah sebelumnya
        $previous = TenantScope::table('talent_reviews')->where('status', 'approved')->where('review_date', '<', $review->review_date)->orderByDesc('review_date')->first(['id', 'name']);
        $moves = ['up' => 0, 'same' => 0, 'down' => 0];
        if ($previous) {
            $before = TenantScope::table('talent_review_entries')->where('review_id', $previous->id)->whereNotNull('final_box')->pluck('final_box', 'employee_id');
            foreach ($placed as $e) {
                if (! isset($before[$e->employee_id])) {
                    continue;
                }
                $score = fn (int $box) => array_sum(array_map(fn ($l) => TalentReviews::LEVEL_INDEX[$l], TalentReviews::axes($box)));
                $delta = $score((int) $e->final_box) - $score((int) $before[$e->employee_id]);
                $moves[$delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'same')]++;
            }
        }
        $key = $placed->filter(fn ($e) => $group($e->final_box) === 'key');

        return view('talent.nine-box', $data + [
            'entries' => $entries, 'placed' => $placed,
            'boxCounts' => collect(range(1, 9))->mapWithKeys(fn ($b) => [$b => $placed->where('final_box', $b)->count()]),
            'groupCounts' => collect(self::GROUPS)->map(fn ($boxes) => $placed->whereIn('final_box', $boxes)->count()),
            'incomplete' => $entries->where('data_status', '!=', 'complete')->countBy('data_status'),
            'byUnit' => $placed->groupBy(fn ($e) => $e->unit_name ?? 'Tanpa unit')->map(fn ($rows) => collect(self::GROUPS)->map(fn ($boxes) => $rows->whereIn('final_box', $boxes)->count())->all()
                + ['total' => $rows->count()])->sortByDesc('total'),
            'previous' => $previous, 'moves' => $moves,
            'keyFemale' => $key->where('gender', 'F')->count(), 'keyMale' => $key->where('gender', 'M')->count(),
            'allFemale' => $placed->where('gender', 'F')->count(), 'allMale' => $placed->where('gender', 'M')->count(),
            'keyHighRisk' => $key->where('flight_risk', 'high')->count(),
            'dataStatus' => TalentReviews::DATA_STATUS,
        ]);
    }
}
