<?php

use App\Support\Security\Roles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 15 peran blueprint (bagian 3.1) sebagai grup Sximo.
 *
 * - `code`: pengenal stabil untuk kode program (nama grup boleh diubah di UI).
 * - `data_scope`: cakupan data bawaan peran — all (seluruh tenant),
 *   scoped (unit di user_data_scopes/penugasan + bawahannya), own (data sendiri).
 * - level: dipakai Sximo untuk hierarki manajemen pengguna (angka lebih kecil
 *   boleh mengelola pengguna dengan level lebih besar).
 *
 * Grup lama dipertahankan ID-nya: 1 Superadmin → Super Administrator,
 * 2 Administrator → Tenant Administrator, 3 Users → Employee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_groups', function (Blueprint $table) {
            $table->string('code', 40)->nullable()->unique()->after('group_id');
            $table->enum('data_scope', ['all', 'scoped', 'own'])->default('own')->after('level');
            $table->boolean('requires_mfa')->default(false)->after('data_scope');
        });

        Roles::sync();
    }

    public function down(): void
    {
        $legacy = [1 => ['Superadmin', 'Root Superadmin , should be as top level groups', 1], 2 => ['Administrator', 'Administrator level groups', 2], 3 => ['Users', 'User level', 3]];
        foreach ($legacy as $id => [$name, $description, $level]) {
            DB::table('tb_groups')->where('group_id', $id)->update(compact('name', 'description', 'level'));
        }

        $newIds = DB::table('tb_groups')->whereNotNull('code')->whereNotIn('group_id', array_keys($legacy))->pluck('group_id');
        DB::table('tb_groups_access')->whereIn('group_id', $newIds)->delete();
        DB::table('tb_groups')->whereIn('group_id', $newIds)->delete();

        Schema::table('tb_groups', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'data_scope', 'requires_mfa']);
        });
    }
};
