<?php

namespace Tests\Feature\Talent;

use App\Support\Security\Roles;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/** Talent gelombang 2 — talent pool (laporan CBHRM 10): kandidat dari talent review, nominasi, SoD, tinjau, keluar, laporan agregat. */
class TalentPoolTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $family;

    private User $hr;

    private User $hr2;

    /** @var array<string,int> */
    private array $emp = [];

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->tenant = $this->makeTenant('TA');
        $company = $this->makeCompany($this->tenant, 'A1');
        $unit = $this->makeUnit($this->tenant, 'Divisi Risiko', null, $company);
        $this->family = $this->insert('job_families', ['code' => 'RSK', 'name' => 'Manajemen Risiko']);
        $job = $this->insert('jobs_catalog', ['code' => 'RA', 'title' => 'Risk Analyst', 'job_family_id' => $this->family]);
        $position = $this->insert('positions', ['code' => 'ANL', 'title' => 'Analis', 'company_id' => $company, 'unit_id' => $unit,
            'job_id' => $job, 'status' => 'active', 'headcount' => 9, 'effective_from' => '2020-01-01']);
        foreach (['ayu' => 'F', 'bima' => 'M', 'citra' => 'F'] as $name => $gender) {
            $this->emp[$name] = $this->insert('employees', ['company_id' => $company, 'employee_number' => 'E'.Str::upper(Str::random(5)),
                'full_name' => ucfirst($name).' Pool', 'gender' => $gender, 'employment_status' => 'pkwtt', 'join_date' => '2021-01-04', 'status' => 'active']);
            $this->insert('employee_assignments', ['employee_id' => $this->emp[$name], 'position_id' => $position, 'type' => 'primary', 'effective_from' => '2021-01-04']);
        }
        $this->hr = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $this->hr2 = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));

        // Talent review sah: Ayu kotak 9 (risiko keluar tinggi), Bima 6, Citra 2
        $period = $this->insert('performance_periods', ['code' => 'K25', 'name' => 'Kinerja 2025', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'status' => 'closed']);
        $review = $this->insert('talent_reviews', ['name' => 'TR 2026', 'review_date' => now()->toDateString(), 'performance_period_id' => $period,
            'panel_user_ids' => json_encode([$this->hr->id, $this->hr2->id]), 'status' => 'approved', 'created_by' => $this->hr->id]);
        foreach (['ayu' => [9, 'high'], 'bima' => [6, null], 'citra' => [2, null]] as $name => [$box, $risk]) {
            DB::table('talent_review_entries')->insert(['tenant_id' => $this->tenant, 'review_id' => $review, 'employee_id' => $this->emp[$name],
                'proposed_box' => $box, 'final_box' => $box, 'flight_risk' => $risk, 'flight_risk_reason' => $risk ? 'Tawaran kompetitor' : null,
                'data_status' => 'complete', 'created_at' => now()]);
        }
        // Ayu: aspirasi sejalan dengan rumpun pool & IDP berjalan
        DB::table('employee_career_aspirations')->insert(['tenant_id' => $this->tenant, 'employee_id' => $this->emp['ayu'], 'target_job_family_id' => $this->family,
            'horizon' => '1_2', 'mobility' => 'yes', 'is_current' => true, 'submitted_by' => $this->hr->id, 'submitted_at' => now(), 'created_at' => now()]);
        $this->insert('idps', ['employee_id' => $this->emp['ayu'], 'period_year' => now()->year, 'status' => 'approved']);
    }

    private function insert(string $table, array $data): int
    {
        return DB::table($table)->insertGetId($data + ['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'created_at' => now()]);
    }

    public function test_siklus_talent_pool_dan_laporan(): void
    {
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN));
        $pool = ['code' => 'risk-lead', 'name' => 'Calon Pemimpin Risiko', 'purpose' => 'Menyiapkan kepala unit risiko', 'entry_criteria' => 'Kotak 6/8/9',
            'exit_criteria' => 'Turun ke kotak 1–4', 'owner_user_id' => $this->hr->id, 'review_cycle_months' => 12];
        $this->post('/talent/pools', $pool)->assertSessionHasErrors('target_job_family_id');
        $this->post('/talent/pools', $pool + ['target_job_family_id' => $this->family])->assertSessionHasNoErrors();
        $poolId = (int) DB::table('talent_pools')->value('id');

        // Kandidat: talenta kunci (kotak 6/8/9) saja
        $page = $this->get('/talent/pools/'.$poolId)->assertOk();
        $this->assertSame(['Ayu Pool', 'Bima Pool'], $page->viewData('candidates')->pluck('full_name')->all());
        $entries = DB::table('talent_review_entries')->pluck('id', 'employee_id');
        $this->post('/talent/pools/'.$poolId.'/nominate', ['candidates' => [$this->emp['ayu'].':'.$entries[$this->emp['ayu']], $this->emp['bima'].':'.$entries[$this->emp['bima']]],
            'employee_ids' => [$this->emp['citra']], 'reason' => 'Memenuhi kriteria masuk pool kepemimpinan risiko'])->assertSessionHasNoErrors();
        $members = DB::table('talent_pool_members')->pluck('id', 'employee_id');
        $this->assertSame(['talent_review', 'talent_review', 'manual'], DB::table('talent_pool_members')->orderBy('id')->pluck('source')->all());

        // SoD: penominasi tidak boleh memutus; disetujui wajib kesiapan; ditolak wajib alasan
        $this->post('/talent/pools/'.$poolId.'/decide', ['ids' => [$members[$this->emp['ayu']]], 'decision' => 'active', 'readiness' => '1_2'])->assertSessionHasErrors('ids');
        $this->actingAsSximo($this->hr2, Roles::id(Roles::TENANT_ADMIN));
        $this->post('/talent/pools/'.$poolId.'/decide', ['ids' => [$members[$this->emp['ayu']], $members[$this->emp['bima']]], 'decision' => 'active'])->assertSessionHasErrors('readiness');
        $this->post('/talent/pools/'.$poolId.'/decide', ['ids' => [$members[$this->emp['ayu']], $members[$this->emp['bima']]], 'decision' => 'active', 'readiness' => '1_2'])->assertSessionHasNoErrors();
        $this->post('/talent/pools/'.$poolId.'/decide', ['ids' => [$members[$this->emp['citra']]], 'decision' => 'rejected'])->assertSessionHasErrors('decision_note');
        $this->post('/talent/pools/'.$poolId.'/decide', ['ids' => [$members[$this->emp['citra']]], 'decision' => 'rejected', 'decision_note' => 'Belum memenuhi kriteria kotak'])->assertSessionHasNoErrors();
        $ayu = DB::table('talent_pool_members')->where('id', $members[$this->emp['ayu']])->first();
        $this->assertSame(['active', '1_2', now()->addYear()->toDateString()], [$ayu->status, $ayu->readiness, $ayu->next_review_date]);

        // Tinjau ulang & keluar
        $this->put('/talent/pools/members/'.$ayu->id, ['action' => 'review', 'readiness' => 'now', 'note' => 'Siap memimpin unit'])->assertSessionHasNoErrors();
        $this->put('/talent/pools/members/'.$members[$this->emp['bima']], ['action' => 'exit'])->assertSessionHasErrors('note');
        $this->put('/talent/pools/members/'.$members[$this->emp['bima']], ['action' => 'exit', 'note' => 'Pindah ke jalur spesialis'])->assertSessionHasNoErrors();
        $this->assertSame(8, DB::table('talent_pool_events')->count(), '3 nominasi + 3 keputusan + 1 tinjau ulang + 1 keluar');

        // Halaman pool: data pendukung & risiko keluar (izin sensitif)
        $show = $this->get('/talent/pools/'.$poolId)->assertOk()->assertSee('Risiko keluar: <strong>Tinggi</strong>', false)->assertSee('Selaras');
        $member = $show->viewData('members')->firstWhere('employee_id', $this->emp['ayu']);
        $this->assertSame(['aligned', 'approved', 9], [$member->aspiration, $member->idp_status, $member->latest_box]);
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'talent_pools')->where('event', 'sensitive_viewed')->exists());

        // Laporan agregat
        $report = $this->get('/talent/pools/report')->assertOk()->assertSee('Calon Pemimpin Risiko');
        $report->assertViewHas('people', 1)->assertViewHas('readyNow', 1)->assertViewHas('aligned', 1)->assertViewHas('withIdp', 1)
            ->assertViewHas('joined', 2)->assertViewHas('exited', 1);
        $exec = $this->makeUser($this->tenant, Roles::id(Roles::EXECUTIVE));
        $this->actingAsSximo($exec, Roles::id(Roles::EXECUTIVE))->get('/talent/pools/report')->assertOk()->assertViewHas('mask', true)
            ->assertSee('&lt; 5', false)->assertDontSee('Ayu Pool');
        $this->get('/talent/pools/'.$poolId)->assertRedirect('dashboard');

        $other = $this->makeUser($this->makeTenant('TB'), Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($other, Roles::id(Roles::TENANT_ADMIN))->get('/talent/pools/'.$poolId)->assertNotFound();
    }
}
