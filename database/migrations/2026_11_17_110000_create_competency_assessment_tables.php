<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Competency Management gelombang 2 (blueprint 7.3–7.5, 17.2 domain 4).
 *
 * - competency_assessments: satu asesmen per pegawai, merujuk versi kamus & posisi
 *   saat dinilai; berlaku 24 bulan sejak ditutup.
 * - assessment_raters / competency_assessment_details: penilai per kelompok
 *   (self, manager, peer, subordinate, hr) dan skor 1–5 per kompetensi;
 *   hasil assessment center disimpan dengan rater_type = assessment_center.
 * - employee_competencies: skor terkini per kompetensi (kode) — hasil penggabungan.
 * - competency_gaps & job_fit_results: snapshot terhadap posisi saat asesmen ditutup.
 * - competency_idp_suggestions: draf item IDP untuk gap negatif kompetensi kritis
 *   (diambil modul Learning & Development di Rilis 2).
 * Bobot penilai disimpan di tenant_settings (`competency.rater_weights`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competency_assessments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('framework_version_id')->constrained('competency_framework_versions');
            $table->foreignId('position_id')->constrained('positions');
            $table->string('title', 150);
            $table->enum('status', ['open', 'closed', 'cancelled'])->default('open');
            $table->date('due_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('closed_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('assessment_raters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('assessment_id')->constrained('competency_assessments')->cascadeOnDelete();
            $table->unsignedInteger('rater_user_id');
            $table->enum('rater_type', ['self', 'manager', 'peer', 'subordinate', 'hr', 'assessment_center']);
            $table->enum('status', ['pending', 'submitted'])->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['assessment_id', 'rater_user_id', 'rater_type']);
            $table->index(['rater_user_id', 'status']);
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('rater_user_id')->references('id')->on('tb_users');
        });

        Schema::create('competency_assessment_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('assessment_id')->constrained('competency_assessments')->cascadeOnDelete();
            $table->foreignId('rater_id')->constrained('assessment_raters')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies');
            $table->unsignedTinyInteger('score');
            $table->text('evidence')->nullable();
            $table->timestamps();
            $table->unique(['rater_id', 'competency_id']);
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('employee_competencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('competency_code', 40);
            $table->foreignId('competency_id')->constrained('competencies');
            $table->decimal('score', 4, 2);
            $table->enum('source', ['raters', 'assessment_center']);
            $table->foreignId('assessment_id')->constrained('competency_assessments');
            $table->date('assessed_at');
            $table->date('valid_until');
            $table->json('breakdown')->nullable()->comment('rata-rata & jumlah penilai per kelompok');
            $table->timestamps();
            $table->unique(['employee_id', 'competency_code']);
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('competency_gaps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies');
            $table->string('competency_code', 40);
            $table->unsignedTinyInteger('required_level');
            $table->unsignedTinyInteger('criticality');
            $table->decimal('actual', 4, 2)->nullable();
            $table->decimal('gap', 5, 2);
            $table->timestamp('computed_at');
            $table->unique(['employee_id', 'position_id', 'competency_code']);
            $table->index(['tenant_id', 'competency_code']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('job_fit_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->decimal('jfi', 5, 2);
            $table->string('band', 20);
            $table->boolean('complete');
            $table->foreignId('assessment_id')->nullable()->constrained('competency_assessments');
            $table->timestamp('computed_at');
            $table->unique(['employee_id', 'position_id']);
            $table->index(['tenant_id', 'band']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('competency_idp_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies');
            $table->string('competency_code', 40);
            $table->unsignedTinyInteger('current_level');
            $table->unsignedTinyInteger('target_level');
            $table->foreignId('assessment_id')->constrained('competency_assessments');
            $table->enum('status', ['draft', 'accepted', 'dismissed'])->default('draft');
            $table->timestamps();
            $table->unique(['employee_id', 'competency_code', 'assessment_id'], 'idp_sugg_unique');
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });
    }

    public function down(): void
    {
        foreach (['competency_idp_suggestions', 'job_fit_results', 'competency_gaps', 'employee_competencies', 'competency_assessment_details', 'assessment_raters', 'competency_assessments'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
