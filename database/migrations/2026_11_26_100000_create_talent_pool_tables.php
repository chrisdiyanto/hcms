<?php

use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Talent gelombang 2 — talent pool (laporan CBHRM 10).
 *
 * - talent_pools: tujuan, sasaran (rumpun jabatan dan/atau posisi), kriteria masuk/keluar, pemilik, siklus tinjau.
 * - talent_pool_members: nominasi (dari talent review sah atau manual) → aktif/ditolak → keluar; kesiapan
 *   ditetapkan saat persetujuan/tinjau ulang; keanggotaan bukan janji promosi.
 * - talent_pool_events: riwayat perubahan status anggota (nominasi, keputusan, tinjau ulang, keluar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talent_pools', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->text('purpose');
            $table->foreignId('target_job_family_id')->nullable()->constrained('job_families')->nullOnDelete();
            $table->foreignId('target_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->text('entry_criteria');
            $table->text('exit_criteria');
            $table->unsignedInteger('owner_user_id');
            $table->unsignedTinyInteger('review_cycle_months')->default(12);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('talent_pool_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('pool_id')->constrained('talent_pools')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('status', ['nominated', 'active', 'rejected', 'exited'])->default('nominated');
            $table->enum('source', ['talent_review', 'manual']);
            $table->foreignId('talent_review_entry_id')->nullable()->constrained('talent_review_entries')->nullOnDelete();
            $table->text('nomination_reason');
            $table->unsignedInteger('nominated_by');
            $table->enum('readiness', ['now', '1_2', '3_plus'])->nullable();
            $table->unsignedInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->date('joined_at')->nullable();
            $table->date('next_review_date')->nullable();
            $table->date('exited_at')->nullable();
            $table->string('exit_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'pool_id', 'status']);
            $table->index(['tenant_id', 'employee_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('talent_pool_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('member_id')->constrained('talent_pool_members')->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('readiness', 10)->nullable();
            $table->string('note', 1000)->nullable();
            $table->unsignedInteger('user_id');
            $table->timestamp('created_at');
            $table->index(['tenant_id', 'member_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        HcmsModules::sync();
    }

    public function down(): void
    {
        DB::table('tb_menu')->whereIn('module', ['talent/pools', 'talent/pools/report'])->delete();
        DB::table('tb_module')->whereIn('module_name', ['talentpools', 'talentpoolreport'])->delete();
        foreach (['talent_pool_events', 'talent_pool_members', 'talent_pools'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
