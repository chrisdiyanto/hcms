<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IAM gelombang 2 (blueprint 3.4 & 19.1):
 * - MFA TOTP di tb_users. Secret & kode pemulihan disimpan terenkripsi (APP_KEY);
 *   kode pemulihan disimpan sebagai hash.
 * - break_glass_accesses: akses darurat Super Administrator ke data tenant,
 *   beralasan, berbatas waktu, dan diberitahukan ke Tenant Administrator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_users', function (Blueprint $table) {
            $table->text('mfa_secret')->nullable();
            $table->text('mfa_recovery_codes')->nullable();
            $table->timestamp('mfa_enabled_at')->nullable();
            $table->unsignedBigInteger('mfa_last_timestep')->nullable();
        });

        Schema::create('break_glass_accesses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedInteger('user_id');
            $table->text('reason');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('request_count')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'expires_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('user_id')->references('id')->on('tb_users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('break_glass_accesses');
        Schema::table('tb_users', function (Blueprint $table) {
            $table->dropColumn(['mfa_secret', 'mfa_recovery_codes', 'mfa_enabled_at', 'mfa_last_timestep']);
        });
    }
};
