<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kriteria MVP blueprint 22: "keberatan dapat diajukan dan diputus sebelum pengesahan".
 * Alur menjadi: kalibrasi → rating diumumkan (sementara, tahap `appeal`) → keberatan diputus →
 * pengesahan final (snapshot immutable dibuat di sini) → periode ditutup.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE performance_periods MODIFY status ENUM('draft','planning','monitoring','assessment','calibration','appeal','closed') NOT NULL DEFAULT 'draft'");
        Schema::table('performance_periods', function (Blueprint $table) {
            $table->date('appeal_closes_on')->nullable()->after('planning_deadline');
        });
        Schema::table('performance_ratings', function (Blueprint $table) {
            $table->enum('status', ['provisional', 'final'])->default('provisional')->after('is_current');
            $table->timestamp('finalized_at')->nullable()->after('published_by');
            $table->unsignedInteger('finalized_by')->nullable()->after('finalized_at');
        });
        // Rating yang sudah punya snapshot (alur lama) dianggap final
        DB::table('performance_ratings')->whereIn('id', DB::table('performance_snapshots')->pluck('rating_id'))
            ->update(['status' => 'final', 'finalized_at' => DB::raw('published_at')]);
    }

    public function down(): void
    {
        Schema::table('performance_ratings', fn (Blueprint $t) => $t->dropColumn(['status', 'finalized_at', 'finalized_by']));
        Schema::table('performance_periods', fn (Blueprint $t) => $t->dropColumn('appeal_closes_on'));
        DB::table('performance_periods')->where('status', 'appeal')->update(['status' => 'calibration']);
        DB::statement("ALTER TABLE performance_periods MODIFY status ENUM('draft','planning','monitoring','assessment','calibration','closed') NOT NULL DEFAULT 'draft'");
    }
};
