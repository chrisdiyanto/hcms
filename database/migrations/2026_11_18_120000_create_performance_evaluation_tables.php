<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance Management gelombang 3 (blueprint 8.4–8.7, 8.9 langkah 8–11).
 *
 * - performance_assessments (+ details): per rencana; self → atasan → atasan dari atasan.
 *   Detail menyimpan skor perilaku (How) & kontribusi tambahan 1–5 per penilai, plus komentar KPI.
 * - calibration_sessions / calibration_adjustments: penyesuaian band dengan alasan wajib.
 * - performance_ratings: hasil akhir per pegawai per periode (prorata antar-rencana), berrevisi;
 *   performance_snapshots: salinan immutable (trigger menolak UPDATE/DELETE) termasuk kebijakan.
 * - performance_appeals: keberatan dalam jendela 14 hari, diputus pejabat di atas penilai.
 * - performance_improvement_plans: PIP untuk rating rendah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_assessments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('plan_id')->unique()->constrained('performance_plans')->cascadeOnDelete();
            $table->enum('status', ['self', 'manager', 'review', 'completed'])->default('self');
            $table->text('self_summary')->nullable();
            $table->timestamp('self_submitted_at')->nullable();
            $table->unsignedInteger('manager_user_id')->nullable();
            $table->text('manager_summary')->nullable();
            $table->timestamp('manager_submitted_at')->nullable();
            $table->unsignedInteger('reviewer_user_id')->nullable()->comment('atasan dari atasan (one-over-one)');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->decimal('what_score', 6, 2)->nullable();
            $table->decimal('how_score', 6, 2)->nullable();
            $table->decimal('contribution_score', 6, 2)->nullable();
            $table->decimal('final_raw', 6, 2)->nullable();
            $table->decimal('final_score', 6, 2)->nullable();
            $table->string('band', 30)->nullable();
            $table->json('weights')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('performance_assessment_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('assessment_id')->constrained('performance_assessments')->cascadeOnDelete();
            $table->enum('rater', ['self', 'manager']);
            $table->enum('item_type', ['kpi', 'how', 'contribution']);
            $table->unsignedBigInteger('objective_id')->nullable();
            $table->string('item_code', 40)->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['assessment_id', 'rater', 'item_type', 'objective_id', 'item_code'], 'perf_detail_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('calibration_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('period_id')->constrained('performance_periods');
            $table->string('name', 150);
            $table->unsignedInteger('unit_id')->nullable()->comment('kosong = seluruh pegawai dalam cakupan');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->text('notes')->nullable();
            $table->unsignedInteger('facilitator_user_id');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'period_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('calibration_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('session_id')->constrained('calibration_sessions')->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained('performance_assessments')->cascadeOnDelete();
            $table->string('from_band', 30);
            $table->string('to_band', 30);
            $table->decimal('from_score', 6, 2);
            $table->decimal('to_score', 6, 2);
            $table->text('reason');
            $table->unsignedInteger('adjusted_by');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'assessment_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });
        Schema::table('performance_assessments', function (Blueprint $table) {
            $table->decimal('calibrated_score', 6, 2)->nullable()->after('band');
            $table->string('calibrated_band', 30)->nullable()->after('calibrated_score');
        });

        Schema::create('performance_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->foreignId('period_id')->constrained('performance_periods');
            $table->foreignId('employee_id')->constrained('employees');
            $table->unsignedSmallInteger('revision')->default(1);
            $table->boolean('is_current')->default(true);
            $table->boolean('rated')->default(true)->comment('false = not rated (masa kerja kurang)');
            $table->decimal('raw_score', 6, 2)->nullable();
            $table->decimal('final_score', 6, 2)->nullable();
            $table->string('band', 30)->nullable();
            $table->string('band_label', 60)->nullable();
            $table->string('reason', 500)->nullable()->comment('alasan revisi (keberatan / koreksi)');
            $table->timestamp('published_at');
            $table->date('appeal_deadline');
            $table->unsignedInteger('published_by')->nullable();
            $table->timestamps();
            $table->unique(['period_id', 'employee_id', 'revision']);
            $table->index(['tenant_id', 'period_id', 'is_current']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('performance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('rating_id')->unique()->constrained('performance_ratings');
            // longText (bukan JSON): MySQL menormalkan kolom JSON sehingga checksum atas teks asli tidak bisa diverifikasi
            $table->longText('payload');
            $table->string('checksum', 64)->comment('SHA-256 dari payload');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });
        foreach (['UPDATE', 'DELETE'] as $op) {
            $name = 'performance_snapshots_no_'.strtolower($op);
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
            DB::unprepared("CREATE TRIGGER {$name} BEFORE {$op} ON performance_snapshots FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'performance_snapshots bersifat immutable'");
        }

        Schema::create('performance_appeals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('rating_id')->constrained('performance_ratings');
            $table->foreignId('employee_id')->constrained('employees');
            $table->text('reason');
            $table->enum('status', ['submitted', 'upheld', 'adjusted'])->default('submitted');
            $table->unsignedInteger('decider_user_id')->nullable()->comment('pejabat di atas penilai');
            $table->text('decision_note')->nullable();
            $table->string('new_band', 30)->nullable();
            $table->decimal('new_score', 6, 2)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('performance_improvement_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('rating_id')->nullable()->constrained('performance_ratings');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('objectives')->comment('area perbaikan & ukuran keberhasilan');
            $table->text('support')->nullable()->comment('dukungan / pelatihan dari perusahaan');
            $table->unsignedInteger('owner_user_id')->comment('atasan penanggung jawab');
            $table->enum('status', ['active', 'completed', 'extended', 'unsuccessful', 'cancelled'])->default('active');
            $table->text('outcome_note')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS performance_snapshots_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS performance_snapshots_no_delete');
        foreach (['performance_improvement_plans', 'performance_appeals', 'performance_snapshots', 'performance_ratings',
            'calibration_adjustments', 'calibration_sessions', 'performance_assessment_details', 'performance_assessments'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
