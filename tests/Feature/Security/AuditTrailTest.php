<?php

namespace Tests\Feature\Security;

use App\Domain\Employee\Services\EmployeeIdentity;
use App\Models\Corporate;
use App\Support\Audit\AuditLog;
use App\Support\Audit\DataExport;
use App\Support\Security\Roles;
use App\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::flushCache();
        $this->tenant = $this->makeTenant('TA');
        $this->makeCompany($this->tenant, 'A1');
        $this->admin = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($this->admin, Roles::id(Roles::TENANT_ADMIN));
    }

    public function test_audit_logs_append_only_di_database(): void
    {
        DB::table('audit_logs')->insert(['event' => 'security', 'auditable_type' => 'probe']);

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->update(['event' => 'x']);
    }

    public function test_audit_logs_tidak_bisa_dihapus(): void
    {
        DB::table('audit_logs')->insert(['event' => 'security', 'auditable_type' => 'probe']);

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->delete();
    }

    public function test_jalur_sximo_mencatat_created_updated_deleted_dengan_diff(): void
    {
        $this->asTenant($this->tenant, function () {
            $id = (new Corporate)->insertRow(['code' => 'B2', 'slug_corporate' => 'b2', 'corporate_name' => 'PT B2'], null);
            (new Corporate)->insertRow(['corporate_name' => 'PT B2 Baru', 'code' => 'B2'], $id);
            Corporate::destroy([$id]);

            $rows = DB::table('audit_logs')->where('auditable_type', 'corporate')->where('auditable_id', (string) $id)->orderBy('id')->get();
            $this->assertSame(['created', 'updated', 'deleted'], $rows->pluck('event')->all());
            $this->assertSame(['corporate_name' => 'PT B2 Baru'], json_decode($rows[1]->new_values, true));
            $this->assertSame(['corporate_name' => 'PT B2'], json_decode($rows[1]->old_values, true));
            $this->assertSame($this->tenant, (int) $rows[0]->tenant_id);
            $this->assertSame($this->admin->id, (int) $rows[0]->user_id);
        });
    }

    public function test_kolom_rahasia_tidak_pernah_tersimpan(): void
    {
        $this->asTenant($this->tenant, function () {
            AuditLog::updated('tb_users', 1, ['password' => 'hash-lama', 'email' => 'a@x'], ['password' => 'hash-baru', 'email' => 'b@x']);

            $employee = DB::table('employees')->insertGetId(['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'company_id' => DB::table('corporate')->value('id_corporate'),
                'employee_number' => 'E1', 'full_name' => 'X', 'employment_status' => 'pkwtt', 'join_date' => '2024-01-01', 'status' => 'active']);
            EmployeeIdentity::save($employee, ['nik_ktp' => '3171010101900001', 'npwp' => '01.234.567.8-901.000']);
        });

        $row = DB::table('audit_logs')->where('auditable_type', 'tb_users')->first();
        $this->assertSame(AuditLog::MASK, json_decode($row->new_values, true)['password']);
        $this->assertSame('b@x', json_decode($row->new_values, true)['email']);

        $dump = DB::table('audit_logs')->get()->toJson();
        $this->assertStringNotContainsString('3171010101900001', $dump);
        $this->assertStringNotContainsString('hash-baru', $dump);
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'employee_identities')->where('description', 'like', '%NIK KTP%')->exists());
    }

    public function test_ekspor_wajib_alasan_watermark_dan_tercatat(): void
    {
        $this->get('/corporate/export?do=csv')->assertOk()->assertSee('Alasan ekspor data');
        $this->get('/corporate/export?do=csv&export_reason=pendek')->assertSee('Alasan minimal');
        $this->assertSame(0, DB::table('data_export_logs')->count());

        $response = $this->get('/corporate/export?do=csv&export_reason=Rekap+company+untuk+audit');
        $response->assertOk();
        $this->assertStringContainsString('RAHASIA', $response->getContent());

        $log = DB::table('data_export_logs')->first();
        $this->assertSame('corporate', $log->module);
        $this->assertSame(1, (int) $log->row_count);
        $this->assertStringContainsString('#'.$log->id, $response->getContent());
        $this->assertTrue(DB::table('audit_logs')->where('event', 'exported')->where('auditable_id', (string) $log->id)->exists());
    }

    public function test_ekspor_massal_butuh_izin_approve(): void
    {
        $rows = [];
        foreach (range(1, DataExport::MASS_THRESHOLD + 1) as $i) {
            $rows[] = ['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'code' => 'M'.$i, 'slug_corporate' => 'm'.$i, 'corporate_name' => 'PT M'.$i];
        }
        DB::table('corporate')->insert($rows);

        $this->get('/corporate/export?do=csv&export_reason=Ekspor+semua+company')->assertRedirect('corporate')->assertSessionHas('status', 'error');
        $this->assertSame(0, DB::table('data_export_logs')->count());

        $this->grantModule('corporate', Roles::id(Roles::TENANT_ADMIN), ['is_global', 'is_view', 'is_csv', 'is_approve']);
        $this->get('/corporate/export?do=csv&export_reason=Ekspor+semua+company')->assertOk();
        $this->assertTrue((bool) DB::table('data_export_logs')->value('approved_mass_export'));
    }

    public function test_ekspor_pegawai_tanpa_identitas_dan_dengan_watermark(): void
    {
        $company = DB::table('corporate')->value('id_corporate');
        $employee = DB::table('employees')->insertGetId(['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'company_id' => $company,
            'employee_number' => 'E-77', 'full_name' => 'Rina', 'employment_status' => 'pkwtt', 'join_date' => '2024-01-01', 'status' => 'active']);
        $this->asTenant($this->tenant, fn () => EmployeeIdentity::save($employee, ['nik_ktp' => '3171010101900077']));

        $this->get('/employees/export')->assertSee('Alasan ekspor data');
        $csv = $this->get('/employees/export?export_reason=Laporan+headcount+bulanan')->assertOk()->streamedContent();

        $this->assertStringStartsWith('"RAHASIA', $csv);
        $this->assertStringContainsString('E-77', $csv);
        $this->assertStringNotContainsString('3171010101900077', $csv);
        $this->assertSame('employees', DB::table('data_export_logs')->value('module'));
    }

    public function test_perubahan_hak_akses_modul_tercatat(): void
    {
        $root = $this->makeUser($this->tenant, Roles::id(Roles::SUPER_ADMIN));
        $moduleId = DB::table('tb_module')->where('module_name', 'corporate')->value('module_id');
        $hr = Roles::id(Roles::HR_ADMIN);

        // Controller Sximo membaca $_POST mentah (tidak diisi test client): cabut is_view yang semula 1
        $this->grantModule('corporate', $hr, ['is_view']);
        $this->actingAsSximo($root, Roles::id(Roles::SUPER_ADMIN))
            ->post('/sximo/module/savepermission/corporate', ['module_id' => $moduleId, 'group_id' => [$hr]]);

        $row = DB::table('audit_logs')->where('auditable_type', 'tb_groups_access')->where('auditable_id', $moduleId.':'.$hr)->first();
        $this->assertNotNull($row);
        $this->assertSame('1', json_decode($row->old_values, true)['is_view']);
        $this->assertSame('0', json_decode($row->new_values, true)['is_view']);
    }

    public function test_halaman_audit_per_peran_dan_per_tenant(): void
    {
        $this->post('/organization/job-families', ['code' => 'FIN', 'name' => 'Finance'])->assertSessionHasNoErrors();
        $other = $this->makeTenant('TB');
        DB::table('audit_logs')->insert(['tenant_id' => $other, 'event' => 'created', 'auditable_type' => 'rahasia_tenant_b', 'description' => 'milik B']);

        $this->get('/core/audit')->assertOk()->assertSee('job_families')->assertDontSee('rahasia_tenant_b');

        $auditor = $this->makeUser($this->tenant, Roles::id(Roles::AUDITOR));
        $this->actingAsSximo($auditor, Roles::id(Roles::AUDITOR))->get('/core/audit')->assertOk();

        $employee = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE));
        $this->actingAsSximo($employee, Roles::id(Roles::EMPLOYEE))->get('/core/audit')->assertRedirect('dashboard');
    }

    public function test_aksi_selama_break_glass_ditandai(): void
    {
        $target = $this->makeTenant('TB');
        $this->makeCompany($target, 'B1');
        $root = $this->makeUser($this->tenant, Roles::id(Roles::SUPER_ADMIN));
        $uuid = DB::table('tenants')->where('id', $target)->value('uuid');

        $this->actingAsSximo($root, Roles::id(Roles::SUPER_ADMIN))->post('/core/tenants/'.$uuid.'/break-glass',
            ['reason' => 'Tiket #9: perbaikan data company', 'minutes' => 15, 'confirm' => 1]);
        $this->post('/corporate', ['action_task' => 'save', 'code' => 'B9', 'corporate_name' => 'PT Perbaikan'])->assertJsonPath('status', 'success');

        $row = DB::table('audit_logs')->where('auditable_type', 'corporate')->where('event', 'created')->first();
        $this->assertSame($target, (int) $row->tenant_id);
        $this->assertSame((int) DB::table('break_glass_accesses')->value('id'), (int) $row->break_glass_id);
    }
}
