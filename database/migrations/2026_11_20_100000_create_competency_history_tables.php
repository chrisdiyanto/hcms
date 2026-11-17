<?php

use App\Domain\Competency\Services\CompetencyHistory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat hasil kompetensi (prasyarat P1 laporan CBHRM): skor per asesmen, setiap perhitungan
 * JFI, dan gap per kompetensi pada perhitungan itu. Hanya bertambah — UPDATE ditolak trigger.
 * DELETE tetap diizinkan agar cascade penghapusan pegawai/tenant (hak subjek data) berjalan.
 */
return new class extends Migration
{
    private const IMMUTABLE = ['employee_competency_history', 'job_fit_history', 'competency_gap_history'];

    public function up(): void
    {
        Schema::create('employee_competency_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained('competency_assessments')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies');
            $table->string('competency_code', 40);
            $table->decimal('score', 4, 2);
            $table->enum('source', ['raters', 'assessment_center']);
            $table->date('assessed_at');
            $table->date('valid_until');
            $table->json('breakdown')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['assessment_id', 'employee_id', 'competency_code'], 'ech_assessment_employee_code');
            $table->index(['tenant_id', 'employee_id', 'competency_code', 'assessed_at'], 'ech_tenant_employee_code');
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('job_fit_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained('competency_assessments')->nullOnDelete();
            $table->decimal('jfi', 5, 2);
            $table->string('band', 20);
            $table->boolean('complete');
            $table->enum('trigger', ['assessment', 'recalculation', 'backfill']);
            $table->timestamp('computed_at');
            $table->timestamp('created_at')->nullable();
            $table->index(['tenant_id', 'employee_id', 'position_id', 'computed_at'], 'jfh_tenant_employee_position');
            $table->index(['tenant_id', 'computed_at']);
        });

        Schema::create('competency_gap_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('job_fit_history_id')->constrained('job_fit_history')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies');
            $table->string('competency_code', 40);
            $table->unsignedTinyInteger('required_level');
            $table->unsignedTinyInteger('criticality');
            $table->decimal('actual', 4, 2)->nullable();
            $table->decimal('gap', 5, 2);
            $table->unique(['job_fit_history_id', 'competency_code']);
            $table->index(['tenant_id', 'competency_code']);
            $table->index(['tenant_id', 'employee_id', 'competency_code'], 'cgh_tenant_employee_code');
        });
        Schema::table('job_fit_history', fn (Blueprint $table) => $table->foreign('tenant_id')->references('id')->on('tenants'));
        Schema::table('competency_gap_history', fn (Blueprint $table) => $table->foreign('tenant_id')->references('id')->on('tenants'));

        foreach (self::IMMUTABLE as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_update");
            DB::unprepared("CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$table} hanya bertambah (riwayat tidak dapat diubah)'");
        }

        // Hasil yang sudah ada menjadi titik awal riwayat (trigger "backfill")
        CompetencyHistory::backfill();
    }

    public function down(): void
    {
        foreach (self::IMMUTABLE as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_update");
        }
        foreach (['competency_gap_history', 'job_fit_history', 'employee_competency_history'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
