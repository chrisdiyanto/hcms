<?php

namespace Tests\Feature\DataImport;

use App\Domain\Organization\Services\OrgUnitClosure;
use App\Support\Security\Roles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DataImportTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Roles::flushCache();
        $this->tenant = $this->makeTenant('TA');
        $this->makeCompany($this->tenant, 'A1');
        DB::table('unit_category')->insert(['tenant_id' => $this->tenant, 'slug_unit_category' => 'div', 'unit_category' => 'Divisi', 'unit_category_code' => 'DIV', 'unit_category_explan' => '']);
        DB::table('unit_type')->insert(['tenant_id' => $this->tenant, 'unit_type' => 'Struktural', 'keterangan' => '', 'status' => 1]);
        $admin = $this->makeUser($this->tenant, Roles::id(Roles::TENANT_ADMIN));
        $this->actingAsSximo($admin, Roles::id(Roles::TENANT_ADMIN));
    }

    private function upload(string $type, string $csv)
    {
        return $this->post('/core/import', ['type' => $type, 'file' => UploadedFile::fake()->createWithContent($type.'.csv', $csv)]);
    }

    private function lastBatch(): object
    {
        return DB::table('import_batches')->orderByDesc('id')->first();
    }

    private function importAndCommit(string $type, string $csv): object
    {
        $this->upload($type, $csv)->assertRedirect();
        $batch = $this->lastBatch();
        $this->assertSame('validated', $batch->status, $type.': '.$batch->errors);
        $this->post('/core/import/'.$batch->uuid.'/commit')->assertSessionHas('status', 'success');

        return DB::table('import_batches')->where('id', $batch->id)->first();
    }

    private function seedStructure(): void
    {
        $this->importAndCommit('units', "\xEF\xBB\xBFunit_code;unit_name;parent_unit_code;company_code;category_code;type_name\nDIR;Direktorat Bisnis;;A1;DIV;Struktural\nKRD;Divisi Kredit;DIR;A1;DIV;Struktural\n");
        $this->importAndCommit('job_families', "code,name\nFIN,Finance\n");
        $this->importAndCommit('job_levels', "code,name,rank,is_managerial\nSTF,Staff,1,Tidak\nMGR,Manager,3,Ya\n");
        $this->importAndCommit('job_grades', "code,name,rank,level_code\nG1,Grade 1,1,STF\nG5,Grade 5,5,MGR\n");
        $this->importAndCommit('jobs', "code,title,family_code,grade_min_code,grade_max_code\nANL,Analyst,FIN,G1,G5\n");
        // STAFF melapor ke HEAD yang baru muncul di baris berikutnya
        $this->importAndCommit('positions', "code,title,company_code,unit_code,job_code,grade_code,reports_to_code,effective_from\nSTAFF,Analis Kredit,A1,KRD,ANL,G1,HEAD,01/01/2020\nHEAD,Kepala Kredit,A1,KRD,ANL,G5,,2020-01-01\n");
    }

    public function test_validasi_kering_tidak_menyimpan_lalu_commit_menyimpan_dan_rekonsiliasi(): void
    {
        $this->upload('units', "unit_code;unit_name;parent_unit_code;company_code;category_code;type_name\nDIR;Direktorat;;A1;DIV;Struktural\nKRD;Divisi Kredit;DIR;A1;DIV;Struktural\n")->assertRedirect();
        $batch = $this->lastBatch();
        $this->assertSame('validated', $batch->status);
        $this->assertSame(0, DB::table('unit')->count(), 'Validasi kering tidak boleh menyimpan');
        $this->get('/core/import/'.$batch->uuid)->assertOk()->assertSee('Commit impor')->assertSee('(simulasi)');

        $this->post('/core/import/'.$batch->uuid.'/commit')->assertSessionHas('status', 'success');
        $batch = DB::table('import_batches')->where('id', $batch->id)->first();
        $this->assertSame('committed', $batch->status);
        $recon = json_decode($batch->reconciliation, true);
        $this->assertEquals(['rows_in_file' => 2, 'created' => 2, 'updated' => 0, 'db_before' => 0, 'db_after' => 2, 'committed' => true], $recon);

        $dir = DB::table('unit')->where('unit_code', 'DIR')->value('id_unit');
        $krd = DB::table('unit')->where('unit_code', 'KRD')->first();
        $this->assertSame($dir, (int) $krd->parent_id);
        $this->assertSame($this->tenant, (int) $krd->tenant_id);
        $this->assertTrue(OrgUnitClosure::isSelfOrDescendant($dir, (int) $krd->id_unit));
        $this->assertSame(2, DB::table('audit_logs')->where('auditable_type', 'unit')->where('event', 'created')->count());

        $this->post('/core/import/'.$batch->uuid.'/commit')->assertStatus(422);
    }

    public function test_alur_lengkap_sampai_pegawai_dan_impor_ulang_memperbarui(): void
    {
        $this->seedStructure();
        $staff = DB::table('positions')->where('code', 'STAFF')->value('id');
        $head = DB::table('positions')->where('code', 'HEAD')->value('id');
        $this->assertSame($head, (int) DB::table('position_relationships')->where('position_id', $staff)->whereNull('effective_to')->value('reports_to_id'));
        $this->assertSame('2020-01-01', DB::table('positions')->where('id', $staff)->value('effective_from'));

        $csv = "employee_number,full_name,company_code,employment_status,join_date,gender,position_code,nik_ktp\n"
            ."E-001,Budi Santoso,A1,pkwtt,04/01/2021,L,STAFF,3171010101900001\n";
        $batch = $this->importAndCommit('employees', $csv);
        $this->assertSame(1, (int) $batch->created_rows);

        $employee = DB::table('employees')->where('employee_number', 'E-001')->first();
        $this->assertSame('2021-01-04', $employee->join_date);
        $this->assertSame('M', $employee->gender);
        $this->assertSame('3171010101900001', Crypt::decryptString(DB::table('employee_identities')->where('employee_id', $employee->id)->value('nik_ktp')));
        $this->assertSame($staff, (int) DB::table('employee_assignments')->where('employee_id', $employee->id)->where('type', 'primary')->value('position_id'));

        // Impor ulang: diperbarui, tanpa penugasan ganda
        $batch = $this->importAndCommit('employees', str_replace('Budi Santoso', 'Budi S.', $csv));
        $this->assertSame(1, (int) $batch->updated_rows);
        $this->assertSame('Budi S.', DB::table('employees')->where('id', $employee->id)->value('full_name'));
        $this->assertSame(1, DB::table('employee_assignments')->where('employee_id', $employee->id)->count());
    }

    public function test_error_dilaporkan_per_baris_dan_tidak_ada_yang_tersimpan(): void
    {
        $this->upload('units', "unit_code,unit_name,parent_unit_code,company_code,category_code,type_name\nOK1,Unit Benar,,A1,DIV,Struktural\nBAD,Unit Salah,,ZZ,DIV,Struktural\nORF,Anak Yatim,TIDAKADA,A1,DIV,Struktural\n,Tanpa kode,,A1,DIV,Struktural\n");

        $batch = $this->lastBatch();
        $this->assertSame('invalid', $batch->status);
        $errors = json_decode($batch->errors, true);
        $this->assertSame([3, 4, 5], array_map('intval', array_keys($errors)));
        $this->assertStringContainsString('Company "ZZ" tidak ditemukan', $errors[3][0]);
        $this->assertSame(0, DB::table('unit')->count());
        $this->get('/core/import/'.$batch->uuid)->assertOk()->assertDontSee('Commit impor');
        $this->post('/core/import/'.$batch->uuid.'/commit')->assertStatus(422);
    }

    public function test_kolom_wajib_hilang_ditolak_di_tingkat_berkas(): void
    {
        $this->upload('job_levels', "code,name\nSTF,Staff\n");

        $errors = json_decode($this->lastBatch()->errors, true);
        $this->assertStringContainsString('Kolom wajib tidak ada: rank', $errors[0][0]);
    }

    public function test_identitas_butuh_izin_data_sensitif(): void
    {
        $this->seedStructure();
        $hr = $this->makeUser($this->tenant, Roles::id(Roles::HR_ADMIN));
        $this->grantModule('employees', Roles::id(Roles::HR_ADMIN), ['is_view', 'is_add', 'is_edit']);

        $this->actingAsSximo($hr, Roles::id(Roles::HR_ADMIN))->upload('employees',
            "employee_number,full_name,company_code,employment_status,join_date,nik_ktp\nE-9,Sari,A1,pkwtt,2022-01-01,3171010101900009\n");

        $errors = json_decode($this->lastBatch()->errors, true);
        $this->assertStringContainsString('izin Data Sensitif', $errors[2][0]);
    }

    public function test_commit_gagal_utuh_bila_data_rujukan_berubah_sesudah_validasi(): void
    {
        $this->seedStructure();
        $this->upload('employees', "employee_number,full_name,company_code,employment_status,join_date,position_code\nE-1,A,A1,pkwtt,2021-01-01,HEAD\nE-2,B,A1,pkwtt,2021-01-01,STAFF\n");
        $batch = $this->lastBatch();
        $this->assertSame('validated', $batch->status);

        DB::table('positions')->where('code', 'STAFF')->update(['deleted_at' => now()]);
        $this->post('/core/import/'.$batch->uuid.'/commit')->assertSessionHas('status', 'error');

        $this->assertSame('failed', DB::table('import_batches')->where('id', $batch->id)->value('status'));
        $this->assertSame(0, DB::table('employees')->count(), 'Baris yang valid pun tidak boleh tersimpan');
    }

    public function test_isolasi_tenant_dan_hak_akses(): void
    {
        $other = $this->makeTenant('TB');
        $companyB = $this->makeCompany($other, 'B1');
        $this->makeUnit($other, 'Unit B');
        DB::table('unit')->where('tenant_id', $other)->update(['unit_code' => 'UNITB']);

        $this->upload('units', "unit_code,unit_name,parent_unit_code,company_code,category_code,type_name\nX1,Anak,UNITB,A1,DIV,Struktural\nX2,Pakai company B,,B1,DIV,Struktural\n");
        $errors = json_decode($this->lastBatch()->errors, true);
        $this->assertArrayHasKey(2, $errors);
        $this->assertArrayHasKey(3, $errors);

        $this->get('/core/import/template/positions')->assertOk()->assertDownload('template-positions.csv');

        $employee = $this->makeUser($this->tenant, Roles::id(Roles::EMPLOYEE));
        $this->actingAsSximo($employee, Roles::id(Roles::EMPLOYEE))->get('/core/import')->assertRedirect('dashboard');
        $this->post('/core/import', ['type' => 'units'])->assertRedirect('dashboard');
    }

    public function test_command_artisan_validasi_dan_commit(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'hcms').'.csv';
        file_put_contents($file, "code,name\nHR,Human Resources\nIT,Information Technology\n");

        $this->assertSame(0, Artisan::call('hcms:import', ['type' => 'job_families', 'file' => $file, '--tenant' => 'TA']));
        $this->assertSame(0, DB::table('job_families')->count());

        $this->assertSame(0, Artisan::call('hcms:import', ['type' => 'job_families', 'file' => $file, '--tenant' => 'TA', '--commit' => true]));
        $this->assertSame(2, DB::table('job_families')->where('tenant_id', $this->tenant)->count());

        // Impor CLI tercatat sebagai batch (riwayat + rekonsiliasi), tapi tidak bisa di-commit dari web
        $batches = DB::table('import_batches')->where('tenant_id', $this->tenant)->orderBy('id')->get();
        $this->assertSame(['validated', 'committed'], $batches->pluck('status')->all());
        $this->assertSame(2, (int) json_decode($batches[1]->reconciliation, true)['created']);
        $this->post('/core/import/'.$batches[0]->uuid.'/commit')->assertStatus(422);
        $this->assertSame(1, Artisan::call('hcms:import', ['type' => 'job_families', 'file' => $file, '--tenant' => 'TIDAKADA']));
        @unlink($file);
    }
}
