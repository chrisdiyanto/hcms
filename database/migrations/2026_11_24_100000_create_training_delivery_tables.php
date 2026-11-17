<?php

use App\Support\Modules\HcmsModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rilis 2 gelombang 3 — pelaksanaan pelatihan & efektivitas (laporan CBHRM 7).
 *
 * - training_sessions: kelas/jadwal dari katalog intervensi.
 * - training_participants: peserta dengan rujukan ke aktivitas IDP & kebutuhan TNA, kehadiran,
 *   kelulusan, evaluasi tingkat 1 (reaksi, diisi peserta) dan tingkat 2 (pre/post-test).
 *   Tingkat 3 (penerapan kompetensi) dan 4 (dampak kinerja) dihitung dari riwayat kompetensi (P1)
 *   dan rating final, tidak disimpan di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->foreignId('intervention_id')->constrained('development_interventions');
            $table->string('code', 40);
            $table->string('title', 200);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('mode', ['offline', 'online', 'blended'])->default('offline');
            $table->string('location', 200)->nullable();
            $table->string('trainer', 150)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->decimal('cost_total', 14, 2)->nullable()->comment('biaya realisasi kelas');
            $table->boolean('has_test')->default(false)->comment('pre/post-test (evaluasi tingkat 2)');
            $table->enum('status', ['planned', 'ongoing', 'completed', 'cancelled'])->default('planned');
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'start_date']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('training_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('session_id')->constrained('training_sessions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('idp_activity_id')->nullable()->constrained('idp_activities')->nullOnDelete();
            $table->foreignId('training_need_id')->nullable()->constrained('training_needs')->nullOnDelete();
            $table->enum('status', ['registered', 'attended', 'completed', 'no_show', 'cancelled'])->default('registered');
            $table->unsignedTinyInteger('attendance_pct')->nullable();
            $table->decimal('pre_score', 5, 2)->nullable();
            $table->decimal('post_score', 5, 2)->nullable();
            $table->decimal('reaction_score', 3, 2)->nullable()->comment('evaluasi tingkat 1, skala 1–5');
            $table->string('reaction_comment', 1000)->nullable();
            $table->timestamp('reaction_at')->nullable();
            $table->string('certificate_no', 100)->nullable();
            $table->date('completed_at')->nullable();
            $table->unsignedInteger('enrolled_by')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'employee_id']);
            $table->index(['tenant_id', 'employee_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        HcmsModules::sync();
    }

    public function down(): void
    {
        DB::table('tb_menu')->whereIn('module', ['development/sessions', 'development/effectiveness', 'ess/trainings'])->delete();
        DB::table('tb_module')->whereIn('module_name', ['trainingsessions', 'trainingeffect'])->delete();
        Schema::dropIfExists('training_participants');
        Schema::dropIfExists('training_sessions');
    }
};
