<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Competency Management (blueprint bagian 7 & 17.2 domain 4) — gelombang 1.
 *
 * - competency_framework_versions: kamus berversi (draft → published → retired).
 *   Hanya draft yang boleh diubah; asesmen kelak selalu merujuk versi saat dinilai.
 * - competencies / competency_levels / competency_indicators: definisi, 5 level,
 *   2–4 indikator perilaku per level (dicek saat terbit).
 * - job_competencies: profil standar per Job (level wajib + bobot kritikalitas 3/2/1),
 *   diwarisi semua posisi pada job tersebut.
 * - position_competencies: override per posisi (ubah level/bobot atau kecualikan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competency_framework_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->unsignedSmallInteger('version');
            $table->string('name', 150);
            $table->enum('status', ['draft', 'published', 'retired'])->default('draft');
            $table->date('effective_from')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('published_by')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'version']);
            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('competency_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code', 20);
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('competencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->foreignId('framework_version_id')->constrained('competency_framework_versions')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('competency_categories');
            $table->string('code', 40);
            $table->string('name', 150);
            $table->text('definition')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['framework_version_id', 'code']);
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('competency_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('title', 60);
            $table->text('description')->nullable();
            $table->unique(['competency_id', 'level']);
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('competency_indicators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('competency_level_id')->constrained('competency_levels')->cascadeOnDelete();
            $table->string('description', 500);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('job_competencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('job_id')->constrained('jobs_catalog')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->unsignedTinyInteger('required_level');
            $table->unsignedTinyInteger('criticality')->comment('3 = kritis, 2 = penting, 1 = pendukung');
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['job_id', 'competency_id']);
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('position_competencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->unsignedTinyInteger('required_level')->nullable()->comment('null = ikut job');
            $table->unsignedTinyInteger('criticality')->nullable()->comment('null = ikut job');
            $table->boolean('is_excluded')->default(false)->comment('kompetensi job tidak berlaku untuk posisi ini');
            $table->string('reason', 255)->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['position_id', 'competency_id']);
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });
    }

    public function down(): void
    {
        foreach (['position_competencies', 'job_competencies', 'competency_indicators', 'competency_levels', 'competencies', 'competency_categories', 'competency_framework_versions'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
