<?php

namespace Tests\Feature\Performance;

use App\Domain\Performance\Services\PerformancePolicy;
use App\Support\Security\Roles;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PerformancePlanningTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $company;

    private int $unit;

    /** @var array<string,int> */
    private array $positions = [];

    private User $hr;

    private User $analyst;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->tenant = $this->makeTenant('TA');
        $this->company = $this->makeCompany($this->tenant, 'A1');
        $this->unit = $this->makeUnit($this->tenant, 'Divisi Kredit', null, $this->company);

        $family = $this->insert('job_families', ['code' => 'FIN', 'name' => 'Finance']);
        $job = $this->insert('jobs_catalog', ['code' => 'AN', 'title' => 'Analyst', 'job_family_id' => $family]);
        foreach (['MGR' => 'Kepala Divisi', 'ANL' => 'Analis Kredit'] as $code => $title) {
            $this->positions[$code] = $this->insert('positions', ['code' => $code, 'title' => $title, 'company_id' => $this->company,
                'unit_id' => $this->unit, 'job_id' => $job, 'status' => 'active', 'headcount' => 1, 'effective_from' => '2020-01-01']);
        }
        DB::table('position_relationships')->insert(['tenant_id' => $this->tenant, 'position_id' => $this->positions['ANL'],
            'reports_to_id' => $this->positions['MGR'], 'type' => 'solid', 'effective_from' => '2020-01-01']);

        $this->analyst = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'rina');
        $this->manager = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'budi');
        $this->employee($this->analyst, 'ANL', 'Rina Analis');
        $this->employee($this->manager, 'MGR', 'Budi Kepala');

        $this->hr = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $this->asHr();
    }

    private function insert(string $table, array $data): int
    {
        return DB::table($table)->insertGetId($data + ['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'created_at' => now()]);
    }

    private function employee(User $user, string $position, string $name): int
    {
        $id = $this->insert('employees', ['company_id' => $this->company, 'user_id' => $user->id, 'employee_number' => 'E'.Str::upper(Str::random(5)),
            'full_name' => $name, 'employment_status' => 'pkwtt', 'join_date' => '2021-01-04', 'status' => 'active']);
        $this->insert('employee_assignments', ['employee_id' => $id, 'position_id' => $this->positions[$position], 'type' => 'primary', 'effective_from' => '2021-01-04']);

        return $id;
    }

    private function asHr(): static
    {
        return $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN));
    }

    private function as(User $user): static
    {
        return $this->actingAsSximo($user, Roles::id(Roles::EMPLOYEE));
    }

    private function publishDefaultPolicy(): int
    {
        $this->asHr()->post('/performance/policies/draft')->assertSessionHasNoErrors();
        $id = (int) DB::table('performance_policies')->where('status', 'draft')->value('id');
        $this->post('/performance/policies/'.$id.'/publish')->assertSessionHasNoErrors();

        return $id;
    }

    private function openPeriod(): int
    {
        $this->asHr()->post('/performance/periods', ['code' => 'kin-2026', 'name' => 'Kinerja 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'])
            ->assertSessionHasNoErrors();
        $period = (int) DB::table('performance_periods')->value('id');
        $this->post('/performance/periods/'.$period.'/open')->assertSessionHasNoErrors();

        return $period;
    }

    private function planOf(User $user): object
    {
        $this->as($user)->get('/performance/my-plans')->assertOk();

        return DB::table('performance_plans')->join('employees', 'employees.id', '=', 'performance_plans.employee_id')
            ->where('employees.user_id', $user->id)->select('performance_plans.*')->first();
    }

    private function kpis(array $weights = [40, 30, 30]): array
    {
        return [
            ['title' => 'Penyaluran kredit UMKM', 'kpi_type' => 'higher', 'target' => 50000000000, 'unit' => 'Rp', 'weight' => $weights[0]],
            ['title' => 'Rasio biaya operasional', 'kpi_type' => 'range', 'range_min' => 60, 'range_max' => 65, 'unit' => '%', 'weight' => $weights[1]],
            ['title' => 'Implementasi scoring baru', 'kpi_type' => 'milestone', 'weight' => $weights[2],
                'milestones' => [['name' => 'Desain', 'weight' => 40], ['name' => 'Uji coba', 'weight' => 60]]],
        ];
    }

    private function task(int $planId): object
    {
        $instance = DB::table('performance_plans')->where('id', $planId)->value('workflow_instance_id');

        return DB::table('approval_tasks')->where('instance_id', $instance)->where('status', 'pending')->first();
    }

    public function test_kebijakan_berversi_dan_periode_mengunci_kebijakan(): void
    {
        // Membuka periode tanpa kebijakan berlaku ditolak
        $this->post('/performance/periods', ['code' => 'kin-2026', 'name' => 'Kinerja 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $period = (int) DB::table('performance_periods')->value('id');
        $this->post('/performance/periods/'.$period.'/open')->assertSessionHasErrors('period');

        // Draft v1 = default blueprint 60/30/10; total bobot ≠ 100 ditolak
        $this->post('/performance/policies/draft')->assertSessionHasNoErrors();
        $v1 = (int) DB::table('performance_policies')->value('id');
        $config = PerformancePolicy::DEFAULTS;
        $config['weights']['what'] = 70;
        $this->put('/performance/policies/'.$v1, ['name' => 'Kebijakan 2026', 'config' => $config])->assertSessionHasErrors('config');

        $config['weights'] = ['what' => 60, 'how' => 30, 'contribution' => 10];
        $config['bands'] = array_slice($config['bands'], 0, 4);
        $config['bands'][3]['min'] = 0;
        $this->put('/performance/policies/'.$v1, ['name' => 'Kebijakan 2026', 'config' => $config])->assertSessionHasNoErrors();
        $saved = json_decode(DB::table('performance_policies')->where('id', $v1)->value('config'), true);
        $this->assertCount(4, $saved['bands'], 'daftar band diganti utuh, tidak digabung dengan default');
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'performance_policies')->where('description', 'Draft kebijakan kinerja diubah')->exists());

        $this->post('/performance/policies/'.$v1.'/publish')->assertSessionHasNoErrors();
        $this->post('/performance/periods/'.$period.'/open')->assertSessionHasNoErrors();
        $snapshot = json_decode(DB::table('performance_periods')->where('id', $period)->value('policy_snapshot'), true);
        $this->assertSame(1, $snapshot['_version']);
        $this->assertSame(60.0, (float) $snapshot['weights']['what']);

        // Kebijakan v2 terbit: v1 pensiun, periode tetap memakai salinan v1
        $this->post('/performance/policies/draft');
        $v2 = (int) DB::table('performance_policies')->where('status', 'draft')->value('id');
        $config['weights'] = ['what' => 70, 'how' => 30, 'contribution' => 0];
        $this->put('/performance/policies/'.$v2, ['name' => 'Kebijakan 2027', 'config' => $config])->assertSessionHasNoErrors();
        $this->post('/performance/policies/'.$v2.'/publish');
        $this->assertSame('retired', DB::table('performance_policies')->where('id', $v1)->value('status'));
        $this->assertSame(60.0, (float) json_decode(DB::table('performance_periods')->where('id', $period)->value('policy_snapshot'), true)['weights']['what']);

        // Kebijakan terbit tidak bisa diubah; periode beririsan ditolak
        $this->put('/performance/policies/'.$v2, ['name' => 'x', 'config' => $config])->assertSessionHasErrors('policy');
        $this->post('/performance/periods', ['code' => 'kin-x', 'name' => 'X', 'start_date' => '2026-06-01', 'end_date' => '2027-05-31'])->assertSessionHasErrors('start_date');
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'performance_periods')->where('description', 'like', '%dikunci%')->exists());
    }

    public function test_rencana_kpi_diajukan_dikembalikan_lalu_disetujui_atasan(): void
    {
        $this->publishDefaultPolicy();
        $this->openPeriod();
        $plan = $this->planOf($this->analyst);
        $this->assertSame('draft', $plan->status);
        $this->assertSame($this->positions['ANL'], (int) $plan->position_id);

        // Total bobot 90% → draft tersimpan, pengajuan ditolak
        $this->put('/performance/my-plans/'.$plan->id, ['action' => 'submit', 'items' => $this->kpis([40, 30, 20])])
            ->assertSessionHasErrors('items_total');
        $this->assertSame(3, DB::table('performance_objectives')->where('plan_id', $plan->id)->count());
        $this->assertSame('draft', DB::table('performance_plans')->where('id', $plan->id)->value('status'));

        // Bobot per KPI di luar 5–40%, milestone tidak 100%, rentang terbalik, rubrik tidak lengkap
        $bad = $this->kpis([45, 30, 25]);
        $bad[1]['range_min'] = 70;
        $bad[2]['milestones'][1]['weight'] = 50;
        $bad[] = ['title' => 'Kepuasan nasabah', 'kpi_type' => 'qualitative', 'weight' => 10, 'rubric' => ['a', 'b', '', 'd', 'e']];
        $this->put('/performance/my-plans/'.$plan->id, ['action' => 'draft', 'items' => $bad])
            ->assertSessionHasErrors(['items.0.weight', 'items.1.range_min', 'items.2.milestones', 'items.3.rubric']);

        $this->put('/performance/my-plans/'.$plan->id, ['action' => 'submit', 'items' => $this->kpis()])->assertSessionHasNoErrors();
        $this->assertSame('submitted', DB::table('performance_plans')->where('id', $plan->id)->value('status'));

        // Tugas persetujuan jatuh ke atasan langsung (dari posisi)
        $task = $this->task($plan->id);
        $this->assertSame($this->manager->id, (int) $task->approver_user_id);
        $this->as($this->manager)->get('/workflow/tasks/'.$task->id)->assertOk()->assertSee('Penyaluran kredit UMKM');
        $this->get('/performance/my-plans/'.$plan->id)->assertOk()->assertSee('Rina Analis');

        // Pegawai tidak bisa mengubah saat menunggu persetujuan
        $this->as($this->analyst)->put('/performance/my-plans/'.$plan->id, ['action' => 'draft', 'items' => $this->kpis()])->assertSessionHasErrors('items');

        // Atasan mengembalikan dengan catatan
        $this->as($this->manager)->post('/workflow/tasks/'.$task->id.'/reject', ['comment' => 'Target penyaluran terlalu rendah'])->assertSessionHasNoErrors();
        $row = DB::table('performance_plans')->find($plan->id);
        $this->assertSame('returned', $row->status);
        $this->assertSame('Target penyaluran terlalu rendah', $row->return_note);

        $items = $this->kpis();
        $items[0]['target'] = 60000000000;
        $this->as($this->analyst)->get('/performance/my-plans/'.$plan->id)->assertSee('Target penyaluran terlalu rendah');
        $this->put('/performance/my-plans/'.$plan->id, ['action' => 'submit', 'items' => $items])->assertSessionHasNoErrors();

        $this->as($this->manager)->post('/workflow/tasks/'.$this->task($plan->id)->id.'/approve')->assertSessionHasNoErrors();
        $row = DB::table('performance_plans')->find($plan->id);
        $this->assertSame('approved', $row->status);
        $this->assertSame(2, (int) $row->revision);
        $this->assertSame('60000000000.0000', DB::table('performance_objectives')->where('plan_id', $plan->id)->orderBy('sort_order')->value('target'));

        // Setelah disetujui KPI terkunci
        $this->as($this->analyst)->put('/performance/my-plans/'.$plan->id, ['action' => 'draft', 'items' => $this->kpis()])->assertSessionHasErrors('items');
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'performance_plans')->where('description', 'Rencana kinerja disetujui atasan')->exists());
    }

    public function test_cascading_sasaran_strategis_dan_kpi_atasan(): void
    {
        $this->publishDefaultPolicy();
        $period = $this->openPeriod();

        // Sasaran strategis: selain korporat wajib berinduk dengan level di atasnya
        $this->post('/performance/periods/'.$period.'/objectives', ['code' => 'so-1', 'title' => 'Pertumbuhan kredit sehat', 'level' => 'corporate'])->assertSessionHasNoErrors();
        $so1 = (int) DB::table('strategic_objectives')->where('code', 'SO-1')->value('id');
        $this->post('/performance/periods/'.$period.'/objectives', ['code' => 'div-1', 'title' => 'Kredit UMKM', 'level' => 'division'])->assertSessionHasErrors('parent_id');
        $this->post('/performance/periods/'.$period.'/objectives', ['code' => 'div-1', 'title' => 'Kredit UMKM', 'level' => 'division', 'parent_id' => $so1,
            'unit_id' => $this->unit, 'contribution_weight' => 40])->assertSessionHasNoErrors();
        $div1 = (int) DB::table('strategic_objectives')->where('code', 'DIV-1')->value('id');
        $this->post('/performance/periods/'.$period.'/objectives', ['code' => 'dir-1', 'title' => 'Salah', 'level' => 'directorate', 'parent_id' => $div1])->assertSessionHasErrors('parent_id');
        $this->get('/performance/periods/'.$period)->assertOk()->assertSee('Kredit UMKM')->assertSee('Rina Analis');

        // Rencana atasan disetujui atasannya (fallback HR/Tenant Admin karena posisi MGR tanpa atasan)
        $managerPlan = $this->planOf($this->manager);
        $mgrItems = $this->kpis();
        $mgrItems[0]['strategic_objective_id'] = $div1;
        $this->put('/performance/my-plans/'.$managerPlan->id, ['action' => 'submit', 'items' => $mgrItems])->assertSessionHasNoErrors();
        $task = $this->task($managerPlan->id);
        $this->assertSame($this->hr->id, (int) $task->approver_user_id);
        $this->asHr()->post('/workflow/tasks/'.$task->id.'/approve')->assertSessionHasNoErrors();
        $parentKpi = (int) DB::table('performance_objectives')->where('plan_id', $managerPlan->id)->where('title', 'Penyaluran kredit UMKM')->value('id');

        // Bawahan meng-cascade KPI atasan; wajib bobot kontribusi; KPI lain bukan induk yang sah
        $plan = $this->planOf($this->analyst);
        $this->get('/performance/my-plans/'.$plan->id)->assertSee('Budi Kepala: Penyaluran kredit UMKM');
        $items = $this->kpis();
        $items[0]['parent_objective_id'] = $parentKpi;
        $this->put('/performance/my-plans/'.$plan->id, ['action' => 'draft', 'items' => $items])->assertSessionHasErrors('items.0.contribution_weight');
        $items[0]['contribution_weight'] = 30;
        $items[1]['parent_objective_id'] = 999999;
        $this->put('/performance/my-plans/'.$plan->id, ['action' => 'draft', 'items' => $items])->assertSessionHasErrors('items.1.parent_objective_id');
        unset($items[1]['parent_objective_id']);
        $this->put('/performance/my-plans/'.$plan->id, ['action' => 'draft', 'items' => $items])->assertSessionHasNoErrors();
        $this->assertSame($parentKpi, (int) DB::table('performance_objectives')->where('plan_id', $plan->id)->orderBy('sort_order')->value('parent_objective_id'));

        // Sasaran yang dirujuk KPI tidak bisa dihapus
        $this->asHr()->delete('/performance/objectives/'.$div1)->assertSessionHasErrors('objective');
    }

    public function test_hak_akses_dan_isolasi(): void
    {
        $this->publishDefaultPolicy();
        $this->openPeriod();
        $plan = $this->planOf($this->analyst);

        // Pegawai tidak boleh membuka kebijakan & periode; pengguna lain tidak melihat rencana orang lain
        $this->as($this->analyst)->get('/performance/policies')->assertRedirect('dashboard');
        $this->get('/performance/periods')->assertRedirect('dashboard');
        $this->as($this->manager)->get('/performance/my-plans/'.$plan->id)->assertNotFound();
        $this->put('/performance/my-plans/'.$plan->id, ['action' => 'draft', 'items' => $this->kpis()])->assertNotFound();

        // Tenant lain
        $other = $this->makeTenant('TB');
        $otherAdmin = $this->makeUser($other, Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($otherAdmin, Roles::id(Roles::TENANT_ADMIN))->get('/performance/periods')->assertOk()->assertViewHas('periods', fn ($p) => $p->isEmpty());
        $this->get('/performance/periods/'.$plan->period_id)->assertNotFound();
        $this->get('/performance/my-plans/'.$plan->id)->assertNotFound();
        $this->get('/performance/policies')->assertOk()->assertSee('Belum ada kebijakan');
    }
}
