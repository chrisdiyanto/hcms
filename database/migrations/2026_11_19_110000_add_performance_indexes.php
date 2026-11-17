<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks dari hasil uji beban Fase 4 (10.000 pegawai, 200.000 baris audit).
 * - audit_logs (tenant_id, auditable_type): daftar tipe di filter Audit Trail (DISTINCT 819 ms → indeks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['tenant_id', 'auditable_type'], 'audit_logs_tenant_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_tenant_type_index');
        });
    }
};
