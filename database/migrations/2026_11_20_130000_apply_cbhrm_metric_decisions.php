<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan kamus metrik CBHRM K1–K2 (docs/METRIK-CBHRM.md, disetujui 2026-11-17):
 * JFI boleh kosong (data belum cukup, < 80% bobot berskor) dan gap kosong untuk kompetensi
 * tanpa skor berlaku. Kolom coverage = % bobot profil yang memiliki skor berlaku.
 * Nilai lama dihitung ulang dengan `php artisan hcms:competency-recalculate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['job_fit_results', 'job_fit_history'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('jfi', 5, 2)->nullable()->change();
                $t->decimal('coverage', 5, 2)->nullable()->after('jfi')->comment('% bobot profil dengan skor berlaku');
            });
        }
        foreach (['competency_gaps', 'competency_gap_history'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->decimal('gap', 5, 2)->nullable()->change());
        }
    }

    public function down(): void
    {
        foreach (['job_fit_results', 'job_fit_history'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('coverage'));
        }
    }
};
