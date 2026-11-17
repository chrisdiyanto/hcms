<?php

namespace App\Http\Controllers\Employee;

use App\Domain\Development\TrainingDelivery;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;

/** Layanan Mandiri › Pelatihan Saya: riwayat pelatihan & survei reaksi (evaluasi tingkat 1). */
class TrainingsController extends Controller
{
    public function __construct(private TrainingDelivery $delivery)
    {
        parent::__construct();
    }

    public function index()
    {
        $me = $this->me();

        return view('employee.trainings', [
            'pageTitle' => 'Pelatihan Saya', 'pageNote' => $me ? $me->employee_number : '', 'me' => $me,
            'rows' => $me ? TenantScope::table('training_participants')->join('training_sessions as s', 's.id', '=', 'training_participants.session_id')
                ->where('training_participants.employee_id', $me->id)->where('training_participants.status', '!=', 'cancelled')
                ->orderByDesc('s.start_date')
                ->get(['training_participants.*', 's.code', 's.title', 's.start_date', 's.end_date', 's.mode', 's.location', 's.trainer', 's.status as session_status']) : collect(),
            'statuses' => TrainingDelivery::PARTICIPANT_STATUS, 'modes' => TrainingDelivery::MODES,
        ]);
    }

    public function react(Request $request, int $participant)
    {
        $me = $this->me() ?? abort(404);
        $data = $request->validate([
            'reaction_score' => ['required', 'integer', 'between:1,5'],
            'reaction_comment' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->delivery->react($participant, (int) $me->id, (float) $data['reaction_score'], $data['reaction_comment'] ?? null);

        return redirect('ess/trainings')->with('message', 'Terima kasih, penilaian Anda tersimpan.')->with('status', 'success');
    }

    private function me(): ?object
    {
        return TenantScope::table('employees')->where('user_id', auth()->id())->whereNull('deleted_at')->where('status', 'active')->first();
    }
}
