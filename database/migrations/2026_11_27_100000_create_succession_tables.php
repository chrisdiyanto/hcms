<?php

use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Talent gelombang 3 — suksesi & bench strength (laporan CBHRM 11; kamus metrik §11, K8).
 *
 * - succession_plans: satu rencana per posisi kritis; draft → disahkan (oleh pemegang Approve selain
 *   pengubah terakhir). Setiap perubahan kandidat mengembalikan rencana ke draft.
 * - succession_candidates: suksesor utama/cadangan dengan kesiapan (K4), sumber (Kesiapan Posisi,
 *   talent pool, manual), alasan wajib, tindakan pengembangan; dihapus = status removed (jejak tetap).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('succession_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedInteger('owner_user_id')->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('last_changed_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->date('next_review_date')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'position_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('succession_candidates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('plan_id')->constrained('succession_plans')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('role', ['primary', 'backup']);
            $table->enum('readiness', ['now', '1_2', '3_plus']);
            $table->enum('source', ['readiness', 'pool', 'manual']);
            $table->foreignId('talent_pool_member_id')->nullable()->constrained('talent_pool_members')->nullOnDelete();
            $table->text('rationale');
            $table->string('development_action', 500)->nullable();
            $table->enum('status', ['active', 'removed'])->default('active');
            $table->string('removal_reason', 500)->nullable();
            $table->unsignedInteger('added_by');
            $table->timestamps();
            $table->index(['tenant_id', 'plan_id', 'status']);
            $table->index(['tenant_id', 'employee_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        HcmsModules::sync();
    }

    public function down(): void
    {
        DB::table('tb_menu')->whereIn('module', ['talent/succession', 'talent/succession/report'])->delete();
        DB::table('tb_module')->whereIn('module_name', ['succession', 'successionreport'])->delete();
        Schema::dropIfExists('succession_candidates');
        Schema::dropIfExists('succession_plans');
    }
};
