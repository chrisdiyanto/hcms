<?php

namespace Database\Seeders;

use App\Support\Security\Roles;
use Illuminate\Database\Seeder;

/** Peran blueprint; jalankan sesudah tb_groupsSeeder (yang mengosongkan tb_groups). */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        Roles::sync();
    }
}
