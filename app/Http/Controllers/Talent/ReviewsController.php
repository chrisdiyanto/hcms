<?php

namespace App\Http\Controllers\Talent;

use App\Domain\Talent\TalentReviews;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLog;
use App\Support\Security\Roles;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Talenta › Talent Review: siklus review, panel, Nine Box, kalibrasi, pengesahan.
 * Nama & posisi individu di Nine Box dan flight risk hanya tampil bagi pemegang izin sensitif;
 * setiap pembukaan review tercatat di audit.
 */
class ReviewsController extends Controller
{
    use AuthorizesHcmsModule;

    public function __construct(private TalentReviews $talent)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'talentreviews';
    }

    public function index()
    {
        $access = $this->authorizeTask('is_view');
        $reviews = TenantScope::table('talent_reviews')->join('performance_periods as pp', 'pp.id', '=', 'talent_reviews.performance_period_id')
            ->leftJoin('unit as u', 'u.id_unit', '=', 'talent_reviews.unit_id')->orderByDesc('talent_reviews.review_date')
            ->selectRaw('talent_reviews.*, pp.name as period_name, u.unit_name,
                (SELECT COUNT(*) FROM talent_review_entries e WHERE e.review_id = talent_reviews.id) as total,
                (SELECT COUNT(*) FROM talent_review_entries e WHERE e.review_id = talent_reviews.id AND e.data_status = \'complete\') as complete')
            ->get();

        return view('talent.reviews.index', [
            'pageTitle' => 'Talent Review', 'pageNote' => 'Nine Box', 'reviews' => $reviews,
            'statuses' => TalentReviews::STATUS, 'canAdd' => ! empty($access['is_add']),
            'periods' => TenantScope::table('performance_periods')->where('status', 'closed')->orderByDesc('end_date')->get(['id', 'name']),
            'units' => TenantScope::table('unit')->orderBy('unit_name')->pluck('unit_name', 'id_unit'),
            'panelists' => TenantScope::table('tb_users')->join('tb_users_groups as g', 'g.user_id', '=', 'tb_users.id')
                ->whereIn('g.group_id', [Roles::id(Roles::TALENT_COMMITTEE), Roles::id(Roles::HR_ADMIN), Roles::id(Roles::TENANT_ADMIN), Roles::id(Roles::EXECUTIVE)])
                ->where('tb_users.active', 1)->orderBy('tb_users.first_name')->get(['tb_users.id', 'tb_users.first_name', 'tb_users.last_name'])->unique('id'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTask('is_add');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'review_date' => ['required', 'date'],
            'performance_period_id' => ['required', 'integer', Rule::in(TenantScope::table('performance_periods')->where('status', 'closed')->pluck('id')->all())],
            'unit_id' => ['nullable', 'integer', TenantScope::existsRule('unit', 'id_unit')],
            'panel_user_ids' => ['required', 'array', 'min:2'], 'panel_user_ids.*' => ['integer', TenantScope::existsRule('tb_users', 'id')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['panel_user_ids.min' => 'Panel talent review minimal 2 orang.', 'performance_period_id.in' => 'Pilih periode kinerja yang sudah ditutup (rating final).']);
        $id = DB::table('talent_reviews')->insertGetId(TenantScope::fillForInsert('talent_reviews', [
            'name' => $data['name'], 'review_date' => $data['review_date'], 'performance_period_id' => $data['performance_period_id'],
            'unit_id' => $data['unit_id'] ?? null, 'panel_user_ids' => json_encode(array_values(array_map('intval', $data['panel_user_ids']))),
            'notes' => $data['notes'] ?? null, 'status' => 'draft', 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
        ]));
        AuditLog::created('talent_reviews', $id, collect($data)->except('panel_user_ids')->all() + ['panel' => count($data['panel_user_ids'])], 'Talent review dibuat');
        $result = $this->talent->populate($id);

        return redirect('talent/reviews/'.$id)->with('message', $result['total'].' pegawai masuk review. Lengkapi asesmen potensi yang belum ada, lalu panel melakukan kalibrasi.')->with('status', 'success');
    }

    public function show(int $id)
    {
        $access = $this->authorizeTask('is_view');
        $review = $this->talent->review($id) ?? abort(404);
        $sensitive = ! empty($access['is_sensitive']);
        $isPanel = in_array((int) auth()->id(), $review->panel, true);
        AuditLog::sensitiveViewed('talent_reviews', $id, 'Membuka talent review '.$review->name.($sensitive ? ' (dengan data individu)' : ''));

        $entries = $this->talent->entries($review);

        return view('talent.reviews.show', [
            'pageTitle' => $review->name, 'pageNote' => TalentReviews::STATUS[$review->status],
            'review' => $review, 'entries' => $entries, 'sensitive' => $sensitive,
            'canDecide' => $review->status !== 'approved' && ($isPanel || ! empty($access['is_edit'])),
            'canApprove' => $review->status !== 'approved' && ! empty($access['is_approve']) && (int) $review->created_by !== (int) auth()->id(),
            'boxLabels' => TalentReviews::BOX_LABELS, 'levels' => TalentReviews::LEVELS, 'dataStatus' => TalentReviews::DATA_STATUS,
            'panel' => TenantScope::table('tb_users')->whereIn('id', $review->panel)->get(['id', 'first_name', 'last_name']),
            'owners' => TenantScope::table('tb_users')->where('active', 1)->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function populate(int $id)
    {
        $this->authorizeTask('is_edit');
        $result = $this->talent->populate($id);

        return redirect('talent/reviews/'.$id)->with('message', sprintf('Peserta diperbarui: %d pegawai (%d baru, %d keluar dari cakupan).', $result['total'], $result['added'], $result['removed']))
            ->with('status', 'success');
    }

    public function decide(Request $request, int $entry)
    {
        $access = $this->authorizeTask('is_view');
        $row = TenantScope::table('talent_review_entries')->where('id', $entry)->first() ?? abort(404);
        $review = $this->talent->review((int) $row->review_id);
        abort_unless(in_array((int) auth()->id(), $review->panel, true) || ! empty($access['is_edit']), 403);
        $rules = [
            'final_box' => ['nullable', 'integer', 'between:1,9'],
            'justification' => ['nullable', 'string', 'max:1000'],
            'development_action' => ['nullable', 'string', 'max:500'],
            'action_owner_user_id' => ['nullable', 'integer', TenantScope::existsRule('tb_users', 'id')],
            'next_review_date' => ['nullable', 'date'],
        ];
        if (! empty($access['is_sensitive'])) {
            $rules += ['flight_risk' => ['nullable', Rule::in(array_keys(TalentReviews::LEVELS))], 'flight_risk_reason' => ['nullable', 'string', 'max:500']];
        }
        $this->talent->decide($entry, $request->validate($rules), (int) auth()->id());

        return redirect('talent/reviews/'.$review->id.'#entry-'.$entry)->with('message', 'Keputusan panel tersimpan.')->with('status', 'success');
    }

    public function approve(Request $request, int $id)
    {
        $this->authorizeTask('is_approve');
        $this->talent->approve($id, (int) auth()->id());
        $this->logActivity($request, 'Talent review #'.$id.' disahkan');

        return redirect('talent/reviews/'.$id)->with('message', 'Talent review disahkan dan tidak dapat diubah lagi.')->with('status', 'success');
    }
}
