<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class tb_users_groupsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('tb_users_groups')->truncate();
        DB::table('tb_users_groups')->insert(array (
  0 => 
  array (
    'id' => 1,
    'user_id' => 1,
    'group_id' => 1,
  ),
));
    }
}
