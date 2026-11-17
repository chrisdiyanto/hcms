<?php

use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Talent gelombang 1 — penilaian potensi, talent review, dan Nine Box (laporan CBHRM 9).
 *
 * - potential_assessments (+ skor per kriteria): hasil instrumen potensi berversi (P3) per pegawai,
 *   dengan assessor, bukti, tingkat keyakinan, dan masa berlaku. Terpisah dari kinerja & kompetensi.
 * - talent_reviews: siklus review (rating final periode kinerja + potensi tervalidasi), panel, pengesahan.
 * - talent_review_entries: posisi pegawai di Nine Box (usulan otomatis K7 → kotak final hasil
 *   kalibrasi panel, justifikasi wajib bila berubah), flight risk (izin sensitif), tindak lanjut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('potential_assessments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained('potential_instruments');
            $table->unsignedInteger('assessor_user_id');
            $table->date('assessed_at');
            $table->date('valid_until');
            $table->decimal('score', 4, 2);
            $table->enum('level', ['low', 'medium', 'high']);
            $table->boolean('complete');
            $table->enum('confidence', ['low', 'medium', 'high']);
            $table->text('evidence');
            $table->enum('status', ['final', 'cancelled'])->default('final');
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id', 'status', 'assessed_at'], 'pa_tenant_employee_status');
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('potential_assessment_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('assessment_id')->constrained('potential_assessments')->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('potential_criteria');
            $table->string('criterion_code', 30);
            $table->decimal('score', 4, 2);
            $table->unique(['assessment_id', 'criterion_code']);
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('talent_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->date('review_date');
            $table->foreignId('performance_period_id')->constrained('performance_periods');
            $table->unsignedInteger('unit_id')->nullable()->comment('cakupan unit (beserta turunannya); null = seluruh cakupan data pembuat');
            $table->json('panel_user_ids');
            $table->enum('status', ['draft', 'calibration', 'approved'])->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'review_date']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('talent_review_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('review_id')->constrained('talent_reviews')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedInteger('unit_id')->nullable();
            $table->foreignId('rating_id')->nullable()->constrained('performance_ratings')->nullOnDelete();
            $table->string('performance_band', 30)->nullable();
            $table->enum('performance_level', ['low', 'medium', 'high'])->nullable();
            $table->foreignId('potential_assessment_id')->nullable()->constrained('potential_assessments')->nullOnDelete();
            $table->enum('potential_level', ['low', 'medium', 'high'])->nullable();
            $table->unsignedTinyInteger('proposed_box')->nullable();
            $table->unsignedTinyInteger('final_box')->nullable();
            $table->string('justification', 1000)->nullable();
            $table->enum('flight_risk', ['low', 'medium', 'high'])->nullable();
            $table->string('flight_risk_reason', 500)->nullable();
            $table->string('development_action', 500)->nullable();
            $table->unsignedInteger('action_owner_user_id')->nullable();
            $table->date('next_review_date')->nullable();
            $table->enum('data_status', ['complete', 'missing_rating', 'missing_potential', 'missing_both']);
            $table->timestamps();
            $table->unique(['review_id', 'employee_id']);
            $table->index(['tenant_id', 'final_box']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        HcmsModules::sync();
    }

    public function down(): void
    {
        DB::table('tb_menu')->whereIn('module', ['talent/potential-assessments', 'talent/reviews', 'talent/nine-box'])->delete();
        DB::table('tb_module')->whereIn('module_name', ['potentialassessments', 'talentreviews', 'nineboxreport'])->delete();
        foreach (['talent_review_entries', 'talent_reviews', 'potential_assessment_scores', 'potential_assessments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
