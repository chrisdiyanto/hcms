<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail (blueprint 3.3, 17.2 domain 14, 19.1):
 * - audit_logs: perubahan data (sebelum/sesudah), akses data sensitif, ekspor,
 *   dan aksi keamanan. Append-only: trigger MySQL menolak UPDATE dan DELETE.
 * - data_export_logs: setiap ekspor beserta alasan, jumlah baris, dan filter.
 *
 * Blueprint meminta partisi bulanan; MySQL tidak mengizinkan partisi pada tabel
 * ber-foreign key, jadi untuk MVP cukup index (tenant_id, created_at). Retensi/
 * pengarsipan dikerjakan bersama retention_policies (PDP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('event', 40)->comment('created, updated, deleted, sensitive_viewed, exported, security');
            $table->string('auditable_type', 60);
            $table->string('auditable_id', 64)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('url', 500)->nullable();
            $table->unsignedBigInteger('break_glass_id')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['tenant_id', 'event']);
        });

        Schema::create('data_export_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedInteger('user_id');
            $table->string('module', 60);
            $table->string('format', 20);
            $table->unsignedInteger('row_count');
            $table->string('reason', 500);
            $table->json('filters')->nullable();
            $table->boolean('approved_mass_export')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
        });

        // Append-only di tingkat database
        foreach (['UPDATE', 'DELETE'] as $op) {
            $name = 'audit_logs_no_'.strtolower($op);
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
            DB::unprepared("CREATE TRIGGER {$name} BEFORE {$op} ON audit_logs FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs bersifat append-only'");
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');
        Schema::dropIfExists('data_export_logs');
        Schema::dropIfExists('audit_logs');
    }
};
