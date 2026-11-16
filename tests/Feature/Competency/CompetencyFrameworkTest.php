<?php

namespace Tests\Feature\Competency;

use App\Domain\Competency\Services\CompetencyFramework;
use App\Domain\Competency\Services\CompetencyProfile;
use App\Support\Security\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CompetencyFrameworkTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $job;

    private int $position;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->tenant = $this->makeTenant('TA');
        $company = $this->makeCompany($this->tenant, 'A1');
        $unit = $this->makeUnit($this->tenant, 'Divisi Kredit', null, $company);
        CompetencyFramework::ensureCategories($this->tenant);
        $family = DB::table('job_families')->insertGetId(['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'code' => 'FIN', 'name' => 'Finance', 'created_at' => now()]);
        $this->job = DB::table('jobs_catalog')->insertGetId(['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'code' => 'RISK-AN', 'title' => 'Risk Analyst', 'job_family_id' => $family, 'created_at' => now()]);
        $this->position = DB::table('positions')->insertGetId(['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'code' => 'P1', 'title' => 'Analis', 'company_id' => $company,
            'unit_id' => $unit, 'job_id' => $this->job, 'status' => 'active', 'headcount' => 1, 'effective_from' => '2024-01-01', 'created_at' => now()]);

        $admin = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($admin, Roles::id(Roles::TENANT_ADMIN));
    }

    private function category(string $code): int
    {
        return (int) DB::table('competency_categories')->where('tenant_id', $this->tenant)->where('code', $code)->value('id');
    }

    private function levels(int $indicators = 2): array
    {
        $levels = [];
        foreach (range(1, 5) as $l) {
            $levels[$l] = ['title' => 'Level '.$l, 'description' => 'Deskripsi '.$l,
                'indicators' => implode("\n", array_map(fn ($i) => "Indikator $l.$i", range(1, $indicators)))];
        }

        return $levels;
    }

    private function addCompetency(int $version, string $code, string $category = 'FUNC', int $indicators = 2)
    {
        return $this->post('/competency/framework/'.$version.'/competencies', [
            'code' => $code, 'name' => 'Kompetensi '.$code, 'category_id' => $this->category($category),
            'definition' => 'Definisi '.$code, 'is_active' => 1, 'levels' => $this->levels($indicators),
        ]);
    }

    private function draftAndPublish(array $codes): int
    {
        $this->post('/competency/framework/draft')->assertRedirect();
        $version = (int) DB::table('competency_framework_versions')->where('status', 'draft')->value('id');
        foreach ($codes as $code) {
            $this->addCompetency($version, $code)->assertSessionHasNoErrors();
        }
        $this->post('/competency/framework/'.$version.'/publish')->assertSessionHasNoErrors();

        return $version;
    }

    private function competencyId(int $version, string $code): int
    {
        return (int) DB::table('competencies')->where('framework_version_id', $version)->where('code', $code)->value('id');
    }

    public function test_draft_lengkap_baru_bisa_terbit(): void
    {
        $this->post('/competency/framework/draft')->assertRedirect();
        $v1 = (int) DB::table('competency_framework_versions')->value('id');
        $this->post('/competency/framework/draft')->assertSessionHasErrors('version');

        $this->addCompetency($v1, 'risk', 'FUNC', 1)->assertSessionHasNoErrors();
        $this->get('/competency/framework')->assertOk()->assertSee('Belum dapat diterbitkan')->assertSee('RISK level 1: 1 indikator');
        $this->post('/competency/framework/'.$v1.'/publish')->assertSessionHasErrors('version');

        $competency = $this->competencyId($v1, 'RISK');
        $this->put('/competency/competencies/'.$competency, ['code' => 'RISK', 'name' => 'Analisis Risiko', 'category_id' => $this->category('FUNC'),
            'is_active' => 1, 'levels' => $this->levels(3)])->assertSessionHasNoErrors();
        $this->assertSame(15, DB::table('competency_indicators')->where('tenant_id', $this->tenant)->count());

        $this->post('/competency/framework/'.$v1.'/publish')->assertSessionHasNoErrors();
        $this->assertSame('published', DB::table('competency_framework_versions')->where('id', $v1)->value('status'));
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'competency_framework_versions')->where('description', 'like', '%diterbitkan%')->exists());
    }

    public function test_versi_terbit_hanya_baca_dan_draft_baru_menyalin_isinya(): void
    {
        $v1 = $this->draftAndPublish(['RISK', 'LEAD']);
        $risk = $this->competencyId($v1, 'RISK');

        $this->put('/competency/competencies/'.$risk, ['code' => 'RISK', 'name' => 'Diubah', 'category_id' => $this->category('FUNC'), 'levels' => $this->levels()])
            ->assertSessionHas('status', 'error');
        $this->assertSame('Kompetensi RISK', DB::table('competencies')->where('id', $risk)->value('name'));

        $this->post('/competency/framework/draft');
        $v2 = (int) DB::table('competency_framework_versions')->where('status', 'draft')->value('id');
        $this->assertSame(['LEAD', 'RISK'], DB::table('competencies')->where('framework_version_id', $v2)->orderBy('code')->pluck('code')->all());
        $this->assertSame(20, DB::table('competency_indicators')->join('competency_levels', 'competency_levels.id', '=', 'competency_indicators.competency_level_id')
            ->whereIn('competency_levels.competency_id', DB::table('competencies')->where('framework_version_id', $v2)->pluck('id'))->count());

        $risk2 = $this->competencyId($v2, 'RISK');
        $this->put('/competency/competencies/'.$risk2, ['code' => 'RISK', 'name' => 'Analisis Risiko v2', 'category_id' => $this->category('FUNC'),
            'is_active' => 1, 'levels' => $this->levels()])->assertSessionHasNoErrors();
        $this->assertSame('Kompetensi RISK', DB::table('competencies')->where('id', $risk)->value('name'));
    }

    public function test_profil_job_validasi_dan_audit(): void
    {
        $this->get('/competency/job-profiles/'.$this->job)->assertOk()->assertSee('Belum ada kamus');
        $v1 = $this->draftAndPublish(['RISK', 'LEAD', 'OLD']);
        $old = $this->competencyId($v1, 'OLD');
        DB::table('competencies')->where('id', $old)->update(['is_active' => false]);

        $items = [
            ['competency_id' => $this->competencyId($v1, 'RISK'), 'required_level' => 4, 'criticality' => 3],
            ['competency_id' => $this->competencyId($v1, 'LEAD'), 'required_level' => 3, 'criticality' => 2],
        ];
        $this->put('/competency/job-profiles/'.$this->job, ['items' => $items])->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('job_competencies')->where('job_id', $this->job)->count());
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'job_competencies')->exists());

        $this->put('/competency/job-profiles/'.$this->job, ['items' => [$items[0], $items[0]]])->assertSessionHasErrors('items.0.competency_id');
        $this->put('/competency/job-profiles/'.$this->job, ['items' => [['competency_id' => $old, 'required_level' => 2, 'criticality' => 1]]])->assertSessionHasErrors('items.0.competency_id');
        $this->put('/competency/job-profiles/'.$this->job, ['items' => [['competency_id' => $items[0]['competency_id'], 'required_level' => 6, 'criticality' => 4]]])
            ->assertSessionHasErrors(['items.0.required_level', 'items.0.criticality']);

        $this->get('/competency/job-profiles')->assertOk()->assertSee('RISK-AN');
    }

    public function test_terbit_versi_baru_memindahkan_profil_berdasarkan_kode(): void
    {
        $v1 = $this->draftAndPublish(['RISK', 'LEAD']);
        $this->put('/competency/job-profiles/'.$this->job, ['items' => [
            ['competency_id' => $this->competencyId($v1, 'RISK'), 'required_level' => 4, 'criticality' => 3],
            ['competency_id' => $this->competencyId($v1, 'LEAD'), 'required_level' => 3, 'criticality' => 2],
        ]]);

        $this->post('/competency/framework/draft');
        $v2 = (int) DB::table('competency_framework_versions')->where('status', 'draft')->value('id');
        $this->delete('/competency/competencies/'.$this->competencyId($v2, 'LEAD'))->assertSessionHas('status', 'success');
        $this->post('/competency/framework/'.$v2.'/publish')->assertSessionHasNoErrors();

        $this->assertSame('retired', DB::table('competency_framework_versions')->where('id', $v1)->value('status'));
        $profile = $this->asTenant($this->tenant, fn () => app(CompetencyProfile::class)->forJob($this->job));
        $this->assertSame(['RISK'], $profile->pluck('code')->all());
        $this->assertSame($this->competencyId($v2, 'RISK'), (int) $profile[0]->competency_id);
        $this->assertSame(4, (int) $profile[0]->required_level);
    }

    public function test_override_posisi_kecualikan_ubah_dan_tambah(): void
    {
        $v1 = $this->draftAndPublish(['RISK', 'LEAD', 'DATA']);
        $this->put('/competency/job-profiles/'.$this->job, ['items' => [
            ['competency_id' => $this->competencyId($v1, 'RISK'), 'required_level' => 4, 'criticality' => 3],
            ['competency_id' => $this->competencyId($v1, 'LEAD'), 'required_level' => 3, 'criticality' => 2],
        ]]);

        // Tambahan tanpa level/bobot ditolak; alasan wajib
        $this->put('/competency/positions/'.$this->position, ['items' => [['competency_id' => $this->competencyId($v1, 'DATA'), 'reason' => 'x']]])
            ->assertSessionHasErrors('items.0.required_level');
        $this->put('/competency/positions/'.$this->position, ['items' => [['competency_id' => $this->competencyId($v1, 'LEAD'), 'is_excluded' => 1]]])
            ->assertSessionHasErrors('items.0.reason');

        $this->put('/competency/positions/'.$this->position, ['items' => [
            ['competency_id' => $this->competencyId($v1, 'LEAD'), 'is_excluded' => 1, 'reason' => 'Posisi individual contributor'],
            ['competency_id' => $this->competencyId($v1, 'RISK'), 'required_level' => 5, 'criticality' => '', 'is_excluded' => 0, 'reason' => 'Portofolio korporat'],
            ['competency_id' => $this->competencyId($v1, 'DATA'), 'required_level' => 3, 'criticality' => 1, 'is_excluded' => 0, 'reason' => 'Analisis data portofolio'],
        ]])->assertSessionHasNoErrors();

        $effective = $this->asTenant($this->tenant, fn () => app(CompetencyProfile::class)->forPosition($this->position));
        $this->assertSame(['RISK:5:3:override', 'DATA:3:1:position'], $effective->map(fn ($e) => "$e->code:$e->required_level:$e->criticality:$e->source")->all());
        $this->get('/competency/positions/'.$this->position)->assertOk()->assertSee('tambahan');
    }

    public function test_hak_akses_dan_isolasi(): void
    {
        $v1 = $this->draftAndPublish(['RISK']);

        // HRBP: boleh lihat, tidak boleh ubah; edit tanpa izin Approve tidak bisa terbit
        $hrbp = $this->makeUser($this->tenant, Roles::id(Roles::HRBP));
        $this->actingAsSximo($hrbp, Roles::id(Roles::HRBP))->get('/competency/framework')->assertOk()->assertDontSee('Buat draft versi baru');
        $this->put('/competency/job-profiles/'.$this->job, ['items' => []])->assertRedirect('dashboard');
        $this->grantModule('competencies', Roles::id(Roles::HRBP), ['is_view', 'is_edit']);
        $this->post('/competency/framework/draft')->assertRedirect();
        $v2 = (int) DB::table('competency_framework_versions')->where('status', 'draft')->value('id');
        $this->post('/competency/framework/'.$v2.'/publish')->assertRedirect('dashboard');
        $this->assertSame('draft', DB::table('competency_framework_versions')->where('id', $v2)->value('status'));

        // Pegawai: boleh membaca kamus, tidak boleh membuka profil job
        $employee = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE));
        $this->actingAsSximo($employee, Roles::id(Roles::EMPLOYEE))->get('/competency/framework')->assertOk();
        $this->get('/competency/job-profiles')->assertRedirect('dashboard');

        // Tenant lain tidak melihat & tidak bisa merujuk kompetensi tenant ini
        $other = $this->makeTenant('TB');
        $otherAdmin = $this->makeUser($other, Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($otherAdmin, Roles::id(Roles::TENANT_ADMIN))->get('/competency/framework')->assertOk()->assertDontSee('Kompetensi RISK');
        $this->get('/competency/competencies/'.$this->competencyId($v1, 'RISK').'/edit')->assertNotFound();
        $this->get('/competency/job-profiles/'.$this->job)->assertNotFound();
    }
}
