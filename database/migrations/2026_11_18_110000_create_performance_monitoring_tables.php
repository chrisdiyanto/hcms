<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance Management gelombang 2 — monitoring (blueprint 8.9 langkah 5–7).
 *
 * - performance_updates: realisasi berkala per KPI (angka / status milestone / skor rubrik)
 *   beserta capaian yang dihitung saat dicatat.
 * - performance_evidences: berkas bukti, disimpan privat per tenant (storage/app/tenants/{id}/…).
 * - performance_checkins: check-in berkala & mid-year review (wajib diselesaikan atasan).
 * - kpi_change_requests: perubahan KPI setelah disetujui hanya lewat pengajuan + workflow.
 * - performance_plan_versions: salinan KPI sebelum setiap perubahan (KPI lama tersimpan sebagai versi).
 * KPI yang dihapus lewat change request ditandai `retired_at` (realisasinya tetap ada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('kpi_version')->default(1)->after('revision');
        });
        Schema::table('performance_objectives', function (Blueprint $table) {
            $table->timestamp('retired_at')->nullable()->after('sort_order');
        });

        Schema::create('performance_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('plan_id')->constrained('performance_plans')->cascadeOnDelete();
            $table->foreignId('objective_id')->constrained('performance_objectives')->cascadeOnDelete();
            $table->date('as_of');
            $table->decimal('realization', 18, 4)->nullable();
            $table->json('milestones')->nullable()->comment('[{done, done_date}] sejajar dengan milestone KPI');
            $table->unsignedTinyInteger('score')->nullable()->comment('skor rubrik 1–5 (KPI kualitatif)');
            $table->decimal('achievement', 6, 2)->comment('capaian setelah cap, saat dicatat');
            $table->text('note')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->index(['tenant_id', 'plan_id']);
            $table->index(['objective_id', 'as_of']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('performance_evidences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('plan_id')->constrained('performance_plans')->cascadeOnDelete();
            $table->foreignId('update_id')->constrained('performance_updates')->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('path', 500);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->unsignedInteger('uploaded_by');
            $table->timestamps();
            $table->index(['tenant_id', 'plan_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('performance_checkins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('plan_id')->constrained('performance_plans')->cascadeOnDelete();
            $table->enum('type', ['regular', 'midyear']);
            $table->date('checkin_date');
            $table->text('employee_notes')->nullable();
            $table->text('manager_notes')->nullable();
            $table->enum('status', ['open', 'completed'])->default('open');
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'plan_id', 'type']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('kpi_change_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('plan_id')->constrained('performance_plans')->cascadeOnDelete();
            $table->json('items')->comment('usulan KPI lengkap; item dengan id = KPI yang diubah');
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('workflow_instance_id')->nullable();
            $table->unsignedSmallInteger('from_version');
            $table->unsignedSmallInteger('to_version')->nullable();
            $table->unsignedInteger('requested_by');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'plan_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('performance_plan_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('plan_id')->constrained('performance_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->json('objectives');
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('change_request_id')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['plan_id', 'version']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });
    }

    public function down(): void
    {
        foreach (['performance_plan_versions', 'kpi_change_requests', 'performance_checkins', 'performance_evidences', 'performance_updates'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('performance_objectives', fn (Blueprint $t) => $t->dropColumn('retired_at'));
        Schema::table('performance_plans', fn (Blueprint $t) => $t->dropColumn('kpi_version'));
    }
};
