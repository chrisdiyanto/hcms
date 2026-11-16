<?php

namespace Tests\Feature\Performance;

use App\Support\Security\Roles;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PerformanceMonitoringTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $company;

    private int $period;

    private object $plan;

    /** @var array<string,int> judul => id KPI */
    private array $kpi = [];

    private User $hr;

    private User $analyst;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        Storage::fake('local');
        $this->tenant = $this->makeTenant('TA');
        $this->company = $this->makeCompany($this->tenant, 'A1');
        $unit = $this->makeUnit($this->tenant, 'Divisi Kredit', null, $this->company);
        $family = $this->insert('job_families', ['code' => 'FIN', 'name' => 'Finance']);
        $job = $this->insert('jobs_catalog', ['code' => 'AN', 'title' => 'Analyst', 'job_family_id' => $family]);
        $positions = [];
        foreach (['MGR' => 'Kepala Divisi', 'ANL' => 'Analis Kredit'] as $code => $title) {
            $positions[$code] = $this->insert('positions', ['code' => $code, 'title' => $title, 'company_id' => $this->company,
                'unit_id' => $unit, 'job_id' => $job, 'status' => 'active', 'headcount' => 1, 'effective_from' => '2020-01-01']);
        }
        DB::table('position_relationships')->insert(['tenant_id' => $this->tenant, 'position_id' => $positions['ANL'],
            'reports_to_id' => $positions['MGR'], 'type' => 'solid', 'effective_from' => '2020-01-01']);

        $this->analyst = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'rina');
        $this->manager = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'budi');
        $this->hr = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        foreach ([[$this->analyst, 'ANL', 'Rina Analis'], [$this->manager, 'MGR', 'Budi Kepala']] as [$user, $pos, $name]) {
            $emp = $this->insert('employees', ['company_id' => $this->company, 'user_id' => $user->id, 'employee_number' => 'E'.Str::upper(Str::random(5)),
                'full_name' => $name, 'employment_status' => 'pkwtt', 'join_date' => '2021-01-04', 'status' => 'active']);
            $this->insert('employee_assignments', ['employee_id' => $emp, 'position_id' => $positions[$pos], 'type' => 'primary', 'effective_from' => '2021-01-04']);
        }

        // Kebijakan default, periode dibuka, rencana Rina disetujui atasan, periode masuk monitoring
        $this->asHr()->post('/performance/policies/draft');
        $this->post('/performance/policies/'.DB::table('performance_policies')->value('id').'/publish')->assertSessionHasNoErrors();
        $this->post('/performance/periods', ['code' => 'kin-2026', 'name' => 'Kinerja 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $this->period = (int) DB::table('performance_periods')->value('id');
        $this->post('/performance/periods/'.$this->period.'/open')->assertSessionHasNoErrors();

        $this->as($this->analyst)->get('/performance/my-plans')->assertOk();
        $planId = (int) DB::table('performance_plans')->value('id');
        $this->put('/performance/my-plans/'.$planId, ['action' => 'submit', 'items' => [
            ['title' => 'Penyaluran kredit', 'kpi_type' => 'higher', 'target' => 50000000000, 'unit' => 'Rp', 'weight' => 40],
            ['title' => 'Rasio biaya', 'kpi_type' => 'range', 'range_min' => 60, 'range_max' => 65, 'unit' => '%', 'weight' => 30],
            ['title' => 'Scoring baru', 'kpi_type' => 'milestone', 'weight' => 30, 'milestones' => [
                ['name' => 'Desain', 'weight' => 40, 'due_date' => '2026-04-30'], ['name' => 'Uji coba', 'weight' => 60, 'due_date' => '2026-08-31']]],
        ]])->assertSessionHasNoErrors();
        $this->as($this->manager)->post('/workflow/tasks/'.$this->pendingTask().'/approve')->assertSessionHasNoErrors();
        $this->asHr()->post('/performance/periods/'.$this->period.'/advance')->assertSessionHasNoErrors();

        $this->plan = DB::table('performance_plans')->find($planId);
        $this->kpi = DB::table('performance_objectives')->where('plan_id', $planId)->pluck('id', 'title')->map(fn ($v) => (int) $v)->all();
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

    private function monitoring(): string
    {
        return '/performance/plans/'.$this->plan->id.'/monitoring';
    }

    public function test_realisasi_capaian_dan_bukti_privat(): void
    {
        $this->as($this->analyst)->post('/performance/kpis/'.$this->kpi['Penyaluran kredit'].'/updates', [
            'as_of' => '2026-06-30', 'realization' => 30000000000, 'note' => 'Semester 1',
            'evidences' => [UploadedFile::fake()->create('laporan-s1.pdf', 120, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $this->post('/performance/kpis/'.$this->kpi['Rasio biaya'].'/updates', ['as_of' => '2026-06-30', 'realization' => 70])->assertSessionHasNoErrors();
        // Milestone 1 tepat waktu, milestone 2 terlambat (faktor keterlambatan 0,25)
        $this->post('/performance/kpis/'.$this->kpi['Scoring baru'].'/updates', ['as_of' => '2026-09-15', 'milestones' => [
            ['done' => 1, 'done_date' => '2026-04-20'], ['done' => 1, 'done_date' => '2026-09-10']]])->assertSessionHasNoErrors();

        $achievements = DB::table('performance_updates')->orderBy('id')->pluck('achievement')->all();
        $this->assertSame(['60.00', '92.31', '85.00'], $achievements);

        // Proyeksi What = 60×0,4 + 92,31×0,3 + 85×0,3 = 77,19
        $this->get($this->monitoring())->assertOk()->assertSee('77,19%')->assertSee('laporan-s1.pdf');

        // Bukti tersimpan privat per tenant, nama acak; hanya pemilik/atasan/HR yang bisa unduh
        $evidence = DB::table('performance_evidences')->first();
        $this->assertStringStartsWith('tenants/'.$this->tenant.'/performance/'.$this->plan->id.'/', $evidence->path);
        $this->assertStringNotContainsString('laporan', $evidence->path);
        Storage::disk('local')->assertExists($evidence->path);
        $this->get('/performance/evidences/'.$evidence->id)->assertOk()->assertDownload('laporan-s1.pdf');
        $this->as($this->manager)->get('/performance/evidences/'.$evidence->id)->assertOk();
        $this->asHr()->get('/performance/evidences/'.$evidence->id)->assertOk();
        $other = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE));
        $this->as($other)->get('/performance/evidences/'.$evidence->id)->assertNotFound();
        $this->get($this->monitoring())->assertNotFound();

        // Berkas berbahaya ditolak; atasan tidak boleh mencatat realisasi bawahan
        $this->as($this->analyst)->post('/performance/kpis/'.$this->kpi['Rasio biaya'].'/updates', ['as_of' => '2026-07-31', 'realization' => 63,
            'evidences' => [UploadedFile::fake()->create('run.exe', 10, 'application/octet-stream')]])->assertSessionHasErrors('evidences.0');
        $this->post('/performance/kpis/'.$this->kpi['Rasio biaya'].'/updates', ['as_of' => '2027-01-15', 'realization' => 63])->assertSessionHasErrors('as_of');
        $this->as($this->manager)->post('/performance/kpis/'.$this->kpi['Rasio biaya'].'/updates', ['as_of' => '2026-07-31', 'realization' => 63])->assertForbidden();
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'performance_evidences')->where('event', 'sensitive_viewed')->exists());
    }

    public function test_checkin_mid_year_wajib_sebelum_tahap_penilaian(): void
    {
        $this->asHr()->post('/performance/periods/'.$this->period.'/advance')->assertSessionHasErrors('period');

        $this->as($this->analyst)->post('/performance/plans/'.$this->plan->id.'/checkins', ['type' => 'midyear', 'checkin_date' => '2026-07-01'])
            ->assertSessionHasErrors('employee_notes');
        $this->post('/performance/plans/'.$this->plan->id.'/checkins', ['type' => 'midyear', 'checkin_date' => '2026-07-01',
            'employee_notes' => 'Penyaluran 60% dari target, butuh dukungan tim pemasaran'])->assertSessionHasNoErrors();
        $this->post('/performance/plans/'.$this->plan->id.'/checkins', ['type' => 'midyear', 'checkin_date' => '2026-07-02', 'employee_notes' => 'lagi'])
            ->assertSessionHasErrors('checkin');
        $this->assertTrue(DB::table('tb_notification')->where('userid', $this->manager->id)->where('title', 'Mid-year review')->exists());

        $checkin = (int) DB::table('performance_checkins')->value('id');
        $this->post('/performance/checkins/'.$checkin.'/complete', ['manager_notes' => 'x'])->assertForbidden();
        $this->as($this->manager)->get('/performance/team')->assertOk()->assertSee('Rina Analis')->assertSee('perlu tanggapan Anda');
        $this->post('/performance/checkins/'.$checkin.'/complete', ['manager_notes' => 'Fokus ke segmen UMKM produktif'])->assertSessionHasNoErrors();
        $this->assertSame('completed', DB::table('performance_checkins')->where('id', $checkin)->value('status'));

        $this->asHr()->post('/performance/periods/'.$this->period.'/advance')->assertSessionHasNoErrors();
        $this->assertSame('assessment', DB::table('performance_periods')->where('id', $this->period)->value('status'));

        // Setelah monitoring berakhir realisasi tidak bisa dicatat lagi
        $this->as($this->analyst)->post('/performance/kpis/'.$this->kpi['Rasio biaya'].'/updates', ['as_of' => '2026-10-31', 'realization' => 62])
            ->assertSessionHasErrors('update');
        $this->as($this->analyst)->get('/performance/team')->assertOk()->assertSee('Tidak ada bawahan langsung');
    }

    public function test_change_request_menyimpan_versi_lama_dan_menghitung_ulang(): void
    {
        $this->as($this->analyst)->post('/performance/kpis/'.$this->kpi['Penyaluran kredit'].'/updates', ['as_of' => '2026-06-30', 'realization' => 30000000000]);
        $this->post('/performance/kpis/'.$this->kpi['Scoring baru'].'/updates', ['as_of' => '2026-05-01', 'milestones' => [['done' => 1, 'done_date' => '2026-04-20'], ['done' => 0]]]);

        $current = [
            ['id' => $this->kpi['Penyaluran kredit'], 'title' => 'Penyaluran kredit', 'kpi_type' => 'higher', 'target' => 50000000000, 'unit' => 'Rp', 'weight' => 40],
            ['id' => $this->kpi['Rasio biaya'], 'title' => 'Rasio biaya', 'kpi_type' => 'range', 'range_min' => 60, 'range_max' => 65, 'unit' => '%', 'weight' => 30],
            ['id' => $this->kpi['Scoring baru'], 'title' => 'Scoring baru', 'kpi_type' => 'milestone', 'weight' => 30, 'milestones' => [
                ['name' => 'Desain', 'weight' => 40, 'due_date' => '2026-04-30'], ['name' => 'Uji coba', 'weight' => 60, 'due_date' => '2026-08-31']]],
        ];
        $this->post('/performance/plans/'.$this->plan->id.'/change-request', ['reason' => 'Tidak ada yang berubah', 'items' => $current])->assertSessionHasErrors('items');

        // Target penyaluran turun, KPI scoring dihapus, KPI kualitatif baru ditambahkan
        $proposal = [$current[0], $current[1], ['title' => 'Kualitas layanan', 'kpi_type' => 'qualitative', 'weight' => 30, 'rubric' => ['Buruk', 'Kurang', 'Cukup', 'Baik', 'Istimewa']]];
        $proposal[0]['target'] = 40000000000;
        $this->post('/performance/plans/'.$this->plan->id.'/change-request', ['reason' => 'Revisi RKAP semester 2', 'items' => $proposal])->assertSessionHasNoErrors();
        $this->post('/performance/plans/'.$this->plan->id.'/change-request', ['reason' => 'Ajukan lagi sekali', 'items' => $proposal])->assertSessionHasErrors('items');

        $task = $this->pendingTask();
        $this->as($this->manager)->get('/workflow/tasks/'.$task)->assertOk()->assertSee('(dihapus)')->assertSee('(KPI baru)');
        $this->post('/workflow/tasks/'.$task.'/approve')->assertSessionHasNoErrors();

        $plan = DB::table('performance_plans')->find($this->plan->id);
        $this->assertSame(2, (int) $plan->kpi_version);
        $version = DB::table('performance_plan_versions')->where('plan_id', $plan->id)->first();
        $this->assertSame(1, (int) $version->version);
        $this->assertCount(3, json_decode($version->objectives, true));

        // KPI lama dipensiunkan (realisasinya tetap ada); capaian dihitung ulang dengan target baru 30/40 = 75%
        $this->assertNotNull(DB::table('performance_objectives')->where('id', $this->kpi['Scoring baru'])->value('retired_at'));
        $this->assertSame(1, DB::table('performance_updates')->where('objective_id', $this->kpi['Scoring baru'])->count());
        $this->assertSame('75.00', DB::table('performance_updates')->where('objective_id', $this->kpi['Penyaluran kredit'])->value('achievement'));
        $this->assertSame(['Penyaluran kredit', 'Rasio biaya', 'Kualitas layanan'],
            DB::table('performance_objectives')->where('plan_id', $plan->id)->whereNull('retired_at')->orderBy('sort_order')->pluck('title')->all());
        $this->as($this->analyst)->get($this->monitoring())->assertOk()->assertSee('Kualitas layanan')->assertDontSee('Scoring baru</h6>', false)
            ->assertSee('v1 → v2');
    }

    public function test_isolasi_tenant(): void
    {
        $other = $this->makeTenant('TB');
        $admin = $this->makeUser($other, Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($admin, Roles::id(Roles::TENANT_ADMIN))->get($this->monitoring())->assertNotFound();
        $this->post('/performance/kpis/'.$this->kpi['Rasio biaya'].'/updates', ['as_of' => '2026-06-30', 'realization' => 62])->assertNotFound();
        $this->get('/performance/team')->assertOk()->assertDontSee('Rina Analis');
    }
}
