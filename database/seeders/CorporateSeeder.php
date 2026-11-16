<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CorporateSeeder extends Seeder
{
    use Concerns\WithDefaultTenant;

    /**
     * Data dummy corporate + kelengkapan konfigurasi modul Sximo "corporate".
     *
     * Aman dijalankan berulang: data di-upsert berdasarkan id_corporate, tidak di-truncate.
     * id_corporate = 1 wajib ada karena dipakai unit.id_corporate (lihat unitSeeder).
     */
    public function run(): void
    {
        $now = now();

        $rows = array(
            array(
                'id_corporate'      => 1,
                'slug_corporate'    => 'pt-riskcue-indonesia',
                'code'              => 'RCI',
                'corporate_name'    => 'PT RiskCue Indonesia',
                'corporate_address' => 'Jl. Jend. Sudirman Kav. 52-53, Senayan, Kebayoran Baru, Jakarta Selatan 12190',
            ),
            array(
                'id_corporate'      => 2,
                'slug_corporate'    => 'pt-nusantara-energi-persada',
                'code'              => 'NEP',
                'corporate_name'    => 'PT Nusantara Energi Persada',
                'corporate_address' => 'Jl. Pemuda No. 118, Kota Semarang, Jawa Tengah 50132',
            ),
            array(
                'id_corporate'      => 3,
                'slug_corporate'    => 'pt-sinar-logistik-mandiri',
                'code'              => 'SLM',
                'corporate_name'    => 'PT Sinar Logistik Mandiri',
                'corporate_address' => 'Jl. Raya Perak Timur No. 210, Surabaya, Jawa Timur 60164',
            ),
            array(
                'id_corporate'      => 4,
                'slug_corporate'    => 'pt-garuda-finansial-sejahtera',
                'code'              => 'GFS',
                'corporate_name'    => 'PT Garuda Finansial Sejahtera',
                'corporate_address' => 'Jl. Asia Afrika No. 81, Bandung, Jawa Barat 40111',
            ),
            array(
                'id_corporate'      => 5,
                'slug_corporate'    => 'pt-bumi-agro-lestari',
                'code'              => 'BAL',
                'corporate_name'    => 'PT Bumi Agro Lestari',
                'corporate_address' => 'Jl. Jend. Gatot Subroto No. 45, Medan, Sumatera Utara 20112',
            ),
        );

        $tenantId = $this->defaultTenantId();

        foreach ($rows as $row) {
            $id = $row['id_corporate'];
            unset($row['id_corporate']);
            $exists = DB::table('corporate')->where('id_corporate', $id)->exists();
            DB::table('corporate')->updateOrInsert(
                array('id_corporate' => $id),
                $row + array('tenant_id' => $tenantId, 'updated_at' => $now)
                    + ($exists ? array() : array('created_at' => $now, 'uuid' => (string) \Illuminate\Support\Str::uuid7()))
            );
        }

        $this->ensureModuleFields();
    }

    /**
     * Form & halaman detail modul memakai kolom "code", tetapi konfigurasi modul bawaan
     * hanya berisi id/slug/nama/alamat. Tambahkan "code" ke forms & grid agar ikut
     * tersimpan dan tampil di tabel.
     */
    protected function ensureModuleFields(): void
    {
        if (!Schema::hasTable('tb_module')) {
            return;
        }

        $module = DB::table('tb_module')->where('module_name', 'corporate')->first();
        if (!$module) {
            return;
        }

        $config = \SiteHelpers::CF_decode_json($module->module_config);
        if (!is_array($config)) {
            return;
        }

        $changed = false;
        foreach (array('forms', 'grid') as $section) {
            $items = isset($config[$section]) ? $config[$section] : array();
            $fields = array_column($items, 'field');
            if (in_array('code', $fields, true)) {
                continue;
            }

            // Salin struktur entri corporate_name (alias, opsi, dsb.) lalu sesuaikan
            $templateIndex = array_search('corporate_name', $fields, true);
            if ($templateIndex === false) {
                continue;
            }
            $entry = $items[$templateIndex];
            $entry['field'] = 'code';
            $entry['label'] = 'Code';
            if (isset($entry['language']) && is_array($entry['language'])) {
                foreach ($entry['language'] as $lang => $text) {
                    $entry['language'][$lang] = '';
                }
            }
            if ($section === 'forms') {
                $entry['type'] = 'text';
                $entry['required'] = 'required';
            }

            // Letakkan tepat setelah corporate_name dan geser urutan sesudahnya
            array_splice($items, $templateIndex + 1, 0, array($entry));
            foreach ($items as $i => $item) {
                if (array_key_exists('sortlist', $item)) {
                    $items[$i]['sortlist'] = $i;
                }
            }

            $config[$section] = $items;
            $changed = true;
        }

        if ($changed) {
            DB::table('tb_module')
                ->where('module_id', $module->module_id)
                ->update(array('module_config' => \SiteHelpers::CF_encode_json($config)));
        }
    }
}
