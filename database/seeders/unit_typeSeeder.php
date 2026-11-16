<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class unit_typeSeeder extends Seeder
{
    use Concerns\WithDefaultTenant;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('unit_type')->truncate();
        DB::table('unit_type')->insert($this->withTenant(array (
  0 => 
  array (
    'id_unit_type' => 1,
    'unit_type' => 'Struktural/Rutin',
    'keterangan' => 'Unit struktural/Rutin/ Non Project',
    'status' => 1,
  ),
  1 => 
  array (
    'id_unit_type' => 2,
    'unit_type' => 'Non Struktural/Non Rutin/Project',
    'keterangan' => 'Adhoc/By Project',
    'status' => 1,
  ),
), false));
    }
}
