<?php

namespace Tests\Feature\Development;

use App\Support\Security\Roles;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/** Rilis 2 gelombang 2 — katalog intervensi & IDP (laporan CBHRM 6): draf dari TNA/saran, persetujuan atasan, pelaksanaan, rekap. */
class IdpTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $company;

    /** @var array<string,int> */
    private array $positions = [];

    /** @var array<string,int> */
    private array $competencies = [];

    private User $hr;

    private User $manager;

    private User $analyst;

    private User $outsider;

    private int $analystEmployee;

    private int $year;

    private int $need;

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
        foreach (['MGR' => 'Kepala Risiko', 'ANL' => 'Analis Risiko', 'OTH' => 'Analis Lain'] as $code => $title) {
            $this->positions[$code] = $this->insert('positions', ['code' => $code, 'title' => $title, 'company_id' => $this->company,
                'unit_id' => $unit, 'job_id' => $job, 'status' => 'active', 'headcount' => 1, 'effective_from' => '2020-01-01']);
        }
        DB::table('position_relationships')->insert(['tenant_id' => $this->tenant, 'position_id' => $this->positions['ANL'],
            'reports_to_id' => $this->positions['MGR'], 'type' => 'solid', 'effective_from' => '2020-01-01']);
        $version = $this->insert('competency_framework_versions', ['version' => 1, 'name' => 'v1', 'status' => 'published']);
        $category = DB::table('competency_categories')->insertGetId(['tenant_id' => $this->tenant, 'code' => 'FUNC', 'name' => 'Functional']);
        foreach (['RISK' => 'Manajemen Risiko', 'DATA' => 'Analisis Data'] as $code => $name) {
            $this->competencies[$code] = $this->insert('competencies', ['framework_version_id' => $version, 'category_id' => $category, 'code' => $code, 'name' => $name]);
        }

        $this->hr = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $this->manager = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'budi');
        $this->analyst = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'rina');
        $this->outsider = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE), 'sari');
        $this->employee($this->manager, 'MGR', 'Budi Kepala');
        $this->analystEmployee = $this->employee($this->analyst, 'ANL', 'Rina Analis');
        $this->employee($this->outsider, 'OTH', 'Sari Lain');

        // Masukan IDP: kebutuhan TNA disetujui (RISK), saran asesmen (DATA), aspirasi karier
        $this->need = $this->insert('training_needs', ['employee_id' => $this->analystEmployee, 'period_year' => $this->year, 'competency_id' => $this->competencies['RISK'],
            'competency_code' => 'RISK', 'topic' => 'Manajemen Risiko', 'source' => 'gap', 'current_level' => 2, 'target_level' => 4, 'criticality' => 3, 'gap' => -2,
            'priority_score' => 6, 'priority' => 'high', 'reason' => 'Gap −2', 'status' => 'approved', 'suggested_intervention' => 'Sertifikasi manajemen risiko',
            'estimated_cost' => 5000000]);
        $assessment = $this->insert('competency_assessments', ['employee_id' => $this->analystEmployee, 'framework_version_id' => $version,
            'position_id' => $this->positions['ANL'], 'title' => 'Asesmen 2026', 'status' => 'closed']);
        DB::table('competency_idp_suggestions')->insert(['tenant_id' => $this->tenant, 'employee_id' => $this->analystEmployee, 'competency_id' => $this->competencies['DATA'],
            'competency_code' => 'DATA', 'current_level' => 2, 'target_level' => 3, 'assessment_id' => $assessment, 'status' => 'draft', 'created_at' => now()]);
        $this->actingAsSximo($this->analyst, Roles::id(Roles::EMPLOYEE))->post('/ess/career', ['target_role' => 'Kepala Risiko', 'horizon' => '3_5', 'mobility' => 'yes']);
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

    private function items(int $idp): array
    {
        return DB::table('idp_activities')->where('idp_id', $idp)->orderBy('sort_order')->get()->map(fn ($a) => [
            'id' => $a->id, 'title' => $a->title, 'goal' => $a->goal, 'activity_type' => $a->activity_type, 'competency_id' => $a->competency_id,
            'target_level' => $a->target_level, 'intervention_id' => $a->intervention_id, 'due_date' => $a->due_date, 'cost_estimate' => $a->cost_estimate,
        ])->all();
    }

    private function pendingTask(): int
    {
        return (int) DB::table('approval_tasks')->where('status', 'pending')->orderByDesc('id')->value('id');
    }

    public function test_siklus_idp_dari_tna_sampai_selesai(): void
    {
        // HR mengisi katalog
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN))->post('/development/interventions', ['code' => 'MENTOR-DATA', 'name' => 'Mentoring analisis data',
            'type' => 'mentoring', 'competency_id' => $this->competencies['DATA'], 'target_level' => 3, 'cost_per_participant' => 0, 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $intervention = (int) DB::table('development_interventions')->where('code', 'MENTOR-DATA')->value('id');
        $this->get('/development/interventions')->assertOk()->assertSee('Mentoring analisis data')->assertSee('Mentoring');

        // Draft: aktivitas dari TNA & saran asesmen, sasaran karier dari aspirasi
        $this->actingAsSximo($this->analyst, Roles::id(Roles::EMPLOYEE))->get('/ess/idp')->assertOk()->assertSee('Buat IDP');
        $this->post('/ess/idp', ['year' => $this->year])->assertSessionHasNoErrors();
        $idp = DB::table('idps')->where('employee_id', $this->analystEmployee)->first();
        $this->assertSame('Kepala Risiko — 3–5 tahun', $idp->career_goal);
        $activities = DB::table('idp_activities')->where('idp_id', $idp->id)->orderBy('sort_order')->get();
        $this->assertSame([[$this->need, null, 'Sertifikasi manajemen risiko'], [null, 'DATA', 'Analisis Data']],
            $activities->map(fn ($a) => [$a->training_need_id ? (int) $a->training_need_id : null, $a->idp_suggestion_id ? $a->competency_code : null, $a->title])->all());
        $this->post('/ess/idp', ['year' => $this->year])->assertSessionHasErrors('period_year');

        // Ubah: aktivitas DATA memakai katalog; ajukan
        $items = $this->items($idp->id);
        $items[1]['intervention_id'] = $intervention;
        $items[1]['activity_type'] = 'mentoring';
        $this->put('/ess/idp/'.$idp->id, ['career_goal' => $idp->career_goal, 'items' => $items, 'action' => 'submit'])->assertSessionHasNoErrors();
        $this->assertSame('submitted', DB::table('idps')->where('id', $idp->id)->value('status'));
        $this->assertSame((string) $intervention, (string) DB::table('idp_activities')->where('id', $items[1]['id'])->value('intervention_id'));
        $this->assertSame((int) $this->need, (int) DB::table('idp_activities')->where('id', $items[0]['id'])->value('training_need_id'), 'rujukan TNA dipertahankan');
        $this->put('/ess/idp/'.$idp->id, ['items' => $items])->assertSessionHasErrors('status');

        // Atasan mengembalikan, pegawai mengajukan ulang, atasan menyetujui
        $this->actingAsSximo($this->outsider, Roles::id(Roles::EMPLOYEE))->get('/ess/idp/'.$idp->id)->assertNotFound();
        $this->actingAsSximo($this->manager, Roles::id(Roles::EMPLOYEE))->get('/ess/idp/'.$idp->id)->assertOk()->assertSee('Mentoring analisis data');
        $this->post('/workflow/tasks/'.$this->pendingTask().'/reject', ['comment' => 'Tambahkan tenggat yang realistis'])->assertSessionHasNoErrors();
        $this->assertSame(['returned', 'Tambahkan tenggat yang realistis'], [DB::table('idps')->where('id', $idp->id)->value('status'), DB::table('idps')->where('id', $idp->id)->value('return_note')]);
        $items[0]['due_date'] = $this->year.'-09-30';
        $this->actingAsSximo($this->analyst, Roles::id(Roles::EMPLOYEE))->put('/ess/idp/'.$idp->id, ['items' => $items, 'action' => 'submit'])->assertSessionHasNoErrors();
        $this->actingAsSximo($this->manager, Roles::id(Roles::EMPLOYEE))->post('/workflow/tasks/'.$this->pendingTask().'/approve')->assertSessionHasNoErrors();
        $this->assertSame('approved', DB::table('idps')->where('id', $idp->id)->value('status'));
        $this->assertSame('planned', DB::table('training_needs')->where('id', $this->need)->value('status'));
        $this->assertSame('accepted', DB::table('competency_idp_suggestions')->where('competency_code', 'DATA')->value('status'));

        // Pelaksanaan: selesai wajib bukti; TNA ditutup; atasan memberi catatan; IDP selesai
        $this->actingAsSximo($this->analyst, Roles::id(Roles::EMPLOYEE))->put('/ess/idp/activities/'.$items[0]['id'], ['status' => 'completed'])->assertSessionHasErrors('evidence');
        $this->put('/ess/idp/activities/'.$items[0]['id'], ['status' => 'completed', 'evidence' => 'Sertifikat CRP no. 123', 'certification_name' => 'CRP',
            'cost_actual' => 4500000])->assertSessionHasNoErrors();
        $this->assertSame('closed', DB::table('training_needs')->where('id', $this->need)->value('status'));
        $this->actingAsSximo($this->manager, Roles::id(Roles::EMPLOYEE))->put('/ess/idp/activities/'.$items[1]['id'], ['manager_note' => 'Mentor: Pak Andi'])->assertSessionHasNoErrors();
        $this->actingAsSximo($this->outsider, Roles::id(Roles::EMPLOYEE))->put('/ess/idp/activities/'.$items[1]['id'], ['manager_note' => 'x'])->assertForbidden();
        $this->actingAsSximo($this->analyst, Roles::id(Roles::EMPLOYEE))->put('/ess/idp/activities/'.$items[1]['id'], ['status' => 'in_progress'])->assertSessionHasNoErrors();
        $this->get('/ess/idp/'.$idp->id)->assertOk()->assertSee('1/2')->assertSee('Mentor: Pak Andi')->assertSee('Sertifikat CRP no. 123');

        // Rekap HR: kemajuan, gap setelah aktivitas selesai (RISK kini tertutup)
        DB::table('competency_gaps')->insert(['tenant_id' => $this->tenant, 'employee_id' => $this->analystEmployee, 'position_id' => $this->positions['ANL'],
            'competency_id' => $this->competencies['RISK'], 'competency_code' => 'RISK', 'required_level' => 4, 'criticality' => 3, 'actual' => 4, 'gap' => 0, 'computed_at' => now()]);
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN))->get('/development/idps?year='.$this->year)->assertOk()->assertSee('Rina Analis')
            ->assertViewHas('idpTotal', 1)->assertViewHas('activityTotal', 2)->assertViewHas('activityDone', 1)->assertViewHas('gapClosed', 1)->assertViewHas('actualCost', 4500000.0);
        $this->get('/ess/idp/'.$idp->id)->assertOk();

        $this->actingAsSximo($this->analyst, Roles::id(Roles::EMPLOYEE))->put('/ess/idp/activities/'.$items[1]['id'], ['status' => 'cancelled', 'employee_note' => 'Diganti kursus'])->assertSessionHasNoErrors();
        $this->assertSame('completed', DB::table('idps')->where('id', $idp->id)->value('status'));
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'idps')->where('description', 'IDP disetujui atasan')->exists());
        $this->get('/development/idps')->assertRedirect('dashboard');
    }

    public function test_isolasi_tenant_katalog_dan_idp(): void
    {
        $this->actingAsSximo($this->analyst, Roles::id(Roles::EMPLOYEE))->post('/ess/idp', ['year' => $this->year]);
        $idp = (int) DB::table('idps')->value('id');
        $other = $this->makeUser($this->makeTenant('TB'), Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($other, Roles::id(Roles::TENANT_ADMIN))->get('/ess/idp/'.$idp)->assertNotFound();
        $this->get('/development/idps?year='.$this->year)->assertOk()->assertViewHas('idpTotal', 0);
        $this->get('/development/interventions')->assertOk();
    }
}
