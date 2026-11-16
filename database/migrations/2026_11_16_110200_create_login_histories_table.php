<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * login_histories (blueprint 17.2 domain 14). Kegagalan dengan akun tak dikenal
 * disimpan tanpa tenant_id (data platform, tidak terlihat oleh tenant).
 * Kata sandi tidak pernah disimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('login', 150)->nullable();
            $table->enum('event', ['login', 'failed', 'logout', 'blocked']);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_histories');
    }
};
