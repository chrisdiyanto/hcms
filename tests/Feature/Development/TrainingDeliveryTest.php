<?php

namespace Tests\Feature\Development;

use App\Domain\Development\TrainingEffectiveness;
use App\Support\Security\Roles;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/** Rilis 2 gelombang 3 — pelaksanaan pelatihan & efektivitas empat tingkat (laporan CBHRM 7). */
class TrainingDeliveryTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $company;

    private int $position;

    private int $risk;

    private int $version;

    private User $hr;

    private User $rinaUser;

    private User $sariUser;

    private int $rina;

    private int $sari;

    private int $budi;

    private int $intervention;

    private int $activity;

    private int $needRina;

    private int $needSari;

    private int $idp;

    private int $year;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->year = now()->year;
        $this->tenant = $this->makeTenant('TA');
        $this->company = $this->makeCompany($this->tenant, 'A1');
        $unit = $this->makeUnit($this->tenant, 'Divisi Risiko', null, $this->company);
        $family = $this->insert('job_families', ['code' => 'RSK', 'name' => 'Risk']);
        $job = $this->insert('jobs_catalog', ['code' => 'RA', 'title' => 'Risk Analyst', 'job_family_id' => $family]);
        $this->position = $this->insert('positions', ['code' => 'ANL', 'title' => 'Analis', 'company_id' => $this->company, 'unit_id' => $unit,
            'job_id' => $job, 'status' => 'active', 'headcount' => 5, 'effective_from' => '2020-01-01']);
        $this->version = $this->insert('competency_framework_versions', ['version' => 1, 'name' => 'v1', 'status' => 'published']);
        $category = DB::table('competency_categories')->insertGetId(['tenant_id' => $this->tenant, 'code' => 'FUNC', 'name' => 'Functional']);
        $this->risk = $this->insert('competencies', ['framework_version_id' => $this->version, 'category_id' => $category, 'code' => 'RISK', 'name' => 'Manajemen Risiko']);

        $this->hr = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $this->rinaUser = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'rina');
        $this->sariUser = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'sari');
        $this->rina = $this->employee($this->rinaUser, 'Rina Analis');
        $this->sari = $this->employee($this->sariUser, 'Sari Analis');
        $this->budi = $this->employee(null, 'Budi Analis');

        $this->intervention = $this->insert('development_interventions', ['code' => 'RISK-101', 'name' => 'Dasar Manajemen Risiko', 'type' => 'training',
            'competency_id' => $this->risk, 'target_level' => 3, 'is_active' => true]);
        // Rina: IDP disetujui dengan aktivitas intervensi ini (dari TNA); Sari: kebutuhan TNA disetujui tanpa IDP
        $this->needRina = $this->need($this->rina, 'planned');
        $this->needSari = $this->need($this->sari, 'approved');
        $this->idp = $this->insert('idps', ['employee_id' => $this->rina, 'period_year' => $this->year, 'status' => 'approved', 'approved_at' => now()]);
        $this->activity = DB::table('idp_activities')->insertGetId(['tenant_id' => $this->tenant, 'idp_id' => $this->idp, 'training_need_id' => $this->needRina,
            'competency_id' => $this->risk, 'competency_code' => 'RISK', 'goal' => 'Level 3 Manajemen Risiko', 'intervention_id' => $this->intervention,
            'activity_type' => 'training', 'title' => 'Dasar Manajemen Risiko', 'due_date' => $this->year.'-12-31', 'status' => 'planned', 'created_at' => now()]);
    }

    private function insert(string $table, array $data): int
    {
        return DB::table($table)->insertGetId($data + ['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'created_at' => now()]);
    }

    private function employee(?User $user, string $name): int
    {
        $id = $this->insert('employees', ['company_id' => $this->company, 'user_id' => $user?->id, 'employee_number' => 'E'.Str::upper(Str::random(5)),
            'full_name' => $name, 'employment_status' => 'pkwtt', 'join_date' => '2021-01-04', 'status' => 'active']);
        $this->insert('employee_assignments', ['employee_id' => $id, 'position_id' => $this->position, 'type' => 'primary', 'effective_from' => '2021-01-04']);

        return $id;
    }

    private function need(int $employee, string $status): int
    {
        return $this->insert('training_needs', ['employee_id' => $employee, 'period_year' => $this->year, 'competency_id' => $this->risk, 'competency_code' => 'RISK',
            'topic' => 'Manajemen Risiko', 'source' => 'gap', 'priority' => 'high', 'reason' => 'Gap', 'status' => $status]);
    }

    public function test_kelas_peserta_kelulusan_dan_evaluasi_empat_tingkat(): void
    {
        $start = $this->year.'-03-10';
        $end = $this->year.'-03-12';
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN))->post('/development/sessions', ['intervention_id' => $this->intervention, 'code' => 'risk-26-01',
            'start_date' => $start, 'end_date' => $end, 'mode' => 'offline', 'trainer' => 'Pak Andi', 'capacity' => 2, 'has_test' => 1])->assertSessionHasNoErrors();
        $session = (int) DB::table('training_sessions')->where('code', 'RISK-26-01')->value('id');
        $this->assertSame('Dasar Manajemen Risiko', DB::table('training_sessions')->where('id', $session)->value('title'));

        // Kandidat: Rina dari IDP, Sari dari TNA; Budi tidak (tanpa IDP/TNA)
        $candidates = $this->get('/development/sessions/'.$session)->assertOk()->viewData('candidates');
        $this->assertSame(['Rina Analis' => 'IDP', 'Sari Analis' => 'TNA'], $candidates->pluck('source', 'full_name')->all());
        $this->post('/development/sessions/'.$session.'/enroll', ['candidates' => [
            $this->rina.':'.$this->activity.':'.$this->needRina, $this->sari.'::'.$this->needSari]])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', DB::table('idp_activities')->where('id', $this->activity)->value('status'));
        $this->post('/development/sessions/'.$session.'/enroll', ['employee_ids' => [$this->budi]])->assertSessionHasErrors('employees'); // kapasitas 2

        // Kehadiran & kelulusan: Rina lulus → aktivitas IDP selesai (bukti otomatis), TNA ditutup, IDP selesai
        $participants = DB::table('training_participants')->where('session_id', $session)->pluck('id', 'employee_id');
        $this->put('/development/sessions/participants/'.$participants[$this->rina], ['status' => 'completed', 'attendance_pct' => 100,
            'pre_score' => 50, 'post_score' => 80, 'certificate_no' => 'CRT-001'])->assertSessionHasNoErrors();
        $activity = DB::table('idp_activities')->where('id', $this->activity)->first();
        $this->assertSame('completed', $activity->status);
        $this->assertStringContainsString('Lulus pelatihan RISK-26-01', $activity->evidence);
        $this->assertSame('closed', DB::table('training_needs')->where('id', $this->needRina)->value('status'));
        $this->assertSame('completed', DB::table('idps')->where('id', $this->idp)->value('status'));
        $this->put('/development/sessions/participants/'.$participants[$this->sari], ['status' => 'attended', 'attendance_pct' => 60,
            'pre_score' => 60, 'post_score' => 55])->assertSessionHasNoErrors();
        $this->assertSame('approved', DB::table('training_needs')->where('id', $this->needSari)->value('status'), 'belum lulus: kebutuhan tetap terbuka');
        $this->post('/development/sessions/'.$session.'/complete', ['cost_total' => 3000000])->assertSessionHasNoErrors();
        $this->assertSame('completed', DB::table('training_sessions')->where('id', $session)->value('status'));

        // Tingkat 1 oleh peserta sendiri, sekali
        $this->actingAsSximo($this->rinaUser, Roles::id(Roles::EMPLOYEE))->get('/ess/trainings')->assertOk()->assertSee('Dasar Manajemen Risiko')->assertSee('CRT-001');
        $this->post('/ess/trainings/'.$participants[$this->rina].'/reaction', ['reaction_score' => 4, 'reaction_comment' => 'Praktis'])->assertSessionHasNoErrors();
        $this->post('/ess/trainings/'.$participants[$this->rina].'/reaction', ['reaction_score' => 5])->assertSessionHasErrors('reaction_score');
        $this->post('/ess/trainings/'.$participants[$this->sari].'/reaction', ['reaction_score' => 1])->assertNotFound();
        $this->actingAsSximo($this->sariUser, Roles::id(Roles::EMPLOYEE))->post('/ess/trainings/'.$participants[$this->sari].'/reaction', ['reaction_score' => 2])->assertSessionHasNoErrors();

        // Tingkat 3: skor RISK Rina 2,0 sebelum kelas → 3,5 pada asesmen 4 bulan setelah kelas
        $assessment = $this->insert('competency_assessments', ['employee_id' => $this->rina, 'framework_version_id' => $this->version, 'position_id' => $this->position,
            'title' => 'Asesmen', 'status' => 'closed']);
        foreach ([[$this->year.'-01-15', 2.0], [$this->year.'-07-20', 3.5]] as $i => [$date, $score]) {
            DB::table('employee_competency_history')->insert(['tenant_id' => $this->tenant, 'employee_id' => $this->rina,
                'assessment_id' => $i === 0 ? $assessment : $this->insert('competency_assessments', ['employee_id' => $this->rina, 'framework_version_id' => $this->version,
                    'position_id' => $this->position, 'title' => 'Asesmen ulang', 'status' => 'closed']),
                'competency_id' => $this->risk, 'competency_code' => 'RISK', 'score' => $score, 'source' => 'raters', 'assessed_at' => $date, 'valid_until' => ($this->year + 2).'-01-01', 'created_at' => now()]);
        }
        // Tingkat 4: rating final 70 (periode sebelum kelas) → 80 (periode berikutnya)
        foreach ([[($this->year - 1).'-01-01', ($this->year - 1).'-12-31', 70], [$this->year.'-01-01', $this->year.'-06-30', 80]] as $i => [$from, $to, $score]) {
            $period = $this->insert('performance_periods', ['code' => 'P'.$i, 'name' => 'Periode '.$i, 'start_date' => $from, 'end_date' => $to, 'status' => 'closed']);
            $this->insert('performance_ratings', ['period_id' => $period, 'employee_id' => $this->rina, 'final_score' => $score, 'band' => 'meets', 'status' => 'final',
                'published_at' => now(), 'finalized_at' => now(), 'appeal_deadline' => $to]);
        }

        $report = $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN))->get('/development/effectiveness?year='.$this->year)->assertOk()->assertSee('Dasar Manajemen Risiko');
        $row = $report->viewData('rows')->first();
        $this->assertSame([2, 1, 50.0, 3000000.0, 3.0, 100.0, 2, 12.5, 1, 1.5, 1, 10.0],
            [$row->participants, $row->completed, $row->completion, $row->cost_per_completed, $row->l1, $row->l1_rate, $row->l2_n, $row->l2, $row->l3_n, $row->l3, $row->l4_n, $row->l4]);
        $this->assertSame('improve', $row->indication, 'penyelesaian 50% < 70%');
        $this->assertSame('stop', TrainingEffectiveness::indication((object) ['completed' => 3, 'l1' => 2.8, 'l2' => 5, 'l2_n' => 3, 'completion' => 90]));
        $this->assertSame('continue', TrainingEffectiveness::indication((object) ['completed' => 3, 'l1' => 4.2, 'l2' => 5, 'l2_n' => 3, 'completion' => 90]));

        $this->actingAsSximo($this->rinaUser, Roles::id(Roles::EMPLOYEE))->get('/development/sessions')->assertRedirect('dashboard');
        $other = $this->makeUser($this->makeTenant('TB'), Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($other, Roles::id(Roles::TENANT_ADMIN))->get('/development/sessions/'.$session)->assertNotFound();
        $this->get('/development/effectiveness?year='.$this->year)->assertOk()->assertViewHas('participants', 0);
    }
}
