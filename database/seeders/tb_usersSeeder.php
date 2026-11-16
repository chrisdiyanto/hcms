<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class tb_usersSeeder extends Seeder
{
    use Concerns\WithDefaultTenant;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('tb_users')->truncate();
        DB::table('tb_users')->insert($this->withTenant(array (
  0 => 
  array (
    'id' => 1,
    'username' => 'Superadmin',
    'nik' => '101',
    'hp' => '08123456789',
    'password' => '$2y$10$Da90vPsJxOLxMwm/YIVrS.oPnjC1i.AxXxUnOsFJl33EUET2za0UG',
    'email' => 'superadmin@mail.com',
    'first_name' => 'Root',
    'last_name' => 'Admin',
    'avatar' => '1.jpg',
    'active' => 1,
    'login_attempt' => 0,
    'last_login' => '2026-10-09 14:17:19',
    'created_at' => '0000-00-00 00:00:00',
    'updated_at' => '0000-00-00 00:00:00',
    'reminder' => NULL,
    'activation' => '',
    'remember_token' => 'M7wC3YnoNzTxaxaifVn06yPwdZ87p1mpPj8Bu9S6sayXBGN9Tld6hvIoSvak',
    'last_activity' => 0,
    'config' => '',
    'entry_by' => NULL,
  ),
), false));

        if (\Schema::hasTable('user_unit')) {
            DB::table('user_unit')->where('id_user', 1)->delete();
            DB::table('user_unit')->insert([
                'id_user'     => 1,
                'id_unit'     => 1,
                'id_position' => 1,
                'start_date'  => now(),
                'end_date'    => null,
            ]);
        }
    }
}
