<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance Management gelombang 1 (blueprint 8.2–8.9, 17.2 domain 5).
 *
 * - performance_policies: kebijakan berversi (bobot What/How/Kontribusi, batas KPI, cap,
 *   tabel konversi 1–5, band rating, prorata) disimpan sebagai JSON `config`.
 * - performance_periods: periode penilaian; saat dibuka, salinan kebijakan dikunci
 *   (`policy_snapshot`) sehingga perubahan kebijakan tidak mengubah periode berjalan.
 * - strategic_objectives: sasaran strategis yang di-cascade korporat → direktorat → divisi → tim.
 * - performance_plans: rencana kinerja per penugasan (bukan per pegawai) dalam satu periode.
 * - performance_objectives: KPI individu (tipe normalisasi, target, bobot, cap, keterkaitan
 *   ke sasaran strategis / KPI atasan beserta bobot kontribusi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->unsignedSmallInteger('version');
            $table->string('name', 150);
            $table->enum('status', ['draft', 'active', 'retired'])->default('draft');
            $table->json('config');
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('published_by')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'version']);
            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('performance_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'planning', 'monitoring', 'assessment', 'calibration', 'closed'])->default('draft');
            $table->foreignId('policy_id')->nullable()->constrained('performance_policies');
            $table->json('policy_snapshot')->nullable()->comment('kebijakan yang dikunci saat periode dibuka');
            $table->date('planning_deadline')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->unsignedInteger('opened_by')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('strategic_objectives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->foreignId('period_id')->constrained('performance_periods')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('strategic_objectives')->nullOnDelete();
            $table->enum('level', ['corporate', 'directorate', 'division', 'team']);
            $table->unsignedInteger('unit_id')->nullable();
            $table->string('code', 30);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('measure', 255)->nullable()->comment('indikator / target ringkas');
            $table->decimal('contribution_weight', 5, 2)->nullable()->comment('bobot kontribusi ke induk (%)');
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['period_id', 'code']);
            $table->index(['tenant_id', 'period_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('unit_id')->references('id_unit')->on('unit');
        });

        Schema::create('performance_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->foreignId('period_id')->constrained('performance_periods');
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('assignment_id')->constrained('employee_assignments');
            $table->foreignId('position_id')->constrained('positions');
            $table->date('start_date')->comment('bagian periode yang dicakup penugasan ini');
            $table->date('end_date');
            $table->enum('status', ['draft', 'submitted', 'approved', 'returned'])->default('draft');
            $table->unsignedBigInteger('workflow_instance_id')->nullable();
            $table->unsignedSmallInteger('revision')->default(0);
            $table->text('return_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['period_id', 'assignment_id']);
            $table->index(['tenant_id', 'period_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('performance_objectives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('plan_id')->constrained('performance_plans')->cascadeOnDelete();
            $table->foreignId('strategic_objective_id')->nullable()->constrained('strategic_objectives')->nullOnDelete();
            $table->foreignId('parent_objective_id')->nullable()->constrained('performance_objectives')->nullOnDelete()
                ->comment('KPI atasan yang di-cascade');
            $table->decimal('contribution_weight', 5, 2)->nullable()->comment('kontribusi ke KPI induk (%)');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('kpi_type', ['higher', 'lower', 'range', 'milestone', 'qualitative']);
            $table->string('unit', 30)->nullable();
            $table->decimal('target', 18, 4)->nullable();
            $table->decimal('range_min', 18, 4)->nullable();
            $table->decimal('range_max', 18, 4)->nullable();
            $table->json('milestones')->nullable()->comment('[{name, weight, due_date}]');
            $table->json('rubric')->nullable()->comment('deskripsi level 1–5 untuk KPI kualitatif');
            $table->decimal('weight', 5, 2);
            $table->decimal('cap', 5, 2)->nullable()->comment('kosong = cap kebijakan');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'plan_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });
    }

    public function down(): void
    {
        foreach (['performance_objectives', 'performance_plans', 'strategic_objectives', 'performance_periods', 'performance_policies'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
