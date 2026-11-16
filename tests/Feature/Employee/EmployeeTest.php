<?php

namespace Tests\Feature\Employee;

use App\Domain\Employee\Actions\TerminateEmployee;
use App\Domain\Organization\Services\OrgUnitClosure;
use App\Support\Security\Roles;
use App\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private int $company;

    private array $units = [];

    private array $positions = [];

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->tenant = $this->makeTenant('TA');
        $this->company = $this->makeCompany($this->tenant, 'A1');
        $this->units['div'] = $this->makeUnit($this->tenant, 'Divisi', null, $this->company);
        $this->units['sek'] = $this->makeUnit($this->tenant, 'Seksi', $this->units['div'], $this->company);
        $this->units['lain'] = $this->makeUnit($this->tenant, 'Divisi Lain', null, $this->company);
        OrgUnitClosure::rebuild($this->tenant);

        $family = $this->insert('job_families', ['code' => 'F', 'name' => 'Finance']);
        $job = $this->insert('jobs_catalog', ['code' => 'J', 'title' => 'Analyst', 'job_family_id' => $family]);
        foreach (['DIV' => 'div', 'SEK' => 'sek', 'LAIN' => 'lain'] as $code => $unit) {
            $this->positions[$code] = $this->insert('positions', ['code' => 'P-'.$code, 'title' => 'Posisi '.$code, 'company_id' => $this->company,
                'unit_id' => $this->units[$unit], 'job_id' => $job, 'status' => 'active', 'headcount' => 1, 'effective_from' => '2020-01-01']);
        }

        $this->hr = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($this->hr, Roles::id(Roles::TENANT_ADMIN));
    }

    private function insert(string $table, array $data): int
    {
        return DB::table($table)->insertGetId($data + ['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'created_at' => now()]);
    }

    private function employee(array $override = []): int
    {
        return $this->insert('employees', $override + [
            'company_id' => $this->company, 'employee_number' => 'E'.Str::random(5), 'full_name' => 'Pegawai '.Str::random(4),
            'employment_status' => 'pkwtt', 'join_date' => '2021-01-04', 'status' => 'active',
        ]);
    }

    private function assign(int $employee, string $position, string $from, array $extra = [])
    {
        return $this->post('/employees/'.$employee.'/assignments', $extra + ['position_id' => $this->positions[$position], 'type' => 'primary', 'effective_from' => $from]);
    }

    public function test_tambah_pegawai_dengan_identitas_terenkripsi(): void
    {
        $this->post('/employees', [
            'company_id' => $this->company, 'employee_number' => 'emp-001', 'full_name' => 'Budi Santoso',
            'employment_status' => 'pkwt', 'join_date' => '2024-02-01', 'contract_end_date' => '2026-01-31',
            'nik_ktp' => '3171010101900001', 'npwp' => '01.234.567.8-901.000',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $employee = DB::table('employees')->where('employee_number', 'EMP-001')->first();
        $identity = DB::table('employee_identities')->where('employee_id', $employee->id)->first();
        $this->assertNotSame('3171010101900001', $identity->nik_ktp);
        $this->assertSame('3171010101900001', Crypt::decryptString($identity->nik_ktp));
        $this->assertSame(64, strlen($identity->nik_hash));

        $this->get('/employees/'.$employee->id)->assertOk()->assertSee('••••••••••••0001')->assertDontSee('3171010101900001');
        $this->postJson('/employees/'.$employee->id.'/identity')->assertOk()->assertJsonPath('nik_ktp', '3171010101900001');
        $this->assertTrue(DB::table('tb_logs')->where('module', 'employees.sensitive')->exists());

        // NIK duplikat, PKWT tanpa akhir kontrak, NIP duplikat
        $this->post('/employees', ['company_id' => $this->company, 'employee_number' => 'EMP-002', 'full_name' => 'X', 'employment_status' => 'pkwtt',
            'join_date' => '2024-01-01', 'nik_ktp' => '3171010101900001'])->assertSessionHasErrors('nik_ktp');
        $this->post('/employees', ['company_id' => $this->company, 'employee_number' => 'EMP-003', 'full_name' => 'X', 'employment_status' => 'pkwt',
            'join_date' => '2024-01-01'])->assertSessionHasErrors('contract_end_date');
        $this->post('/employees', ['company_id' => $this->company, 'employee_number' => 'emp-001', 'full_name' => 'X', 'employment_status' => 'pkwtt',
            'join_date' => '2024-01-01'])->assertSessionHasErrors('employee_number');
    }

    public function test_tanpa_izin_data_sensitif_identitas_tidak_terbuka(): void
    {
        $id = $this->employee();
        DB::table('employee_identities')->insert(['tenant_id' => $this->tenant, 'employee_id' => $id, 'nik_ktp' => Crypt::encryptString('3171010101900009')]);

        $hrbp = $this->makeUser($this->tenant, Roles::id(Roles::HRBP));
        DB::table('user_data_scopes')->insert(['tenant_id' => $this->tenant, 'user_id' => $hrbp->id, 'unit_id' => $this->units['div'], 'include_children' => 1]);
        $this->actingAs($this->hr)->assign($id, 'DIV', '2021-01-04');

        $this->actingAsSximo($hrbp, Roles::id(Roles::HRBP))->get('/employees/'.$id)->assertOk()->assertDontSee('Identitas negara');
        $this->postJson('/employees/'.$id.'/identity')->assertRedirect('dashboard');
    }

    public function test_penugasan_primer_mutasi_rangkap_dan_validasi(): void
    {
        $a = $this->employee();
        $b = $this->employee();

        $this->assign($a, 'DIV', '2021-01-04')->assertSessionHasNoErrors();
        // Mutasi: primer baru menutup primer lama sehari sebelumnya
        $this->assign($a, 'SEK', '2023-07-01')->assertSessionHasNoErrors();
        $this->assertSame('2023-06-30', DB::table('employee_assignments')->where('employee_id', $a)->where('position_id', $this->positions['DIV'])->value('effective_to'));

        // Primer di masa yang tumpang tindih dengan periode tertutup ditolak
        $this->assign($a, 'LAIN', '2022-01-01')->assertSessionHasErrors('effective_from');
        // Sebelum tanggal bergabung ditolak
        $this->assign($b, 'LAIN', '2020-06-01')->assertSessionHasErrors('effective_from');
        // Headcount penuh: SEK sudah diisi A
        $this->assign($b, 'SEK', '2024-01-01')->assertSessionHasErrors('position_id');
        // Plt. boleh; rangkap di posisi yang sama dan periode bertumpang tindih ditolak
        $this->assign($b, 'SEK', '2024-01-01', ['type' => 'acting'])->assertSessionHasNoErrors();
        $this->assign($b, 'SEK', '2024-06-01', ['type' => 'concurrent'])->assertSessionHasErrors('position_id');
        // Posisi dibekukan ditolak
        DB::table('positions')->where('id', $this->positions['LAIN'])->update(['status' => 'frozen']);
        $this->assign($b, 'LAIN', '2024-01-01')->assertSessionHasErrors('position_id');

        $this->assertSame(1, DB::table('employee_assignments')->where('employee_id', $a)->where('type', 'primary')->whereNull('effective_to')->count());
        $this->assertTrue(DB::table('employee_status_histories')->where('employee_id', $a)->where('field', 'assignment_primary')->where('new_value', 'P-SEK')->exists());
    }

    public function test_data_scope_atasan_dan_pegawai(): void
    {
        $inSek = $this->employee(['full_name' => 'Ani Seksi']);
        $inLain = $this->employee(['full_name' => 'Joko Lain']);
        $this->assign($inSek, 'SEK', '2021-01-04');
        $this->assign($inLain, 'LAIN', '2021-01-04');

        $manager = $this->makeUser($this->tenant, Roles::id(Roles::LINE_MANAGER));
        DB::table('user_unit')->insert(['id_user' => $manager->id, 'id_unit' => $this->units['div'], 'start_date' => now()]);

        $this->actingAsSximo($manager, Roles::id(Roles::LINE_MANAGER))->get('/employees')->assertOk()->assertSee('Ani Seksi')->assertDontSee('Joko Lain');
        $this->get('/employees/'.$inLain)->assertNotFound();
        $this->get('/employees/'.$inSek)->assertOk();

        $staffUser = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE));
        DB::table('employees')->where('id', $inLain)->update(['user_id' => $staffUser->id]);
        $this->actingAsSximo($staffUser, Roles::id(Roles::EMPLOYEE))->get('/employees')->assertSee('Joko Lain')->assertDontSee('Ani Seksi');
        $this->get('/employees/'.$inLain)->assertOk()->assertDontSee('Ubah data');
        $this->post('/employees/'.$inLain.'/records/families', ['name' => 'X', 'relationship' => 'child'])->assertRedirect('dashboard');
    }

    public function test_terminasi_langsung_terjadwal_dan_batal(): void
    {
        $login = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE));
        $now = $this->employee(['user_id' => $login->id]);
        $this->assign($now, 'DIV', '2021-01-04');

        $this->post('/employees/'.$now.'/terminate', ['termination_date' => now()->toDateString(), 'type' => 'resign', 'reason' => 'Melanjutkan studi', 'rehire_eligible' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame('terminated', DB::table('employees')->where('id', $now)->value('status'));
        $this->assertSame(now()->toDateString(), DB::table('employee_assignments')->where('employee_id', $now)->value('effective_to'));
        $this->assertSame(0, (int) DB::table('tb_users')->where('id', $login->id)->value('active'));
        $this->assertSame('processed', DB::table('employee_terminations')->where('employee_id', $now)->value('state'));

        $later = $this->employee();
        $date = now()->addDays(10)->toDateString();
        $this->post('/employees/'.$later.'/terminate', ['termination_date' => $date, 'type' => 'contract_end', 'reason' => 'Kontrak selesai']);
        $this->assertSame('active', DB::table('employees')->where('id', $later)->value('status'));
        $this->post('/employees/'.$later.'/terminate', ['termination_date' => $date, 'type' => 'resign', 'reason' => 'Dua kali'])->assertSessionHasErrors('termination_date');

        $this->assertSame(0, app(TerminateEmployee::class)->processDue(now()->toDateString()));
        $this->assertSame(1, app(TerminateEmployee::class)->processDue($date));
        $this->assertSame('terminated', DB::table('employees')->where('id', $later)->value('status'));

        $third = $this->employee();
        $this->post('/employees/'.$third.'/terminate', ['termination_date' => $date, 'type' => 'other', 'reason' => 'x']);
        $t = DB::table('employee_terminations')->where('employee_id', $third)->value('id');
        $this->post('/employees/'.$third.'/terminations/'.$t.'/cancel')->assertSessionHas('status', 'success');
        $this->assertSame('cancelled', DB::table('employee_terminations')->where('id', $t)->value('state'));
    }

    public function test_terminasi_butuh_izin_approve(): void
    {
        $id = $this->employee();
        $hrbp = $this->makeUser($this->tenant, Roles::id(Roles::HRBP));
        $this->grantModule('employees', Roles::id(Roles::HRBP), ['is_view', 'is_edit']);
        DB::table('user_data_scopes')->insert(['tenant_id' => $this->tenant, 'user_id' => $hrbp->id, 'unit_id' => $this->units['div'], 'include_children' => 1]);
        $this->assign($id, 'DIV', '2021-01-04');

        $this->actingAsSximo($hrbp, Roles::id(Roles::HRBP))
            ->post('/employees/'.$id.'/terminate', ['termination_date' => now()->toDateString(), 'type' => 'resign', 'reason' => 'x'])
            ->assertRedirect('dashboard');
        $this->assertSame('active', DB::table('employees')->where('id', $id)->value('status'));
    }

    public function test_data_riwayat_dan_riwayat_perubahan(): void
    {
        $id = $this->employee(['full_name' => 'Citra']);
        $other = $this->employee();

        $this->post('/employees/'.$id.'/records/certifications', ['name' => 'CRMP', 'expires_at' => '2027-01-01'])->assertSessionHasNoErrors();
        $this->post('/employees/'.$id.'/records/families', ['name' => 'Dewi', 'relationship' => 'child', 'is_dependent' => 1])->assertSessionHasNoErrors();
        $this->assertTrue((bool) DB::table('employee_families')->where('employee_id', $id)->value('is_dependent'));
        $this->post('/employees/'.$id.'/records/unknown', [])->assertNotFound();

        $cert = DB::table('employee_certifications')->where('employee_id', $id)->value('id');
        $this->delete('/employees/'.$other.'/records/certifications/'.$cert)->assertNotFound();
        $this->delete('/employees/'.$id.'/records/certifications/'.$cert)->assertRedirect();

        $row = (array) DB::table('employees')->where('id', $id)->first();
        $this->put('/employees/'.$id, array_merge($row, ['full_name' => 'Citra Lestari', 'employment_status' => 'pkwtt']))->assertSessionHasNoErrors();
        $this->assertTrue(DB::table('employee_status_histories')->where('employee_id', $id)->where('field', 'full_name')->where('new_value', 'Citra Lestari')->exists());
        $this->get('/employees/'.$id)->assertSee('tahun');
    }

    public function test_isolasi_tenant_pegawai(): void
    {
        $other = $this->makeTenant('TB');
        $company = $this->makeCompany($other, 'B1');
        $foreign = DB::table('employees')->insertGetId(['tenant_id' => $other, 'uuid' => (string) Str::uuid7(), 'company_id' => $company, 'employee_number' => 'B-1',
            'full_name' => 'Rahasia B', 'employment_status' => 'pkwtt', 'join_date' => '2020-01-01', 'status' => 'active']);

        $this->get('/employees?status=all')->assertDontSee('Rahasia B');
        $this->get('/employees/'.$foreign)->assertNotFound();
        $this->post('/employees/'.$foreign.'/terminate', ['termination_date' => now()->toDateString(), 'type' => 'resign', 'reason' => 'x'])->assertNotFound();
        $this->post('/employees', ['company_id' => $company, 'employee_number' => 'X1', 'full_name' => 'X', 'employment_status' => 'pkwtt', 'join_date' => '2024-01-01'])
            ->assertSessionHasErrors('company_id');
    }
}
