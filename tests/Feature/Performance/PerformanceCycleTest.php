<?php

namespace Tests\Feature\Performance;

use App\Domain\Performance\Services\PerformancePlans;
use App\Support\Security\Roles;
use App\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/** Gerbang keluar Fase 3: simulasi satu siklus penuh dengan data uji. */
class PerformanceCycleTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $company;

    private int $period;

    private int $planId;

    /** @var array<string,int> */
    private array $employees = [];

    private User $hr;

    private User $analyst;

    private User $manager;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->tenant = $this->makeTenant('TA');
        $this->company = $this->makeCompany($this->tenant, 'A1');
        $unit = $this->makeUnit($this->tenant, 'Divisi Kredit', null, $this->company);
        $family = $this->insert('job_families', ['code' => 'FIN', 'name' => 'Finance']);
        $job = $this->insert('jobs_catalog', ['code' => 'AN', 'title' => 'Analyst', 'job_family_id' => $family]);
        $pos = [];
        foreach (['DIR' => 'Direktur', 'MGR' => 'Kepala Divisi', 'ANL' => 'Analis Kredit'] as $code => $title) {
            $pos[$code] = $this->insert('positions', ['code' => $code, 'title' => $title, 'company_id' => $this->company,
                'unit_id' => $unit, 'job_id' => $job, 'status' => 'active', 'headcount' => 1, 'effective_from' => '2020-01-01']);
        }
        foreach ([['ANL', 'MGR'], ['MGR', 'DIR']] as [$child, $parent]) {
            DB::table('position_relationships')->insert(['tenant_id' => $this->tenant, 'position_id' => $pos[$child],
                'reports_to_id' => $pos[$parent], 'type' => 'solid', 'effective_from' => '2020-01-01']);
        }
        $this->analyst = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'rina');
        $this->manager = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'budi');
        $this->director = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'sari');
        $this->hr = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        foreach ([[$this->analyst, 'ANL', 'Rina Analis'], [$this->manager, 'MGR', 'Budi Kepala'], [$this->director, 'DIR', 'Sari Direktur']] as [$user, $code, $name]) {
            $emp = $this->insert('employees', ['company_id' => $this->company, 'user_id' => $user->id, 'employee_number' => 'E'.Str::upper(Str::random(5)),
                'full_name' => $name, 'employment_status' => 'pkwtt', 'join_date' => '2021-01-04', 'status' => 'active']);
            $this->insert('employee_assignments', ['employee_id' => $emp, 'position_id' => $pos[$code], 'type' => 'primary', 'effective_from' => '2021-01-04']);
            $this->employees[$code] = $emp;
        }

        // Perencanaan → monitoring dengan realisasi & mid-year
        $this->asHr()->post('/performance/policies/draft');
        $this->post('/performance/policies/'.DB::table('performance_policies')->value('id').'/publish')->assertSessionHasNoErrors();
        $this->post('/performance/periods', ['code' => 'kin-2026', 'name' => 'Kinerja 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $this->period = (int) DB::table('performance_periods')->value('id');
        $this->post('/performance/periods/'.$this->period.'/open');

        $this->as($this->analyst)->get('/performance/my-plans');
        $this->planId = (int) DB::table('performance_plans')->value('id');
        $this->put('/performance/my-plans/'.$this->planId, ['action' => 'submit', 'items' => [
            ['title' => 'Proposal dianalisis', 'kpi_type' => 'higher', 'target' => 100, 'weight' => 40],
            ['title' => 'Rasio biaya', 'kpi_type' => 'range', 'range_min' => 60, 'range_max' => 65, 'weight' => 30],
            ['title' => 'Kualitas analisis', 'kpi_type' => 'qualitative', 'weight' => 30, 'rubric' => ['a', 'b', 'c', 'd', 'e']],
        ]])->assertSessionHasNoErrors();
        $this->as($this->manager)->post('/workflow/tasks/'.$this->pendingTask().'/approve')->assertSessionHasNoErrors();
        $this->asHr()->post('/performance/periods/'.$this->period.'/advance')->assertSessionHasNoErrors();

        $kpi = DB::table('performance_objectives')->where('plan_id', $this->planId)->pluck('id', 'title');
        $this->as($this->analyst)->post('/performance/kpis/'.$kpi['Proposal dianalisis'].'/updates', ['as_of' => '2026-10-31', 'realization' => 110]);
        $this->post('/performance/kpis/'.$kpi['Rasio biaya'].'/updates', ['as_of' => '2026-10-31', 'realization' => 62]);
        $this->post('/performance/kpis/'.$kpi['Kualitas analisis'].'/updates', ['as_of' => '2026-10-31', 'score' => 4]);
        $this->post('/performance/plans/'.$this->planId.'/checkins', ['type' => 'midyear', 'checkin_date' => '2026-07-01', 'employee_notes' => 'Sesuai rencana']);
        $this->as($this->manager)->post('/performance/checkins/'.DB::table('performance_checkins')->value('id').'/complete', ['manager_notes' => 'Lanjutkan']);
    }

    private function insert(string $table, array $data): int
    {
        return DB::table($table)->insertGetId($data + ['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'created_at' => now()]);
    }

    private function asHr(): static
    {
        return $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN));
    }

    private function as(User $user): static
    {
        return $this->actingAsSximo($user, Roles::id(Roles::EMPLOYEE));
    }

    private function pendingTask(): int
    {
        return (int) DB::table('approval_tasks')->where('status', 'pending')->orderByDesc('id')->value('id');
    }

    private function how(int ...$scores): array
    {
        return array_combine(['INT', 'KOL', 'PEL', 'INO', 'AKT'], $scores);
    }

    private function assessment(): object
    {
        return DB::table('performance_assessments')->where('plan_id', $this->planId)->first();
    }

    public function test_siklus_penuh_penilaian_kalibrasi_pengesahan_keberatan(): void
    {
        // ---- Tahap penilaian dibuka: form dibuat untuk rencana yang disetujui
        $this->asHr()->post('/performance/periods/'.$this->period.'/advance')->assertSessionHasNoErrors();
        $this->assertSame('self', $this->assessment()->status);
        $this->assertTrue(DB::table('tb_notification')->where('userid', $this->analyst->id)->where('title', 'Penilaian kinerja dibuka')->exists());
        $this->post('/performance/periods/'.$this->period.'/advance')->assertSessionHasErrors('period'); // penilaian belum selesai

        // ---- Penilaian diri
        $url = '/performance/assessments/'.$this->planId;
        $this->as($this->analyst)->post($url.'/self', ['action' => 'submit', 'how' => ['INT' => 5]])->assertSessionHasErrors(['how.KOL', 'contribution']);
        $this->post($url.'/self', ['action' => 'submit', 'how' => $this->how(5, 5, 5, 5, 5), 'contribution' => 5, 'summary' => 'Target terlampaui'])->assertSessionHasNoErrors();
        $this->assertSame('manager', $this->assessment()->status);
        $this->assertSame($this->manager->id, (int) $this->assessment()->manager_user_id);
        $this->post($url.'/self', ['action' => 'draft', 'how' => $this->how(1, 1, 1, 1, 1)])->assertSessionHasErrors('assessment');

        // ---- Penilaian atasan (melihat skor diri; pegawai belum melihat skor atasan)
        $this->as($this->manager)->get('/performance/assessments')->assertOk()->assertSee('Isi penilaian');
        $this->post($url.'/manager', ['action' => 'submit', 'how' => $this->how(4, 4, 3, 4, 5), 'contribution' => 3])->assertSessionHasErrors('summary');
        $this->post($url.'/manager', ['action' => 'submit', 'how' => $this->how(4, 4, 3, 4, 5), 'contribution' => 3, 'summary' => 'Kinerja sangat baik'])
            ->assertSessionHasNoErrors();
        $a = $this->assessment();
        // What = 110×0,4 + 100×0,3 + 85×0,3 = 99,5; How = rata 4 → 85; Kontribusi 3 → 75; akhir = 59,7 + 25,5 + 7,5 = 92,7
        $this->assertSame(['99.50', '85.00', '75.00', '92.70', 'outstanding', 'review'], [$a->what_score, $a->how_score, $a->contribution_score, $a->final_score, $a->band, $a->status]);
        $this->assertSame($this->director->id, (int) $a->reviewer_user_id);
        $this->as($this->analyst)->get($url)->assertOk()->assertDontSee('92,7');

        // ---- Review atasan dari atasan: dikembalikan (catatan wajib), diperbaiki, disetujui
        $this->as($this->director)->post($url.'/review', ['decision' => 'return'])->assertSessionHasErrors('review_note');
        $this->post($url.'/review', ['decision' => 'return', 'review_note' => 'Kontribusi terlalu tinggi'])->assertSessionHasNoErrors();
        $this->assertSame('manager', $this->assessment()->status);
        $this->as($this->manager)->post($url.'/manager', ['action' => 'submit', 'how' => $this->how(4, 4, 3, 4, 5), 'contribution' => 2, 'summary' => 'Kinerja sangat baik'])
            ->assertSessionHasNoErrors();
        $this->as($this->director)->post($url.'/review', ['decision' => 'agree'])->assertSessionHasNoErrors();
        $a = $this->assessment();
        $this->assertSame(['completed', '91.70'], [$a->status, $a->final_score]);
        $this->as($this->analyst)->get($url)->assertOk()->assertSee('91,7');

        // ---- Kalibrasi: pindah band wajib beralasan, skor disesuaikan ke batas atas band tujuan
        $this->asHr()->post('/performance/periods/'.$this->period.'/advance')->assertSessionHasNoErrors();
        $this->post('/performance/periods/'.$this->period.'/calibration', ['name' => 'Kalibrasi Divisi Kredit'])->assertRedirect();
        $session = (int) DB::table('calibration_sessions')->value('id');
        $this->get('/performance/calibration/sessions/'.$session)->assertOk()->assertSee('Rina Analis');
        $move = fn (string $reason) => $this->postJson('/performance/calibration/sessions/'.$session.'/move',
            ['assessment_id' => $a->id, 'to_band' => 'exceeds', 'reason' => $reason]);
        $move('kurang')->assertStatus(422);
        $move('Distribusi divisi melebihi kuota Outstanding')->assertOk()
            ->assertJsonPath('distribution.exceeds.count', 1)->assertJsonPath('distribution.outstanding.count', 0)->assertJsonPath('score', 89.99);
        $this->assertSame(['89.99', 'exceeds'], [$this->assessment()->calibrated_score, $this->assessment()->calibrated_band]);
        $this->assertSame(1, DB::table('calibration_adjustments')->where('from_band', 'outstanding')->where('to_band', 'exceeds')->count());

        // ---- Pengumuman rating sementara: sesi kalibrasi harus ditutup; belum ada snapshot
        $this->post('/performance/periods/'.$this->period.'/publish')->assertSessionHasErrors('period');
        $this->post('/performance/calibration/sessions/'.$session.'/close')->assertSessionHasNoErrors();
        $this->post('/performance/periods/'.$this->period.'/publish')->assertSessionHasNoErrors();
        $this->assertSame('appeal', DB::table('performance_periods')->where('id', $this->period)->value('status'));
        $rating = DB::table('performance_ratings')->where('employee_id', $this->employees['ANL'])->first();
        $this->assertSame(['89.99', 'exceeds', 'Exceeds Expectation', 'provisional', now()->addDays(14)->toDateString()],
            [$rating->final_score, $rating->band, $rating->band_label, $rating->status, $rating->appeal_deadline]);
        $this->assertSame(0, DB::table('performance_snapshots')->count());
        $this->as($this->analyst)->get('/performance/my-ratings')->assertOk()->assertSee('Exceeds Expectation')->assertSee('sementara')->assertSee('Ajukan keberatan');

        // ---- Keberatan sebelum pengesahan: diputus atasan dari atasan, bukan penilai
        $this->post('/performance/ratings/'.$rating->id.'/appeal', ['reason' => 'tidak adil'])->assertSessionHasErrors('reason');
        $this->post('/performance/ratings/'.$rating->id.'/appeal', ['reason' => 'Kalibrasi menurunkan band padahal seluruh KPI terlampaui dan ada bukti penghargaan klien.'])
            ->assertSessionHasNoErrors();
        $appeal = DB::table('performance_appeals')->first();
        $this->assertSame($this->director->id, (int) $appeal->decider_user_id);
        $this->post('/performance/ratings/'.$rating->id.'/appeal', ['reason' => str_repeat('Ajukan lagi untuk periode yang sama. ', 2)])->assertSessionHasErrors('reason');
        $this->as($this->manager)->post('/performance/appeals/'.$appeal->id.'/decide', ['decision' => 'adjusted', 'new_score' => 95, 'decision_note' => 'Saya setuju dengan pegawai'])
            ->assertForbidden();

        // Pengesahan final ditolak selama jendela keberatan terbuka / masih ada keberatan
        $this->asHr()->post('/performance/periods/'.$this->period.'/finalize')->assertSessionHasErrors('period');
        $this->travel(15)->days();
        $this->post('/performance/periods/'.$this->period.'/finalize')->assertSessionHasErrors('period');

        $this->as($this->director)->get('/performance/appeals')->assertOk()->assertSee('Simpan keputusan');
        $this->post('/performance/appeals/'.$appeal->id.'/decide', ['decision' => 'adjusted', 'new_score' => 91, 'decision_note' => 'Bukti penghargaan klien valid'])
            ->assertSessionHasNoErrors();
        $ratings = DB::table('performance_ratings')->where('employee_id', $this->employees['ANL'])->orderBy('revision')->get();
        $this->assertSame([[1, 0, 'exceeds'], [2, 1, 'outstanding']], $ratings->map(fn ($r) => [(int) $r->revision, (int) $r->is_current, $r->band])->all());
        $this->assertStringContainsString('Keberatan #'.$appeal->id, $ratings[1]->reason);
        $this->asHr()->get('/performance/dashboard')->assertRedirect('/executive/analytic/performance');
        $this->asHr()->get('/executive/analytic/performance')->assertOk()->assertSee('Rating sementara');

        // ---- Pengesahan final: rating terkini final + snapshot immutable, periode ditutup
        $this->post('/performance/periods/'.$this->period.'/finalize')->assertSessionHasNoErrors();
        $this->assertSame('closed', DB::table('performance_periods')->where('id', $this->period)->value('status'));
        $final = DB::table('performance_ratings')->where('employee_id', $this->employees['ANL'])->where('is_current', true)->first();
        $this->assertSame(['final', 2], [$final->status, (int) $final->revision]);
        $this->assertSame(1, DB::table('performance_snapshots')->count());
        $snapshot = DB::table('performance_snapshots')->where('rating_id', $final->id)->first();
        $this->assertSame(hash('sha256', $snapshot->payload), $snapshot->checksum);
        $payload = json_decode($snapshot->payload, true);
        $this->assertSame(1, $payload['policy']['_version']);
        $this->assertCount(1, $payload['calibrations']);
        $this->assertSame('adjusted', $payload['appeals'][0]['status']);
        try {
            DB::table('performance_snapshots')->where('id', $snapshot->id)->update(['checksum' => 'x']);
            $this->fail('Snapshot seharusnya tidak dapat diubah');
        } catch (QueryException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }
        $this->as($this->analyst)->post('/performance/ratings/'.$final->id.'/appeal', ['reason' => str_repeat('Keberatan setelah final tidak boleh. ', 2)])
            ->assertSessionHasErrors('reason');

        // ---- Dasbor
        $this->asHr()->get('/performance/dashboard')->assertRedirect('/executive/analytic/performance');
        $this->asHr()->get('/executive/analytic/performance')->assertOk()->assertSee('Rating final yang disahkan')->assertSee('data-chart', false);
        $this->get('/performance/periods/'.$this->period.'/ratings')->assertOk()->assertSee('Outstanding')->assertSee('final');

        // ---- Ekspor rating: alasan wajib, watermark, tercatat; pegawai tanpa izin ekspor ditolak
        $export = '/performance/periods/'.$this->period.'/ratings/export';
        $this->get($export)->assertOk()->assertSee('Alasan ekspor data');
        $csv = $this->get($export.'?export_reason='.urlencode('Rekap rating untuk komite remunerasi'))->assertOk()->streamedContent();
        $this->assertStringContainsString('RAHASIA', $csv);
        $this->assertStringContainsString('Rina Analis', $csv);
        $this->assertStringContainsString('Final', $csv);
        $this->assertSame(1, DB::table('data_export_logs')->where('module', 'performance_ratings')->where('row_count', 1)->count());
        $this->as($this->analyst)->get($export.'?export_reason='.urlencode('Ingin melihat rating semua orang'))->assertForbidden();
    }

    public function test_mutasi_tengah_periode_dihitung_prorata_dan_masa_kurang_not_rated(): void
    {
        $period = (object) DB::table('performance_periods')->find($this->period);
        $mgr = (int) DB::table('positions')->where('code', 'MGR')->value('id');
        $pos2 = $this->insert('positions', ['code' => 'ANL2', 'title' => 'Analis Kredit 2', 'company_id' => $this->company,
            'unit_id' => DB::table('positions')->where('id', $mgr)->value('unit_id'), 'job_id' => DB::table('positions')->where('id', $mgr)->value('job_id'),
            'status' => 'active', 'headcount' => 1, 'effective_from' => '2020-01-01']);
        DB::table('position_relationships')->insert(['tenant_id' => $this->tenant, 'position_id' => $pos2, 'reports_to_id' => $mgr, 'type' => 'solid', 'effective_from' => '2020-01-01']);
        $anl = (int) DB::table('positions')->where('code', 'ANL')->value('id');

        // Dodi: ANL2 s.d. 30 Juni (181 hari) lalu mutasi ke ANL (184 hari). Eko: masuk 1 November (61 hari < 90).
        $people = [];
        foreach (['Dodi Mutasi' => [[$pos2, '2021-01-04', '2026-06-30'], [$anl, '2026-07-01', null]], 'Eko Baru' => [[$pos2, '2026-11-01', null]]] as $name => $assignments) {
            $user = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE));
            $emp = $this->insert('employees', ['company_id' => $this->company, 'user_id' => $user->id, 'employee_number' => 'E'.Str::upper(Str::random(5)),
                'full_name' => $name, 'employment_status' => 'pkwtt', 'join_date' => $assignments[0][1], 'status' => 'active']);
            foreach ($assignments as [$position, $from, $to]) {
                $this->insert('employee_assignments', ['employee_id' => $emp, 'position_id' => $position, 'type' => 'primary', 'effective_from' => $from, 'effective_to' => $to]);
            }
            $people[$name] = $emp;
        }

        // Rencana per penugasan, dipotong ke batas periode
        $segments = $this->asTenantPlans($people['Dodi Mutasi'], $period);
        $this->assertSame([['2026-01-01', '2026-06-30', 181], ['2026-07-01', '2026-12-31', 184]], $segments);

        // Rencana disetujui + mid-year selesai (langsung di data), lalu penilaian dibuka & diselesaikan
        $scores = ['Dodi Mutasi' => [80.0, 90.0], 'Eko Baru' => [95.0]];
        foreach ($people as $name => $emp) {
            foreach (DB::table('employee_assignments')->where('employee_id', $emp)->orderBy('effective_from')->get() as $a) {
                $plan = $this->insert('performance_plans', ['period_id' => $this->period, 'employee_id' => $emp, 'assignment_id' => $a->id, 'position_id' => $a->position_id,
                    'start_date' => max($a->effective_from, '2026-01-01'), 'end_date' => min($a->effective_to ?? '2026-12-31', '2026-12-31'), 'status' => 'approved']);
                DB::table('performance_checkins')->insert(['tenant_id' => $this->tenant, 'plan_id' => $plan, 'type' => 'midyear', 'checkin_date' => '2026-07-01',
                    'status' => 'completed', 'created_by' => $this->hr->id, 'created_at' => now()]);
            }
        }
        $this->asHr()->post('/performance/periods/'.$this->period.'/advance')->assertSessionHasNoErrors();
        $this->assertSame(4, DB::table('performance_assessments')->count());

        DB::table('performance_assessments')->where('plan_id', $this->planId)->update(['status' => 'completed', 'final_score' => 75, 'final_raw' => 75, 'band' => 'meets']);
        foreach ($people as $name => $emp) {
            $plans = DB::table('performance_plans')->where('employee_id', $emp)->orderBy('start_date')->pluck('id');
            foreach ($plans as $i => $plan) {
                $s = $scores[$name][$i];
                DB::table('performance_assessments')->where('plan_id', $plan)->update(['status' => 'completed', 'final_score' => $s, 'final_raw' => $s, 'band' => $s >= 90 ? 'outstanding' : 'exceeds']);
            }
        }
        $this->post('/performance/periods/'.$this->period.'/advance')->assertSessionHasNoErrors();
        $this->post('/performance/periods/'.$this->period.'/publish')->assertSessionHasNoErrors();

        // Dodi: (80×181 + 90×184) / 365 = 85,04 → Exceeds (band dari skor prorata, bukan dari salah satu penugasan)
        $dodi = DB::table('performance_ratings')->where('employee_id', $people['Dodi Mutasi'])->first();
        $this->assertSame(['85.04', 'exceeds', 1], [$dodi->final_score, $dodi->band, (int) $dodi->rated]);
        // Eko: 61 hari < 90 → not rated
        $eko = DB::table('performance_ratings')->where('employee_id', $people['Eko Baru'])->first();
        $this->assertSame([0, null, 'Not rated'], [(int) $eko->rated, $eko->final_score, $eko->band_label]);
    }

    /** @return array<int, array{0:string,1:string,2:int}> */
    private function asTenantPlans(int $employeeId, object $period): array
    {
        return $this->asTenant($this->tenant, fn () => app(PerformancePlans::class)->segments($employeeId, $period)
            ->map(fn ($s) => [$s->start, $s->end, (int) $s->days])->values()->all());
    }

    public function test_pip_untuk_rating_rendah_dan_isolasi(): void
    {
        // Rating rendah untuk Budi (langsung, tanpa siklus) → kandidat PIP; penanggung jawab = atasannya (Sari)
        $ratingId = $this->insert('performance_ratings', ['period_id' => $this->period, 'employee_id' => $this->employees['MGR'], 'revision' => 1,
            'is_current' => true, 'rated' => true, 'final_score' => 62, 'band' => 'below', 'band_label' => 'Below Expectation',
            'published_at' => now(), 'appeal_deadline' => now()->addDays(14)->toDateString()]);
        $this->asHr()->get('/performance/pips')->assertOk()->assertSee('Budi Kepala — Below Expectation');
        $this->post('/performance/pips', ['rating_id' => $ratingId, 'start_date' => '2026-11-20', 'end_date' => '2027-02-20', 'objectives' => 'singkat'])
            ->assertSessionHasErrors('objectives');
        $this->post('/performance/pips', ['rating_id' => $ratingId, 'start_date' => '2026-11-20', 'end_date' => '2027-02-20',
            'objectives' => 'Menyelesaikan review portofolio tepat waktu setiap bulan', 'support' => 'Coaching mingguan'])->assertSessionHasNoErrors();
        $pip = DB::table('performance_improvement_plans')->first();
        $this->assertSame($this->director->id, (int) $pip->owner_user_id);
        $this->post('/performance/pips', ['rating_id' => $ratingId, 'start_date' => '2026-11-20', 'end_date' => '2027-02-20',
            'objectives' => 'Duplikat PIP untuk pegawai yang sama'])->assertSessionHasErrors('rating_id');

        // Pegawai melihat PIP-nya; penanggung jawab memperpanjang; orang lain tidak bisa mengubah
        $this->as($this->manager)->get('/performance/my-ratings')->assertOk()->assertSee('Coaching mingguan');
        $this->post('/performance/pips/'.$pip->id.'/close', ['status' => 'completed', 'outcome_note' => 'Menilai diri sendiri'])->assertForbidden();
        $this->as($this->director)->post('/performance/pips/'.$pip->id.'/close', ['status' => 'extended', 'outcome_note' => 'Perlu satu bulan lagi'])
            ->assertSessionHasErrors('end_date');
        $this->post('/performance/pips/'.$pip->id.'/close', ['status' => 'extended', 'outcome_note' => 'Perlu satu bulan lagi', 'end_date' => '2027-03-20'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['extended', '2027-03-20'], [DB::table('performance_improvement_plans')->value('status'), DB::table('performance_improvement_plans')->value('end_date')]);

        // Tenant lain
        $other = $this->makeTenant('TB');
        $admin = $this->makeUser($other, Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($admin, Roles::id(Roles::TENANT_ADMIN))->get('/performance/pips')->assertOk()->assertDontSee('Budi Kepala');
        $this->post('/performance/pips/'.$pip->id.'/close', ['status' => 'cancelled', 'outcome_note' => 'Dari tenant lain'])->assertNotFound();
        $this->get('/performance/periods/'.$this->period.'/ratings')->assertNotFound();
    }
}
