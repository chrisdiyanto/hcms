<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow & Notification (blueprint 3.2, 4 modul 5, 17.2 domain 12).
 *
 * - workflow_definitions + workflow_versions: alur per tenant; setiap perubahan
 *   membuat versi baru, instance selalu menunjuk versi yang dipakai saat diajukan.
 * - workflow_instances: satu pengajuan (subjek: tipe + id).
 * - approval_tasks: tugas per langkah per approver (setara workflow_steps); approver
 *   ditentukan dari posisi, termasuk eskalasi saat posisi kosong & delegasi.
 * - approval_actions: jejak audit (siapa, kapan, sebagai posisi apa).
 * - approval_delegations: delegasi approval bertanggal.
 * - employee_change_requests: ESS change request (blueprint 6.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code', 60);
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('definition_id')->constrained('workflow_definitions')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->json('steps');
            $table->boolean('is_current')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['definition_id', 'version']);
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->foreignId('version_id')->constrained('workflow_versions');
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id');
            $table->unsignedInteger('requester_user_id');
            $table->unsignedBigInteger('requester_employee_id')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->unsignedSmallInteger('current_step')->default(0);
            $table->timestamp('submitted_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['requester_user_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('approval_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('instance_id')->constrained('workflow_instances')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_index');
            $table->string('step_name', 150);
            $table->unsignedInteger('approver_user_id');
            $table->unsignedBigInteger('approver_position_id')->nullable()->comment('Posisi yang dijabat approver saat ditugaskan');
            $table->unsignedInteger('delegated_from_user_id')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'escalated', 'cancelled'])->default('pending');
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->index(['approver_user_id', 'status']);
            $table->index(['status', 'due_at']);
            $table->index(['tenant_id', 'instance_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('instance_id')->constrained('workflow_instances')->cascadeOnDelete();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedBigInteger('acted_as_position_id')->nullable();
            $table->enum('action', ['submit', 'approve', 'reject', 'cancel', 'escalate', 'delegate', 'complete']);
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'instance_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedInteger('delegator_user_id');
            $table->unsignedInteger('delegate_user_id');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason', 255);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['delegator_user_id', 'starts_on', 'ends_on']);
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('delegator_user_id')->references('id')->on('tb_users');
            $table->foreign('delegate_user_id')->references('id')->on('tb_users');
        });

        Schema::create('employee_change_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->unsignedInteger('requested_by');
            $table->json('changes')->comment('{kolom: [lama, baru]}');
            $table->string('reason', 500);
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('workflow_instance_id')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });
    }

    public function down(): void
    {
        foreach (['employee_change_requests', 'approval_delegations', 'approval_actions', 'approval_tasks', 'workflow_instances', 'workflow_versions', 'workflow_definitions'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
