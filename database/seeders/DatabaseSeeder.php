<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        
        // $this->call(tb_groupsSeeder::class);

        Schema::disableForeignKeyConstraints();

        $this->call([
            tb_groupsSeeder::class,
            tb_usersSeeder::class,
            tb_users_groupsSeeder::class,
            tb_menuSeeder::class,
            tb_moduleSeeder::class,
            tb_groups_accessSeeder::class,
            RolesSeeder::class,
            tb_pagesSeeder::class,
            IconcategoriesSeeder::class,
            IconSeeder::class,
            unit_categorySeeder::class,
            unit_typeSeeder::class,
            CorporateSeeder::class,
            unitSeeder::class,
        ]);

        Schema::enableForeignKeyConstraints();
        
    }
}
