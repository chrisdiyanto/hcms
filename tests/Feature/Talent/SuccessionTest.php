<?php

namespace Tests\Feature\Talent;

use App\Support\Security\Roles;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/** Talent gelombang 3 — suksesi & bench strength (laporan CBHRM 11; K8). */
class SuccessionTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $company;

    private int $unit;

    private int $job;

    /** @var array<string,int> */
    private array $pos = [];

    /** @var array<string,int> */
    private array $emp = [];

    private User $hr;

    private User $hr2;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->tenant = $this->makeTenant('TA');
        $this->company = $this->makeCompany($this->tenant, 'A1');
        $this->unit = $this->makeUnit($this->tenant, 'Direktorat Risiko', null, $this->company);
        $family = $this->insert('job_families', ['code' => 'RSK', 'name' => 'Risk']);
        $this->job = $this->insert('jobs_catalog', ['code' => 'RJ', 'title' => 'Risk', 'job_family_id' => $family]);
        foreach ([['CRO', 'Chief Risk Officer', true, 3], ['HRK', 'Kepala Risiko Kredit', true, 2], ['ANL', 'Analis', false, null]] as [$code, $title, $critical, $impact]) {
            $this->pos[$code] = $this->insert('positions', ['code' => $code, 'title' => $title, 'company_id' => $this->company, 'unit_id' => $this->unit, 'job_id' => $this->job,
                'status' => 'active', 'headcount' => 5, 'effective_from' => '2020-01-01', 'is_critical' => $critical, 'critical_impact' => $impact,
                'critical_reason' => $critical ? 'Pemegang izin regulator' : null]);
        }
        // Pemangku CRO mencapai usia 59 dalam 12 bulan; posisi HRK kosong
        $this->emp['hadi'] = $this->employee('Hadi Pemangku', 'CRO', now()->subYears(59)->addMonths(3)->toDateString());
        $this->emp['ayu'] = $this->employee('Ayu Suksesor', 'ANL', '1990-01-01');
        $this->emp['bima'] = $this->employee('Bima Suksesor', 'ANL', '1991-01-01');
        $this->hr = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $this->hr2 = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));

        // Talent pool menyasar CRO: Ayu aktif, siap sekarang
        $pool = $this->insert('talent_pools', ['code' => 'CRO-P', 'name' => 'Calon CRO', 'purpose' => 'x', 'target_position_id' => $this->pos['CRO'],
            'entry_criteria' => 'x', 'exit_criteria' => 'x', 'owner_user_id' => $this->hr->id, 'review_cycle_months' => 12, 'is_active' => true]);
        DB::table('talent_pool_members')->insert(['tenant_id' => $this->tenant, 'pool_id' => $pool, 'employee_id' => $this->emp['ayu'], 'status' => 'active',
            'source' => 'manual', 'nomination_reason' => 'x', 'nominated_by' => $this->hr->id, 'readiness' => 'now', 'joined_at' => now()->toDateString(), 'created_at' => now()]);
    }

    private function insert(string $table, array $data): int
    {
        return DB::table($table)->insertGetId($data + ['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'created_at' => now()]);
    }

    private function employee(string $name, string $position, string $birth): int
    {
        $id = $this->insert('employees', ['company_id' => $this->company, 'employee_number' => 'E'.Str::upper(Str::random(5)), 'full_name' => $name,
            'birth_date' => $birth, 'employment_status' => 'pkwtt', 'join_date' => '2021-01-04', 'status' => 'active']);
        $this->insert('employee_assignments', ['employee_id' => $id, 'position_id' => $this->pos[$position], 'type' => 'primary', 'effective_from' => '2021-01-04']);

        return $id;
    }

    private function add(int $position, string $who, string $role, string $readiness, string $source = 'manual')
    {
        return $this->post('/talent/succession/'.$position.'/candidates', ['employee_id' => $this->emp[$who], 'role' => $role, 'readiness' => $readiness,
            'source' => $source, 'rationale' => 'Kinerja tinggi dan kesiapan kompetensi memadai', 'development_action' => 'Rotasi 6 bulan']);
    }

    public function test_rencana_suksesi_pengesahan_dan_metrik_k8(): void
    {
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN));
        $index = $this->get('/talent/succession')->assertOk()->assertSee('Chief Risk Officer')->assertDontSee('>Analis<', false);
        $this->assertSame(['total' => 2, 'covered' => 0, 'coverage' => 0.0, 'bench' => 0.0, 'at_risk' => 2, 'no_plan' => 2, 'draft' => 0, 'duplicates' => 0], $index->viewData('metrics'));
        $positions = $index->viewData('positions')->keyBy('code');
        $this->assertSame([['retirement'], ['vacant']], [$positions['CRO']->risks, $positions['HRK']->risks]);

        // Rencana CRO: usulan dari talent pool; pemangku tidak boleh jadi suksesornya; alasan wajib
        $show = $this->get('/talent/succession/'.$this->pos['CRO'])->assertOk()->assertSee('Pensiun ≤ 12 bulan');
        $this->assertSame(['Ayu Suksesor' => 'pool'], $show->viewData('suggestions')->pluck('source', 'full_name')->all());
        $this->add($this->pos['CRO'], 'hadi', 'primary', 'now')->assertSessionHasErrors('employee_id');
        $this->post('/talent/succession/'.$this->pos['CRO'].'/candidates', ['employee_id' => $this->emp['bima'], 'role' => 'backup', 'readiness' => '1_2',
            'source' => 'manual', 'rationale' => 'singkat'])->assertSessionHasErrors('rationale');
        $this->add($this->pos['CRO'], 'ayu', 'primary', 'now', 'pool')->assertSessionHasNoErrors();
        $this->add($this->pos['CRO'], 'bima', 'backup', '1_2')->assertSessionHasNoErrors();
        $this->add($this->pos['CRO'], 'bima', 'backup', '1_2')->assertSessionHasErrors('employee_id');

        // Draft tidak dihitung; pengubah terakhir tidak boleh mengesahkan
        $this->assertSame(0, $this->get('/talent/succession')->viewData('metrics')['covered']);
        $this->post('/talent/succession/'.$this->pos['CRO'].'/approve')->assertSessionHasErrors('status');
        $this->actingAsSximo($this->hr2, Roles::id(Roles::TENANT_ADMIN))->post('/talent/succession/'.$this->pos['CRO'].'/approve')->assertSessionHasNoErrors();
        $metrics = $this->get('/talent/succession')->viewData('metrics');
        $this->assertSame([1, 50.0, 1.0, 2], [$metrics['covered'], $metrics['coverage'], $metrics['bench'], $metrics['at_risk']]);

        // Perubahan kandidat → kembali draft; alasan wajib
        $bima = (int) DB::table('succession_candidates')->where('employee_id', $this->emp['bima'])->value('id');
        $this->put('/talent/succession/candidates/'.$bima, ['action' => 'update', 'role' => 'backup', 'readiness' => 'now'])->assertSessionHasErrors('reason');
        $this->put('/talent/succession/candidates/'.$bima, ['action' => 'update', 'role' => 'backup', 'readiness' => 'now', 'reason' => 'Lulus program kepemimpinan'])->assertSessionHasNoErrors();
        $this->assertSame('draft', DB::table('succession_plans')->where('position_id', $this->pos['CRO'])->value('status'));
        $this->assertSame(0, $this->get('/talent/succession')->viewData('metrics')['covered']);

        // HRK: Ayu juga suksesor utama → kandidat ganda setelah keduanya disahkan
        $this->add($this->pos['HRK'], 'ayu', 'primary', '1_2')->assertSessionHasNoErrors();
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN))->post('/talent/succession/'.$this->pos['CRO'].'/approve')->assertSessionHasNoErrors();
        $this->post('/talent/succession/'.$this->pos['HRK'].'/approve')->assertSessionHasNoErrors();
        $metrics = $this->get('/talent/succession')->viewData('metrics');
        $this->assertSame([1, 1, 1.5], [$metrics['covered'], $metrics['duplicates'], $metrics['bench']], '(CRO 2 siap sekarang + HRK 1 siap 1–2 thn) ÷ 2 posisi');
        $this->get('/talent/succession/'.$this->pos['CRO'])->assertOk()->assertSee('kandidat ganda');

        // Keluarkan kandidat: alasan wajib, jejak tetap ada
        $this->put('/talent/succession/candidates/'.$bima, ['action' => 'remove'])->assertSessionHasErrors('reason');
        $this->put('/talent/succession/candidates/'.$bima, ['action' => 'remove', 'reason' => 'Pindah perusahaan'])->assertSessionHasNoErrors();
        $this->assertSame(['removed', 'Pindah perusahaan'], [DB::table('succession_candidates')->where('id', $bima)->value('status'), DB::table('succession_candidates')->where('id', $bima)->value('removal_reason')]);
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'succession_plans')->where('event', 'sensitive_viewed')->exists());

        // Usia pensiun dari pengaturan tenant: 65 → risiko pensiun CRO hilang
        DB::table('tenant_settings')->insert(['tenant_id' => $this->tenant, 'key' => 'talent.retirement_age', 'value' => json_encode(65), 'created_at' => now()]);
        $this->assertSame([], $this->get('/talent/succession')->viewData('positions')->firstWhere('code', 'CRO')->risks);

        // Laporan: eksekutif melihat agregat tanpa nama kandidat; pegawai & tenant lain ditolak
        $exec = $this->makeUser($this->tenant, Roles::id(Roles::EXECUTIVE));
        DB::table('tb_groups')->where('group_id', Roles::id(Roles::EXECUTIVE))->update(['data_scope' => 'all']);
        Roles::flushCache();
        $this->actingAsSximo($exec, Roles::id(Roles::EXECUTIVE))->get('/talent/succession/report')->assertOk()
            ->assertSee('Chief Risk Officer')->assertDontSee('Ayu Suksesor')->assertViewHas('mask', true);
        $this->get('/talent/succession/'.$this->pos['CRO'])->assertRedirect('dashboard');
        $other = $this->makeUser($this->makeTenant('TB'), Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($other, Roles::id(Roles::TENANT_ADMIN))->get('/talent/succession/'.$this->pos['CRO'])->assertNotFound();
    }
}
