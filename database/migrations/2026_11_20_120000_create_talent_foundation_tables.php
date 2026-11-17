<?php

use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prasyarat P3 laporan CBHRM: data yang dibutuhkan talent review, talent pool, dan suksesi.
 *
 * - positions: tingkat dampak & alasan untuk posisi kritis (penanda is_critical sudah ada sejak Fase 1).
 * - employee_career_aspirations: aspirasi karier dari Layanan Mandiri; setiap pembaruan = baris baru
 *   (is_current), sehingga riwayat minat dapat ditelusuri.
 * - potential_instruments + potential_criteria: kamus instrumen asesmen potensi berversi
 *   (draft → berlaku → pensiun), terpisah dari kinerja & kompetensi. Penilaian potensi pegawai
 *   dibangun bersama talent review (laporan 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->unsignedTinyInteger('critical_impact')->nullable()->after('is_critical')->comment('1 sedang, 2 tinggi, 3 sangat tinggi');
            $table->string('critical_reason', 500)->nullable()->after('critical_impact');
        });

        Schema::create('employee_career_aspirations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('target_job_family_id')->nullable()->constrained('job_families')->nullOnDelete();
            $table->foreignId('target_job_id')->nullable()->constrained('jobs_catalog')->nullOnDelete();
            $table->string('target_role', 150)->nullable()->comment('peran yang diminati bila tidak ada di katalog');
            $table->enum('horizon', ['1_2', '3_5', '5_plus']);
            $table->enum('mobility', ['yes', 'no', 'discuss'])->comment('bersedia pindah lokasi');
            $table->text('interests')->nullable();
            $table->text('development_needs')->nullable();
            $table->boolean('is_current')->default(true);
            $table->unsignedInteger('submitted_by');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id', 'is_current'], 'eca_tenant_employee_current');
            $table->index(['tenant_id', 'target_job_family_id'], 'eca_tenant_family');
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('potential_instruments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->unsignedSmallInteger('version');
            $table->string('name', 150);
            $table->enum('status', ['draft', 'published', 'retired'])->default('draft');
            $table->unsignedTinyInteger('scale_max')->default(5);
            $table->decimal('threshold_medium', 4, 2)->default(2.50)->comment('skor tertimbang ≥ nilai ini = potensi sedang');
            $table->decimal('threshold_high', 4, 2)->default(3.75)->comment('skor tertimbang ≥ nilai ini = potensi tinggi');
            $table->unsignedTinyInteger('validity_months')->default(24);
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('published_by')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'version']);
            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('potential_criteria', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('instrument_id')->constrained('potential_instruments')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->text('description');
            $table->text('indicators')->comment('indikator perilaku, satu per baris');
            $table->unsignedTinyInteger('weight')->comment('bobot %, total 100 per instrumen');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['instrument_id', 'code']);
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        HcmsModules::sync();
    }

    public function down(): void
    {
        DB::table('tb_menu')->whereIn('module', ['talent/potential-instruments', 'ess/career'])->delete();
        Schema::dropIfExists('potential_criteria');
        Schema::dropIfExists('potential_instruments');
        Schema::dropIfExists('employee_career_aspirations');
        Schema::table('positions', fn (Blueprint $table) => $table->dropColumn(['critical_impact', 'critical_reason']));
    }
};
