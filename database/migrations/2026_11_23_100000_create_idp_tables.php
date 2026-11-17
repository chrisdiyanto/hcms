<?php

use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rilis 2 gelombang 2 — katalog intervensi dan IDP (laporan CBHRM 6).
 *
 * - development_interventions: katalog pelatihan, e-learning, coaching, mentoring, penugasan, sertifikasi.
 * - idps: satu rencana pengembangan per pegawai per tahun (draft → diajukan → disetujui atasan lewat
 *   workflow → selesai; dikembalikan = kembali dapat diubah).
 * - idp_activities: aktivitas dengan rujukan ke kebutuhan TNA (training_needs) dan saran IDP asal
 *   (competency_idp_suggestions) agar rantai gap → saran/TNA → IDP dapat ditelusuri (P6).
 */
return new class extends Migration
{
    private const TYPES = ['training', 'elearning', 'coaching', 'mentoring', 'assignment', 'certification', 'other'];

    public function up(): void
    {
        Schema::create('development_interventions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->enum('type', self::TYPES);
            $table->string('provider', 150)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('competency_id')->nullable()->constrained('competencies')->nullOnDelete();
            $table->unsignedTinyInteger('target_level')->nullable();
            $table->decimal('duration_hours', 6, 1)->nullable();
            $table->decimal('cost_per_participant', 14, 2)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->text('prerequisites')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'code']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('idps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->text('career_goal')->nullable();
            $table->enum('status', ['draft', 'submitted', 'returned', 'approved', 'completed', 'cancelled'])->default('draft');
            $table->string('return_note', 1000)->nullable();
            $table->unsignedBigInteger('workflow_instance_id')->nullable();
            $table->unsignedSmallInteger('revision')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'period_year', 'status']);
            $table->index(['employee_id', 'period_year']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('idp_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('idp_id')->constrained('idps')->cascadeOnDelete();
            $table->foreignId('training_need_id')->nullable()->constrained('training_needs')->nullOnDelete();
            $table->foreignId('idp_suggestion_id')->nullable()->constrained('competency_idp_suggestions')->nullOnDelete();
            $table->foreignId('competency_id')->nullable()->constrained('competencies')->nullOnDelete();
            $table->string('competency_code', 40)->nullable();
            $table->decimal('current_level', 4, 2)->nullable();
            $table->unsignedTinyInteger('target_level')->nullable();
            $table->string('goal', 255);
            $table->foreignId('intervention_id')->nullable()->constrained('development_interventions')->nullOnDelete();
            $table->enum('activity_type', self::TYPES);
            $table->string('title', 200);
            $table->date('due_date');
            $table->enum('status', ['planned', 'in_progress', 'completed', 'deferred', 'cancelled'])->default('planned');
            $table->decimal('cost_estimate', 14, 2)->nullable();
            $table->decimal('cost_actual', 14, 2)->nullable();
            $table->text('evidence')->nullable();
            $table->string('certification_name', 200)->nullable();
            $table->date('certification_expiry')->nullable();
            $table->date('completed_at')->nullable();
            $table->string('employee_note', 1000)->nullable();
            $table->string('manager_note', 1000)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'competency_code']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        HcmsModules::sync();
    }

    public function down(): void
    {
        DB::table('tb_menu')->whereIn('module', ['development/interventions', 'development/idps', 'ess/idp'])->delete();
        DB::table('tb_module')->whereIn('module_name', ['interventions', 'idpadmin'])->delete();
        Schema::dropIfExists('idp_activities');
        Schema::dropIfExists('idps');
        Schema::dropIfExists('development_interventions');
    }
};
