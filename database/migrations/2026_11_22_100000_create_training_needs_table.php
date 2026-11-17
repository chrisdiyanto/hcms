<?php

use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rilis 2 gelombang 1 — Training Need Analysis (laporan CBHRM 5).
 *
 * Satu baris = satu kebutuhan pegawai × kompetensi/topik × tahun rencana, dengan sumber
 * (gap kompetensi, kinerja/PIP, prioritas organisasi, permintaan) dan prioritas K5.
 * Status: suggested (saran otomatis, BELUM dihitung sebagai TNA) → proposed → approved/rejected
 * → planned (sudah ada intervensi) → closed. dedupe_key mencegah saran ganda dari sumber yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_needs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->foreignId('competency_id')->nullable()->constrained('competencies')->nullOnDelete();
            $table->string('competency_code', 40)->nullable();
            $table->string('topic', 200);
            $table->enum('source', ['gap', 'performance', 'organization', 'request']);
            $table->string('source_ref_type', 60)->nullable();
            $table->unsignedBigInteger('source_ref_id')->nullable();
            $table->string('dedupe_key', 120)->nullable()->comment('saran otomatis: sumber + rujukan');
            $table->decimal('current_level', 4, 2)->nullable();
            $table->unsignedTinyInteger('target_level')->nullable();
            $table->unsignedTinyInteger('criticality')->nullable();
            $table->decimal('gap', 5, 2)->nullable();
            $table->foreignId('strategic_objective_id')->nullable()->constrained('strategic_objectives')->nullOnDelete();
            $table->decimal('priority_score', 5, 2)->nullable()->comment('K5: kritikalitas × |gap| × faktor strategis');
            $table->enum('priority', ['high', 'medium', 'low']);
            $table->text('reason');
            $table->text('evidence')->nullable();
            $table->text('impact_if_unaddressed')->nullable();
            $table->string('suggested_intervention', 255)->nullable();
            $table->decimal('estimated_cost', 14, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedInteger('owner_user_id')->nullable()->comment('penanggung jawab tindak lanjut');
            $table->enum('status', ['suggested', 'proposed', 'approved', 'rejected', 'planned', 'closed']);
            $table->unsignedInteger('requested_by')->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'period_year', 'dedupe_key'], 'tn_employee_year_dedupe');
            $table->index(['tenant_id', 'period_year', 'status'], 'tn_tenant_year_status');
            $table->index(['tenant_id', 'competency_code']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        HcmsModules::sync();
    }

    public function down(): void
    {
        DB::table('tb_menu')->whereIn('module', ['development/needs', 'development/needs/report', 'ess/training-requests'])->delete();
        DB::table('tb_module')->whereIn('module_name', ['trainingneeds', 'tnareport'])->delete();
        Schema::dropIfExists('training_needs');
    }
};
