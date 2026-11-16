<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class tb_groupsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('tb_groups')->truncate();
        DB::table('tb_groups')->insert(array (
  0 => 
  array (
    'group_id' => 1,
    'name' => 'Superadmin',
    'description' => 'Root Superadmin , should be as top level groups',
    'level' => 1,
  ),
  1 => 
  array (
    'group_id' => 2,
    'name' => 'Administrator',
    'description' => 'Administrator level groups',
    'level' => 2,
  ),
  2 => 
  array (
    'group_id' => 3,
    'name' => 'Users',
    'description' => 'User level',
    'level' => 3,
  ),
));
    }
}
