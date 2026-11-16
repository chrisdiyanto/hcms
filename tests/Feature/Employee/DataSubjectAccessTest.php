<?php

namespace Tests\Feature\Employee;

use App\Domain\Employee\Services\EmployeeIdentity;
use App\Support\Security\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/** Hak subjek data (Fase 4): pegawai dapat mengakses (unduh) dan mengoreksi (ESS) data pribadinya. */
class DataSubjectAccessTest extends TestCase
{
    use InteractsWithTenants;

    public function test_pegawai_mengunduh_hanya_data_miliknya_dan_tercatat(): void
    {
        Roles::flushCache();
        $tenant = $this->makeTenant('TA');
        $company = $this->makeCompany($tenant, 'A1');
        $make = function (string $name, string $nik) use ($tenant, $company) {
            $user = $this->makeUser($tenant, Roles::id(Roles::EMPLOYEE));
            $id = DB::table('employees')->insertGetId(['tenant_id' => $tenant, 'uuid' => (string) Str::uuid7(), 'company_id' => $company, 'user_id' => $user->id,
                'employee_number' => 'E'.Str::upper(Str::random(5)), 'full_name' => $name, 'employment_status' => 'pkwtt', 'join_date' => '2022-01-03', 'status' => 'active', 'created_at' => now()]);
            $this->asTenant($tenant, fn () => EmployeeIdentity::save($id, ['nik_ktp' => $nik]));
            DB::table('employee_families')->insert(['tenant_id' => $tenant, 'employee_id' => $id, 'name' => 'Keluarga '.$name,
                'relationship' => 'spouse', 'created_at' => now()]);

            return [$user, $id];
        };
        [$rina, $rinaId] = $make('Rina Analis', '3171010101900001');
        $make('Budi Lain', '3171010101900002');

        $this->actingAsSximo($rina, Roles::id(Roles::EMPLOYEE))->get('/ess/profile')->assertOk()->assertSee('Unduh data saya');
        $json = $this->get('/ess/my-data')->assertOk()->assertDownload()->streamedContent();
        $data = json_decode($json, true);

        $this->assertSame('Rina Analis', $data['pegawai']['full_name']);
        $this->assertSame('3171010101900001', $data['identitas']['nik_ktp'], 'identitas milik sendiri didekripsi');
        $this->assertSame(['Keluarga Rina Analis'], array_column($data['keluarga'], 'name'));
        $this->assertStringNotContainsString('Budi Lain', $json);
        $this->assertStringNotContainsString('3171010101900002', $json);
        $this->assertArrayNotHasKey('tenant_id', $data['pegawai']);

        $this->assertSame(1, DB::table('data_export_logs')->where('module', 'ess_my_data')->where('user_id', $rina->id)->count());
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', 'employees')->where('auditable_id', (string) $rinaId)
            ->where('event', 'sensitive_viewed')->exists());

        // Akun tanpa data pegawai tidak mendapat apa pun
        $loner = $this->makeUser($tenant, Roles::id(Roles::EMPLOYEE));
        $this->actingAsSximo($loner, Roles::id(Roles::EMPLOYEE))->get('/ess/my-data')->assertNotFound();
    }
}
