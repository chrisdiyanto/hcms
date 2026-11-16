<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('user_unit')) {
            Schema::table('user_unit', function (Blueprint $table) {
                if (!Schema::hasColumn('user_unit', 'id_position')) {
                    $table->foreignId('id_position')->nullable()->after('id_unit');
                }
            });

            DB::statement('ALTER TABLE `user_unit` MODIFY `id_unit` BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE `user_unit` MODIFY `id_position` BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE `user_unit` MODIFY `start_date` DATETIME NULL');
            DB::statement('ALTER TABLE `user_unit` MODIFY `end_date` DATETIME NULL');
        }

        // Migrate existing id_unit and id_position from tb_users into user_unit
        if (Schema::hasTable('tb_users') && Schema::hasTable('user_unit')) {
            $hasIdUnit = Schema::hasColumn('tb_users', 'id_unit');
            $hasIdPosition = Schema::hasColumn('tb_users', 'id_position');

            if ($hasIdUnit || $hasIdPosition) {
                $users = DB::table('tb_users')->get();
                foreach ($users as $user) {
                    $unitId = $hasIdUnit ? $user->id_unit : null;
                    $posId  = $hasIdPosition ? $user->id_position : null;

                    if (!is_null($unitId) || !is_null($posId)) {
                        $existing = DB::table('user_unit')
                            ->where('id_user', $user->id)
                            ->where(function ($q) use ($unitId) {
                                if (!is_null($unitId)) {
                                    $q->where('id_unit', $unitId)->orWhereNull('id_unit');
                                } else {
                                    $q->whereNull('id_unit');
                                }
                            })
                            ->first();

                        if ($existing) {
                            DB::table('user_unit')
                                ->where('id_user_unit', $existing->id_user_unit)
                                ->update([
                                    'id_unit'     => $unitId ?? $existing->id_unit,
                                    'id_position' => $posId ?? ($existing->id_position ?? null),
                                ]);
                        } else {
                            DB::table('user_unit')->insert([
                                'id_user'     => $user->id,
                                'id_unit'     => $unitId,
                                'id_position' => $posId,
                                'start_date'  => now(),
                                'end_date'    => null,
                            ]);
                        }
                    }
                }

                Schema::table('tb_users', function (Blueprint $table) use ($hasIdUnit, $hasIdPosition) {
                    $cols = [];
                    if ($hasIdUnit) {
                        $cols[] = 'id_unit';
                    }
                    if ($hasIdPosition) {
                        $cols[] = 'id_position';
                    }
                    if (!empty($cols)) {
                        $table->dropColumn($cols);
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tb_users')) {
            Schema::table('tb_users', function (Blueprint $table) {
                if (!Schema::hasColumn('tb_users', 'id_unit')) {
                    $table->foreignId('id_unit')->nullable()->after('username');
                }
                if (!Schema::hasColumn('tb_users', 'id_position')) {
                    $table->foreignId('id_position')->nullable()->after('id_unit');
                }
            });

            if (Schema::hasTable('user_unit')) {
                $userUnits = DB::table('user_unit')->get();
                foreach ($userUnits as $uu) {
                    DB::table('tb_users')
                        ->where('id', $uu->id_user)
                        ->whereNull('id_unit')
                        ->update([
                            'id_unit'     => $uu->id_unit,
                            'id_position' => $uu->id_position ?? null,
                        ]);
                }
            }
        }
    }
};
