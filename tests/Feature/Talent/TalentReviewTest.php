<?php

namespace Tests\Feature\Talent;

use App\Domain\Talent\TalentReviews;
use App\Support\Security\Roles;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/** Talent gelombang 1 — asesmen potensi, talent review, Nine Box (laporan CBHRM 9; K7). */
class TalentReviewTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $company;

    private int $position;

    private int $period;

    private User $hr;

    private User $hr2;

    private User $committee;

    /** @var array<string,int> */
    private array $emp = [];

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->tenant = $this->makeTenant('TA');
        $this->company = $this->makeCompany($this->tenant, 'A1');
        $unit = $this->makeUnit($this->tenant, 'Divisi Risiko', null, $this->company);
        $family = $this->insert('job_families', ['code' => 'RSK', 'name' => 'Risk']);
        $job = $this->insert('jobs_catalog', ['code' => 'RA', 'title' => 'Risk Analyst', 'job_family_id' => $family]);
        $this->position = $this->insert('positions', ['code' => 'ANL', 'title' => 'Analis', 'company_id' => $this->company, 'unit_id' => $unit,
            'job_id' => $job, 'status' => 'active', 'headcount' => 9, 'effective_from' => '2020-01-01']);
        foreach (['ayu' => 'F', 'bima' => 'M', 'citra' => 'F', 'dodi' => 'M'] as $name => $gender) {
            $this->emp[$name] = $this->insert('employees', ['company_id' => $this->company, 'employee_number' => 'E'.Str::upper(Str::random(5)),
                'full_name' => ucfirst($name).' Talenta', 'gender' => $gender, 'employment_status' => 'pkwtt', 'join_date' => '2021-01-04', 'status' => 'active']);
            $this->insert('employee_assignments', ['employee_id' => $this->emp[$name], 'position_id' => $this->position, 'type' => 'primary', 'effective_from' => '2021-01-04']);
        }
        $this->period = $this->insert('performance_periods', ['code' => 'K25', 'name' => 'Kinerja 2025', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'status' => 'closed']);
        foreach (['ayu' => 'outstanding', 'bima' => 'meets', 'citra' => 'exceeds'] as $name => $band) {
            $this->insert('performance_ratings', ['period_id' => $this->period, 'employee_id' => $this->emp[$name], 'final_score' => 85, 'band' => $band,
                'status' => 'final', 'published_at' => now(), 'finalized_at' => now(), 'appeal_deadline' => '2026-01-31']);
        }

        $this->hr = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $this->hr2 = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $this->committee = $this->makeUser($this->tenant, Roles::id(Roles::TALENT_COMMITTEE));
        // Instrumen potensi berlaku (kriteria bawaan 40/30/30, ambang 2,50 / 3,75)
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN))->post('/talent/potential-instruments/draft');
        $this->post('/talent/potential-instruments/'.DB::table('potential_instruments')->value('id').'/publish')->assertSessionHasNoErrors();
    }

    private function insert(string $table, array $data): int
    {
        return DB::table($table)->insertGetId($data + ['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'created_at' => now()]);
    }

    private function potential(string $name, array $scores)
    {
        return $this->post('/talent/potential-assessments', ['employee_id' => $this->emp[$name], 'scores' => $scores, 'confidence' => 'high',
            'evidence' => 'Observasi panel dan wawancara karier, Oktober 2026.']);
    }

    public function test_pemetaan_kotak_k7(): void
    {
        $this->assertSame([9, 1, 3, 7, 5], [TalentReviews::box('high', 'high'), TalentReviews::box('low', 'low'), TalentReviews::box('high', 'low'),
            TalentReviews::box('low', 'high'), TalentReviews::box('medium', 'medium')]);
        $this->assertNull(TalentReviews::box(null, 'high'));
        $this->assertSame(['medium', 'high'], TalentReviews::axes(8));
        $bands = [['code' => 'a', 'min' => 90], ['code' => 'b', 'min' => 80], ['code' => 'c', 'min' => 70], ['code' => 'd', 'min' => 0]];
        $this->assertSame(['high', 'medium', 'low', 'high', 'medium', 'low'], [TalentReviews::performanceLevel('exceeds', []), TalentReviews::performanceLevel('meets', []),
            TalentReviews::performanceLevel('unsatisfactory', []), TalentReviews::performanceLevel('b', $bands), TalentReviews::performanceLevel('c', $bands), TalentReviews::performanceLevel('d', $bands)]);
    }

    public function test_review_kalibrasi_pengesahan_dan_laporan(): void
    {
        $this->potential('ayu', ['ABILITY' => 2])->assertSessionHasErrors('scores');
        $this->potential('ayu', ['ABILITY' => 5, 'ASPIRATION' => 4, 'ENGAGEMENT' => 4])->assertSessionHasNoErrors();   // 4,40 → tinggi
        $this->potential('bima', ['ABILITY' => 3, 'ASPIRATION' => 3, 'ENGAGEMENT' => 3])->assertSessionHasNoErrors();  // 3,00 → sedang
        $this->potential('dodi', ['ABILITY' => 2, 'ASPIRATION' => 2, 'ENGAGEMENT' => 2])->assertSessionHasNoErrors();  // tanpa rating
        $this->assertSame(['high', 'medium', 'low'], DB::table('potential_assessments')->orderBy('id')->pluck('level')->all());
        $this->get('/talent/potential-assessments')->assertOk()->assertSee('Ayu Talenta');
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'potential_assessments')->where('event', 'sensitive_viewed')->exists());

        // Review: peserta otomatis, usulan kotak K7
        $this->post('/talent/reviews', ['name' => 'Talent Review 2026', 'review_date' => now()->toDateString(), 'performance_period_id' => $this->period,
            'panel_user_ids' => [$this->committee->id, $this->hr2->id]])->assertSessionHasNoErrors();
        $review = (int) DB::table('talent_reviews')->value('id');
        $entries = DB::table('talent_review_entries')->where('review_id', $review)->get()->keyBy('employee_id');
        $this->assertSame([9, 5, null, null], [$entries[$this->emp['ayu']]->proposed_box, $entries[$this->emp['bima']]->proposed_box,
            $entries[$this->emp['citra']]->proposed_box, $entries[$this->emp['dodi']]->proposed_box]);
        $this->assertSame(['missing_potential', 'missing_rating'], [$entries[$this->emp['citra']]->data_status, $entries[$this->emp['dodi']]->data_status]);

        // Panel: kalibrasi wajib justifikasi; flight risk wajib alasan
        $this->actingAsSximo($this->committee, Roles::id(Roles::TALENT_COMMITTEE));
        $this->get('/talent/reviews/'.$review)->assertOk()->assertSee('Bima Talenta')->assertSee('Bintang');
        $bima = $entries[$this->emp['bima']]->id;
        $ayu = $entries[$this->emp['ayu']]->id;
        $this->put('/talent/reviews/entries/'.$bima, ['final_box' => 6])->assertSessionHasErrors('justification');
        $this->put('/talent/reviews/entries/'.$bima, ['final_box' => 6, 'justification' => 'Konsisten melampaui target di semester 2'])->assertSessionHasNoErrors();
        $this->put('/talent/reviews/entries/'.$ayu, ['final_box' => 9, 'flight_risk' => 'high'])->assertSessionHasErrors('flight_risk_reason');
        $this->put('/talent/reviews/entries/'.$ayu, ['final_box' => 9, 'flight_risk' => 'high', 'flight_risk_reason' => 'Ditawari kompetitor',
            'development_action' => 'Penugasan proyek strategis', 'next_review_date' => now()->addMonths(6)->toDateString()])->assertSessionHasNoErrors();
        $this->put('/talent/reviews/entries/'.$entries[$this->emp['citra']]->id, ['final_box' => 3])->assertSessionHasErrors('final_box');
        $this->assertSame('calibration', DB::table('talent_reviews')->where('id', $review)->value('status'));

        // Pengesahan: pembuat tidak boleh; peninjau lain boleh; setelah sah terkunci
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN))->post('/talent/reviews/'.$review.'/approve')->assertSessionHasErrors('status');
        $this->actingAsSximo($this->hr2, Roles::id(Roles::TENANT_ADMIN))->post('/talent/reviews/'.$review.'/approve')->assertSessionHasNoErrors();
        $this->actingAsSximo($this->committee, Roles::id(Roles::TALENT_COMMITTEE))->put('/talent/reviews/entries/'.$bima, ['final_box' => 5])->assertSessionHasErrors('status');

        // Laporan agregat: HR melihat angka & risiko; eksekutif (tanpa akses talent individual) melihat "< 5" tanpa nama/risiko
        $report = $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN))->get('/talent/nine-box')->assertOk();
        $report->assertViewHas('mask', false)->assertViewHas('keyHighRisk', 1)->assertSee('Risiko kehilangan');
        $this->assertSame(['key' => 2, 'core' => 0, 'attention' => 0], $report->viewData('groupCounts')->all());
        $exec = $this->makeUser($this->tenant, Roles::id(Roles::EXECUTIVE));
        DB::table('tb_groups')->where('group_id', Roles::id(Roles::EXECUTIVE))->update(['data_scope' => 'all']);
        $this->actingAsSximo($exec, Roles::id(Roles::EXECUTIVE))->get('/talent/nine-box')->assertOk()
            ->assertViewHas('mask', true)->assertSee('&lt; 5', false)->assertDontSee('Ayu Talenta')->assertDontSee('Risiko kehilangan');
        $this->get('/talent/reviews')->assertRedirect('dashboard');

        // Siklus berikutnya: Ayu turun ke kotak 8, Bima tetap 6 → pergerakan
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN))->post('/talent/reviews', ['name' => 'Talent Review lanjutan', 'review_date' => now()->addDay()->toDateString(),
            'performance_period_id' => $this->period, 'panel_user_ids' => [$this->committee->id, $this->hr2->id]])->assertSessionHasNoErrors();
        $review2 = (int) DB::table('talent_reviews')->max('id');
        $e2 = DB::table('talent_review_entries')->where('review_id', $review2)->pluck('id', 'employee_id');
        $this->actingAsSximo($this->committee, Roles::id(Roles::TALENT_COMMITTEE));
        $this->put('/talent/reviews/entries/'.$e2[$this->emp['ayu']], ['final_box' => 8, 'justification' => 'Fokus pada proyek baru'])->assertSessionHasNoErrors();
        $this->put('/talent/reviews/entries/'.$e2[$this->emp['bima']], ['final_box' => 6, 'justification' => 'Sama dengan keputusan sebelumnya'])->assertSessionHasNoErrors();
        $this->get('/talent/reviews/'.$review2)->assertOk()->assertSee('sebelumnya kotak 9');
        $this->actingAsSximo($this->hr2, Roles::id(Roles::TENANT_ADMIN))->post('/talent/reviews/'.$review2.'/approve')->assertSessionHasNoErrors();
        $this->get('/talent/nine-box?review='.$review2)->assertOk()->assertViewHas('moves', ['up' => 0, 'same' => 1, 'down' => 1]);

        // Isolasi & pegawai biasa
        $other = $this->makeUser($this->makeTenant('TB'), Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($other, Roles::id(Roles::TENANT_ADMIN))->get('/talent/reviews/'.$review)->assertNotFound();
        $this->get('/talent/nine-box')->assertOk()->assertViewHas('review', null);
        $employee = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE));
        $this->actingAsSximo($employee, Roles::id(Roles::EMPLOYEE))->get('/talent/potential-assessments')->assertRedirect('dashboard');
    }
}
