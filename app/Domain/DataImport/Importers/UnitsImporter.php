<?php

namespace App\Domain\DataImport\Importers;

use App\Domain\DataImport\Importer;
use App\Domain\Organization\Services\OrganizationHistory;
use App\Domain\Organization\Services\OrgUnitClosure;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Unit organisasi (tabel `unit`); parent dirujuk lewat kode dan harus muncul lebih dulu atau sudah ada. */
final class UnitsImporter extends Importer
{
    public function title(): string
    {
        return 'Unit organisasi';
    }

    public function columns(): array
    {
        return [
            'unit_code' => ['required' => true, 'description' => 'Kode unit (kunci impor)', 'example' => 'DIV-KRD'],
            'unit_name' => ['required' => true, 'description' => 'Nama unit', 'example' => 'Divisi Kredit'],
            'parent_unit_code' => ['description' => 'Kode unit induk; kosong = unit puncak', 'example' => 'DIR-BISNIS'],
            'company_code' => ['required' => true, 'description' => 'Kode company', 'example' => 'RCI'],
            'category_code' => ['required' => true, 'description' => 'Kode kategori unit (unit_category_code)', 'example' => 'DIV'],
            'type_name' => ['required' => true, 'description' => 'Nama tipe unit (unit_type)', 'example' => 'Divisi'],
            'address' => ['description' => 'Alamat'],
            'description' => ['description' => 'Keterangan'],
            'active' => ['description' => 'Ya/Tidak (default Ya)', 'example' => 'Ya'],
        ];
    }

    public function process(array $row, int $line): string
    {
        $row = $this->validate(self::nullable($row), [
            'unit_code' => ['required', 'max:255'], 'unit_name' => ['required', 'max:255'], 'parent_unit_code' => ['nullable', 'max:255'],
            'company_code' => ['required'], 'category_code' => ['required'], 'type_name' => ['required'],
            'address' => ['nullable', 'max:1000'], 'description' => ['nullable', 'max:2000'], 'active' => ['nullable'],
        ]);

        $existing = $this->idByCode('unit', $row['unit_code'], 'unit_code', 'id_unit', false);
        $parent = null;
        if (! empty($row['parent_unit_code'])) {
            if (strtoupper($row['parent_unit_code']) === strtoupper($row['unit_code'])) {
                $this->fail('Unit tidak boleh menjadi induk dirinya sendiri.');
            }
            $parent = $this->requireId('unit', $row['parent_unit_code'], 'Unit induk', 'unit_code', 'id_unit', false);
        }

        $data = [
            'unit_name' => $row['unit_name'],
            'unit_code' => strtoupper($row['unit_code']),
            'parent_id' => $parent,
            'id_corporate' => $this->requireId('corporate', $row['company_code'], 'Company', 'code', 'id_corporate', false),
            'id_unit_category' => $this->requireId('unit_category', $row['category_code'], 'Kategori unit', 'unit_category_code', 'id_unit_category', false),
            'id_unit_type' => $this->requireId('unit_type', $row['type_name'], 'Tipe unit', 'unit_type', 'id_unit_type', false),
            'unit_addres' => $row['address'] ?? '',
            'unit_explan' => $row['description'] ?? '',
            'status' => self::bool($row['active'] ?? null, true) ? 1 : 0,
        ];

        if ($existing) {
            $before = OrganizationHistory::snapshot($existing);
            TenantScope::table('unit')->where('id_unit', $existing)->update($data);
            OrganizationHistory::record($existing, $before);
            AuditLog::updated('unit', $existing, $before, OrganizationHistory::snapshot($existing), 'Impor data');

            return 'updated';
        }

        $data['slug_unit'] = Str::limit(Str::slug($row['unit_name']), 200, '').'-'.strtolower(Str::slug($row['unit_code']));
        $data = TenantScope::fillForInsert('unit', $data);
        $id = (int) DB::table('unit')->insertGetId($data);
        OrganizationHistory::record($id, null);
        AuditLog::created('unit', $id, $data, 'Impor data');
        $this->remember('unit', $row['unit_code'], $id);

        return 'created';
    }

    public function finalize(): array
    {
        OrgUnitClosure::rebuild(TenantScope::tenantId());

        return [];
    }

    public function countInDatabase(): int
    {
        return TenantScope::table('unit')->count();
    }
}
