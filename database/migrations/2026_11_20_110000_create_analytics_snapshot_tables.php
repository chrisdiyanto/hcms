<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot analitik bulanan (prasyarat P2 laporan CBHRM).
 *
 * analytics_snapshots: satu baris per tenant × bulan × versi. Menjalankan ulang bulan yang sama
 * membuat versi baru dan menandai versi lama "superseded" — angka lama tidak ditimpa.
 * analytics_unit_metrics: format panjang (unit × metrik), semua nilai ADITIF (jumlah & total),
 * sehingga agregasi lintas unit/cakupan data cukup SUM dan rata-rata dihitung saat dibaca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->char('period', 7)->comment('YYYY-MM');
            $table->date('as_of')->comment('akhir bulan; posisi data per tanggal ini');
            $table->unsignedSmallInteger('version')->default(1);
            $table->unsignedSmallInteger('definitions_version');
            $table->boolean('reconstructed')->default(false)->comment('dibuat > 7 hari setelah akhir bulan dari data efektif-bertanggal');
            $table->unsignedInteger('row_count')->default(0);
            $table->timestamp('generated_at');
            $table->unsignedInteger('generated_by')->nullable()->comment('null = penjadwal');
            $table->timestamp('superseded_at')->nullable();
            $table->unique(['tenant_id', 'period', 'version']);
            $table->index(['tenant_id', 'superseded_at', 'period']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('analytics_unit_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('snapshot_id')->constrained('analytics_snapshots')->cascadeOnDelete();
            $table->unsignedInteger('unit_id')->nullable()->comment('unit posisi primer per as_of; null = tanpa penempatan');
            $table->string('metric', 60);
            $table->decimal('value', 16, 4);
            $table->unique(['snapshot_id', 'unit_id', 'metric']);
            $table->index(['tenant_id', 'metric']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        DB::unprepared('DROP TRIGGER IF EXISTS analytics_unit_metrics_no_update');
        DB::unprepared("CREATE TRIGGER analytics_unit_metrics_no_update BEFORE UPDATE ON analytics_unit_metrics FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'analytics_unit_metrics tidak dapat diubah; buat versi snapshot baru'");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS analytics_unit_metrics_no_update');
        Schema::dropIfExists('analytics_unit_metrics');
        Schema::dropIfExists('analytics_snapshots');
    }
};
