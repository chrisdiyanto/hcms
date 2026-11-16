<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Domain Platform & Tenant (blueprint 17.2 no. 1): tenants + tenant_settings.
 * Konvensi HCMS (ADR 0001): PK BIGINT auto-increment + kolom uuid (UUIDv7) untuk ID publik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('legal_name', 255)->nullable();
            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->enum('license_type', ['enterprise', 'saas'])->default('enterprise');
            $table->unsignedInteger('max_employees')->nullable();
            $table->char('data_region', 2)->default('ID');
            $table->string('contact_name', 150)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('key', 100);
            $table->json('value')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });

        // Tenant default menampung seluruh data master yang sudah ada.
        DB::table('tenants')->insert([
            'uuid' => (string) Str::uuid7(),
            'code' => 'DEFAULT',
            'name' => 'Tenant Default',
            'status' => 'active',
            'license_type' => 'enterprise',
            'data_region' => 'ID',
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('tenants');
    }
};
