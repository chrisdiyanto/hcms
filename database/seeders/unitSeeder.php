<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class unitSeeder extends Seeder
{
    use Concerns\WithDefaultTenant;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('unit')->truncate();
        DB::table('unit')->insert($this->withTenant(array (
  0 => 
  array (
    'id_unit' => 1,
    'slug_unit' => 'vXh472020s',
    'parent_id' => NULL,
    'urutan' => 1,
    'unit_name' => 'Pengurus',
    'unit_code' => '100',
    'id_unit_category' => 1,
    'id_corporate' => 1,
    'unit_addres' => '',
    'unit_explan' => '',
    'id_unit_type' => 1,
    'status' => 1,
  ),
), true));
    }
}
