<?php

namespace Tests\Feature\Workflow;

use App\Domain\Workflow\WorkflowEngine;
use App\Support\Security\Roles;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private array $pos = [];

    private array $emp = [];

    /** @var array<string, User> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->tenant = $this->makeTenant('TA');
        $company = $this->makeCompany($this->tenant, 'A1');
        $unit = $this->makeUnit($this->tenant, 'Kantor Pusat', null, $company);
        $family = $this->insert('job_families', ['code' => 'F', 'name' => 'F']);
        $job = $this->insert('jobs_catalog', ['code' => 'J', 'title' => 'J', 'job_family_id' => $family]);

        foreach (['DIR', 'MGR', 'STAFF'] as $code) {
            $this->pos[$code] = $this->insert('positions', ['code' => $code, 'title' => 'Posisi '.$code, 'company_id' => $company, 'unit_id' => $unit,
                'job_id' => $job, 'status' => 'active', 'headcount' => 1, 'effective_from' => '2020-01-01']);
        }
        $this->relate('MGR', 'DIR');
        $this->relate('STAFF', 'MGR');

        foreach (['staff' => [Roles::EMPLOYEE, 'STAFF'], 'mgr' => [Roles::LINE_MANAGER, 'MGR'], 'dir' => [Roles::DEPARTMENT_HEAD, 'DIR']] as $key => [$role, $position]) {
            $this->users[$key] = $this->makeUser($this->tenant, Roles::id($role), $key.'.user');
            $this->emp[$key] = $this->insert('employees', ['company_id' => $company, 'user_id' => $this->users[$key]->id, 'employee_number' => strtoupper($key),
                'full_name' => 'Pegawai '.$key, 'employment_status' => 'pkwtt', 'join_date' => '2021-01-01', 'status' => 'active', 'phone' => '0811']);
            $this->insert('employee_assignments', ['employee_id' => $this->emp[$key], 'position_id' => $this->pos[$position], 'type' => 'primary', 'effective_from' => '2021-01-01']);
        }

        $this->users['hr'] = $this->makeUser($this->tenant, Roles::id(Roles::HR_ADMIN), 'hr.user');
        $this->users['admin'] = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN), 'admin.user');
    }

    private function insert(string $table, array $data): int
    {
        $data += ['tenant_id' => $this->tenant, 'created_at' => now()];
        if (\Schema::hasColumn($table, 'uuid')) {
            $data += ['uuid' => (string) Str::uuid7()];
        }

        return DB::table($table)->insertGetId($data);
    }

    private function relate(string $child, string $boss): void
    {
        $this->insert('position_relationships', ['position_id' => $this->pos[$child], 'reports_to_id' => $this->pos[$boss], 'type' => 'solid', 'effective_from' => '2020-01-01']);
    }

    private function as(string $key, ?string $role = null): static
    {
        $groupId = $role ? Roles::id($role) : (int) DB::table('tb_users_groups')->where('user_id', $this->users[$key]->id)->value('group_id');

        return $this->actingAsSximo($this->users[$key], $groupId);
    }

    private function submitEss(string $who = 'staff', string $phone = '0812999'): void
    {
        $this->as($who)->post('/ess/change-requests', ['phone' => $phone, 'reason' => 'Ganti nomor'])->assertSessionHasNoErrors();
    }

    private function pendingTask(): object
    {
        return DB::table('approval_tasks')->where('status', 'pending')->orderByDesc('id')->first();
    }

    private function useSupervisorFlow(): void
    {
        $this->as('admin')->put('/workflow/definitions/employee_change_request', ['steps' => [
            ['name' => 'Persetujuan atasan', 'approver' => ['type' => 'supervisor', 'levels' => 1], 'sla_hours' => 24],
        ]])->assertRedirect('workflow/definitions')->assertSessionHasNoErrors();
    }

    public function test_ess_disetujui_hr_dan_perubahan_diterapkan_dengan_jejak(): void
    {
        $this->submitEss();
        $task = $this->pendingTask();
        $this->assertSame($this->users['hr']->id, (int) $task->approver_user_id);
        $this->assertSame(1, DB::table('tb_notification')->where('userid', $this->users['hr']->id)->count());

        $this->as('hr')->get('/workflow/tasks')->assertOk()->assertSee('Pegawai staff');
        $this->get('/workflow/tasks/'.$task->id)->assertOk()->assertSee('0811')->assertSee('0812999');
        $this->post('/workflow/tasks/'.$task->id.'/approve', ['comment' => 'Sesuai'])->assertRedirect('workflow/tasks');

        $this->assertSame('0812999', DB::table('employees')->where('id', $this->emp['staff'])->value('phone'));
        $this->assertSame('approved', DB::table('employee_change_requests')->value('status'));
        $this->assertSame('approved', DB::table('workflow_instances')->value('status'));
        $this->assertTrue(DB::table('employee_status_histories')->where('employee_id', $this->emp['staff'])->where('field', 'phone')->where('reason', 'like', 'ESS #%')->exists());
        $this->assertSame(['submit', 'approve', 'complete'], DB::table('approval_actions')->orderBy('id')->pluck('action')->all());
        $this->assertSame($this->users['hr']->id, (int) DB::table('approval_actions')->where('action', 'approve')->value('user_id'));
        $this->assertSame(1, DB::table('tb_notification')->where('userid', $this->users['staff']->id)->count());
    }

    public function test_tolak_wajib_alasan_dan_data_tidak_berubah(): void
    {
        $this->submitEss();
        $task = $this->pendingTask();

        $this->as('hr')->post('/workflow/tasks/'.$task->id.'/reject', ['comment' => ''])->assertSessionHasErrors('comment');
        $this->post('/workflow/tasks/'.$task->id.'/reject', ['comment' => 'Nomor tidak valid'])->assertRedirect();

        $this->assertSame('0811', DB::table('employees')->where('id', $this->emp['staff'])->value('phone'));
        $this->assertSame('rejected', DB::table('employee_change_requests')->value('status'));
    }

    public function test_approver_berdasarkan_posisi_kosong_naik_dan_plt(): void
    {
        $this->useSupervisorFlow();

        $this->submitEss();
        $first = $this->pendingTask();
        $this->assertSame($this->users['mgr']->id, (int) $first->approver_user_id);
        $this->assertSame($this->pos['MGR'], (int) $first->approver_position_id);
        $this->as('mgr')->post('/workflow/tasks/'.$first->id.'/approve');

        // Posisi MGR kosong → naik ke DIR
        DB::table('employee_assignments')->where('employee_id', $this->emp['mgr'])->update(['effective_to' => now()->subDay()->toDateString()]);
        $this->submitEss('staff', '0813');
        $this->assertSame($this->users['dir']->id, (int) $this->pendingTask()->approver_user_id);
        $this->as('dir')->post('/workflow/tasks/'.$this->pendingTask()->id.'/approve');

        // Plt. di MGR menjadi approver
        $acting = $this->makeUser($this->tenant, Roles::id(Roles::LINE_MANAGER), 'plt.user');
        $actingEmp = $this->insert('employees', ['company_id' => DB::table('corporate')->value('id_corporate'), 'user_id' => $acting->id, 'employee_number' => 'PLT',
            'full_name' => 'Plt', 'employment_status' => 'pkwtt', 'join_date' => '2021-01-01', 'status' => 'active']);
        $this->insert('employee_assignments', ['employee_id' => $actingEmp, 'position_id' => $this->pos['MGR'], 'type' => 'acting', 'effective_from' => now()->toDateString()]);
        $this->submitEss('staff', '0814');
        $this->assertSame($acting->id, (int) $this->pendingTask()->approver_user_id);
    }

    public function test_pengaju_tidak_pernah_menjadi_approver_sendiri(): void
    {
        // HR yang juga pegawai mengajukan perubahan datanya sendiri → jatuh ke Tenant Administrator
        DB::table('employees')->where('id', $this->emp['dir'])->update(['user_id' => $this->users['hr']->id]);
        $this->submitEss('hr');

        $task = $this->pendingTask();
        $this->assertSame($this->users['admin']->id, (int) $task->approver_user_id);
        $this->as('hr')->get('/workflow/tasks/'.$task->id)->assertNotFound();
        $this->post('/workflow/tasks/'.$task->id.'/approve')->assertNotFound();
    }

    public function test_beberapa_kandidat_keputusan_pertama_menutup_langkah(): void
    {
        $hr2 = $this->makeUser($this->tenant, Roles::id(Roles::HR_ADMIN), 'hr2.user');
        $this->submitEss();
        $this->assertSame(2, DB::table('approval_tasks')->where('status', 'pending')->count());

        $mine = DB::table('approval_tasks')->where('approver_user_id', $hr2->id)->value('id');
        $this->actingAsSximo($hr2, Roles::id(Roles::HR_ADMIN))->post('/workflow/tasks/'.$mine.'/approve');

        $this->assertSame('cancelled', DB::table('approval_tasks')->where('approver_user_id', $this->users['hr']->id)->value('status'));
        $this->assertSame('approved', DB::table('workflow_instances')->value('status'));
    }

    public function test_delegasi_mengalihkan_tugas(): void
    {
        $this->useSupervisorFlow();
        $deputy = $this->makeUser($this->tenant, Roles::id(Roles::LINE_MANAGER), 'wakil.user');

        $this->as('mgr')->post('/workflow/delegations', ['delegate_user_id' => $deputy->id, 'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(3)->toDateString(), 'reason' => 'Cuti'])->assertSessionHasNoErrors();
        $this->post('/workflow/delegations', ['delegate_user_id' => $this->users['mgr']->id, 'starts_on' => now()->toDateString(),
            'ends_on' => now()->toDateString(), 'reason' => 'x'])->assertSessionHasErrors('delegate_user_id');

        $this->submitEss();
        $task = $this->pendingTask();
        $this->assertSame($deputy->id, (int) $task->approver_user_id);
        $this->assertSame($this->users['mgr']->id, (int) $task->delegated_from_user_id);
        $this->assertTrue(DB::table('approval_actions')->where('action', 'delegate')->exists());
    }

    public function test_eskalasi_sla_ke_atasan_approver(): void
    {
        $this->useSupervisorFlow();
        $this->submitEss();
        $task = $this->pendingTask();
        DB::table('approval_tasks')->where('id', $task->id)->update(['due_at' => now()->subHour()]);

        $this->assertSame(1, app(WorkflowEngine::class)->escalateOverdue());
        $this->assertSame('escalated', DB::table('approval_tasks')->where('id', $task->id)->value('status'));
        $escalated = $this->pendingTask();
        $this->assertSame($this->users['dir']->id, (int) $escalated->approver_user_id);
        $this->assertSame(1, (int) $escalated->escalation_level);

        // Puncak rantai: tidak ada atasan → tetap, tenggat diperpanjang
        DB::table('approval_tasks')->where('id', $escalated->id)->update(['due_at' => now()->subHour()]);
        $this->assertSame(0, app(WorkflowEngine::class)->escalateOverdue());
        $this->assertSame('pending', DB::table('approval_tasks')->where('id', $escalated->id)->value('status'));
        $this->assertTrue(now()->lt(DB::table('approval_tasks')->where('id', $escalated->id)->value('due_at')));
    }

    public function test_versi_alur_pengajuan_berjalan_tidak_berubah(): void
    {
        $this->submitEss();
        $oldVersion = DB::table('workflow_instances')->value('version_id');

        $this->useSupervisorFlow();
        $this->assertSame(2, (int) DB::table('workflow_versions')->where('is_current', true)->value('version'));
        $this->assertSame($oldVersion, DB::table('workflow_instances')->value('version_id'));
        $this->assertSame($this->users['hr']->id, (int) $this->pendingTask()->approver_user_id);

        $this->as('staff')->get('/workflow/definitions')->assertRedirect('dashboard');
    }

    public function test_tanpa_approver_pengajuan_ditolak_dengan_pesan_dan_tidak_tersimpan(): void
    {
        // Tidak ada HR Admin maupun Tenant Admin aktif di tenant
        DB::table('tb_users')->whereIn('id', [$this->users['hr']->id, $this->users['admin']->id])->update(['active' => 0]);

        $this->as('staff')->post('/ess/change-requests', ['phone' => '0899', 'reason' => 'Ganti nomor'])
            ->assertSessionHasErrors('workflow');

        $this->assertSame(0, DB::table('employee_change_requests')->count());
        $this->assertSame(0, DB::table('workflow_instances')->count());
        $this->get('/ess/profile')->assertOk()->assertSee('Tidak ada approver');
    }

    public function test_batal_oleh_pengaju_dan_satu_pengajuan_aktif(): void
    {
        $this->submitEss();
        $this->as('staff')->post('/ess/change-requests', ['phone' => '0899', 'reason' => 'lagi'])->assertSessionHasErrors('changes');
        $this->post('/ess/change-requests', ['phone' => '0811', 'reason' => 'sama'])->assertSessionHasErrors('changes');

        $instance = DB::table('workflow_instances')->value('id');
        $this->as('mgr')->post('/workflow/requests/'.$instance.'/cancel')->assertForbidden();
        $this->as('staff')->post('/workflow/requests/'.$instance.'/cancel')->assertRedirect();

        $this->assertSame('cancelled', DB::table('employee_change_requests')->value('status'));
        $this->assertSame(0, DB::table('approval_tasks')->where('status', 'pending')->count());
        $this->get('/ess/profile')->assertOk()->assertSee('Dibatalkan');
    }
}
