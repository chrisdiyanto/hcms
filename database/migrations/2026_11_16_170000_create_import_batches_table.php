<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alat migrasi data (blueprint Fase 1 & 23: dry run 2×, rekonsiliasi).
 * Setiap unggahan = satu batch: divalidasi kering dulu, lalu di-commit utuh
 * (semua baris masuk atau tidak sama sekali).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->string('type', 40);
            $table->string('file_name', 255);
            $table->string('stored_path', 255);
            $table->enum('status', ['validated', 'invalid', 'committed', 'failed'])->default('validated');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->json('errors')->nullable()->comment('{baris: [pesan]}');
            $table->json('reconciliation')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
