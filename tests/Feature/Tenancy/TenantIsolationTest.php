<?php

namespace Tests\Feature\Tenancy;

use App\Models\Corporate;
use App\Models\Sximo;
use App\Support\Tenancy\Exceptions\MissingTenantContext;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Gerbang keluar Fase 1 (ADR 0001): data tenant A tidak pernah terlihat
 * atau berubah dari tenant B, lewat semua jalur akses Sximo.
 */
class TenantIsolationTest extends TestCase
{
    use InteractsWithTenants;

    private int $tenantA;

    private int $tenantB;

    private int $companyA;

    private int $companyB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = $this->makeTenant('TA');
        $this->tenantB = $this->makeTenant('TB');
        $this->companyA = $this->makeCompany($this->tenantA, 'A1');
        $this->companyB = $this->makeCompany($this->tenantB, 'B1');
    }

    public function test_tes_memakai_database_terpisah(): void
    {
        $this->assertSame('hcms_test', DB::getDatabaseName());
    }

    public function test_get_rows_hanya_mengembalikan_tenant_aktif(): void
    {
        $result = $this->asTenant($this->tenantA, fn () => Corporate::getRows(['page' => 1, 'limit' => 50]));

        $this->assertSame(1, (int) $result['total']);
        $this->assertSame(['A1'], array_column($result['rows'], 'code'));
    }

    public function test_get_row_dan_prev_next_tidak_melihat_tenant_lain(): void
    {
        $this->asTenant($this->tenantA, function () {
            $this->assertSame([], Corporate::getRow($this->companyB));
            $this->assertSame('A1', Corporate::getRow($this->companyA)->code);

            $nav = Corporate::prevNext($this->companyA);
            $this->assertNotContains($this->companyB, [(int) $nav['prev'], (int) $nav['next']]);
        });
    }

    public function test_insert_row_mengisi_tenant_aktif_dan_uuid_meski_input_dipalsukan(): void
    {
        $id = $this->asTenant($this->tenantA, fn () => (new Corporate)->insertRow([
            'code' => 'A2', 'slug_corporate' => 'a2', 'corporate_name' => 'PT A2',
            'tenant_id' => $this->tenantB,
        ], null));

        $row = DB::table('corporate')->where('id_corporate', $id)->first();
        $this->assertSame($this->tenantA, (int) $row->tenant_id);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $row->uuid);
    }

    public function test_insert_row_menolak_update_baris_tenant_lain(): void
    {
        try {
            $this->asTenant($this->tenantA, fn () => (new Corporate)->insertRow(['corporate_name' => 'DIBAJAK'], $this->companyB));
            $this->fail('Update lintas tenant seharusnya ditolak');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }

        $this->assertSame('PT B1', DB::table('corporate')->where('id_corporate', $this->companyB)->value('corporate_name'));
    }

    public function test_update_tidak_bisa_memindahkan_baris_ke_tenant_lain(): void
    {
        $this->asTenant($this->tenantA, fn () => (new Corporate)->insertRow(['corporate_name' => 'PT A1 Baru', 'tenant_id' => $this->tenantB], $this->companyA));

        $row = DB::table('corporate')->where('id_corporate', $this->companyA)->first();
        $this->assertSame('PT A1 Baru', $row->corporate_name);
        $this->assertSame($this->tenantA, (int) $row->tenant_id);
    }

    public function test_eloquent_destroy_dan_find_tidak_menyentuh_tenant_lain(): void
    {
        $this->asTenant($this->tenantA, function () {
            $this->assertNull(Corporate::find($this->companyB));
            Corporate::destroy([$this->companyB]);
        });

        $this->assertTrue(DB::table('corporate')->where('id_corporate', $this->companyB)->exists());
    }

    public function test_delete_data_row_dan_update_data_row_difilter(): void
    {
        $this->asTenant($this->tenantA, function () {
            Sximo::updateDataRow('corporate', 'id_corporate', ['corporate_name' => 'X'], $this->companyB);
            Sximo::deleteDataRow('corporate', 'id_corporate', [$this->companyB]);
        });

        $this->assertSame('PT B1', DB::table('corporate')->where('id_corporate', $this->companyB)->value('corporate_name'));
    }

    public function test_combo_select_difilter_dan_menolak_identifier_tidak_valid(): void
    {
        $this->asTenant($this->tenantA, function () {
            $rows = Sximo::getComboselect(['corporate', 'id_corporate', 'corporate_name']);
            $this->assertSame(['A1'], $rows->pluck('code')->all());

            $this->assertCount(0, Sximo::getComboselect(['corporate where 1=1 --', 'id_corporate', 'code']));
            $this->assertCount(0, Sximo::getComboselect(['corporate', 'id_corporate', 'code'], "WHERE:code:= 'x' or 1=1:A1"));
            $this->assertSame(['A1'], Sximo::getComboselect(['corporate', 'id_corporate', 'code'], 'WHERE:code:=:A1')->pluck('code')->all());
        });
    }

    public function test_helper_global_difilter(): void
    {
        $this->asTenant($this->tenantA, function () {
            $this->assertSame(['A1'], db_get_all_data('corporate')->pluck('code')->all());
            $this->assertSame('A1', getcorporate('code'));
            $this->assertNull(db_get_value('corporate', 'code', 'id_corporate = '.$this->companyB));
        });
    }

    public function test_tanpa_tenant_context_akses_data_gagal_keras(): void
    {
        $this->expectException(MissingTenantContext::class);
        Corporate::getRows(['page' => 1, 'limit' => 10]);
    }

    public function test_tabel_tanpa_tenant_id_tidak_terpengaruh(): void
    {
        $this->assertSame('', TenantScope::sql('tb_module'));
        $this->assertGreaterThan(0, Sximo::getComboselect(['tb_groups', 'group_id', 'name'])->count());
    }
}
