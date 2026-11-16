<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IconcategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('tb_icon_categories')->truncate();
        DB::table('tb_icon_categories')->insert(array (
  0 => 
  array (
    'id' => 4,
    'name' => 'Bootstrap Icons',
  ),
  1 => 
  array (
    'id' => 3,
    'name' => 'FontAwesome',
  ),
  2 => 
  array (
    'id' => 2,
    'name' => 'Simple Line Icons',
  ),
  3 => 
  array (
    'id' => 1,
    'name' => 'Themify',
  ),
  4 => 
  array (
    'id' => 5,
    'name' => 'zCustom',
  ),
));
    }
}
