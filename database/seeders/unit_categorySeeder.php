<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class unit_categorySeeder extends Seeder
{
    use Concerns\WithDefaultTenant;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('unit_category')->truncate();
        DB::table('unit_category')->insert($this->withTenant(array (
  0 => 
  array (
    'id_unit_category' => 1,
    'slug_unit_category' => 'aMsr23942p',
    'unit_category' => 'Kantor Pusat',
    'unit_category_code' => 'KP',
    'unit_category_explan' => 'Divisi/Bagian di kantor pusat',
  ),
  1 => 
  array (
    'id_unit_category' => 2,
    'slug_unit_category' => 'hun58osrpo',
    'unit_category' => 'Unit Usaha',
    'unit_category_code' => 'UU',
    'unit_category_explan' => 'Unit Bisnis/Unit Usaha',
  ),
), false));
    }
}
