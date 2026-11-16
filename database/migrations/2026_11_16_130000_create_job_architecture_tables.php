<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organization & Job Architecture (blueprint bagian 5 dan 17.2 domain 2).
 *
 * Job Family → Job → Position. Standar kompetensi nanti didefinisikan per Job
 * (Fase 2) dan diwarisi posisi. Konvensi ADR 0001: PK BIGINT + uuid, tenant_id
 * wajib, unique selalu menyertakan tenant_id, deleted_at hanya untuk master data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_families', function (Blueprint $table) {
            $this->standard($table);
            $table->string('code', 30);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('job_levels', function (Blueprint $table) {
            $this->standard($table);
            $table->string('code', 30);
            $table->string('name', 100);
            $table->unsignedSmallInteger('rank')->default(1)->comment('Urutan senioritas; makin besar makin senior');
            $table->boolean('is_managerial')->default(false);
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('job_grades', function (Blueprint $table) {
            $this->standard($table);
            $table->foreignId('job_level_id')->constrained('job_levels');
            $table->string('code', 30);
            $table->string('name', 100);
            $table->unsignedSmallInteger('rank')->default(1);
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('jobs_catalog', function (Blueprint $table) {
            $this->standard($table);
            $table->foreignId('job_family_id')->constrained('job_families');
            $table->string('code', 30);
            $table->string('title', 150);
            $table->text('purpose')->nullable()->comment('Tujuan jabatan (job description ringkas)');
            $table->text('responsibilities')->nullable();
            $table->foreignId('grade_min_id')->nullable()->constrained('job_grades');
            $table->foreignId('grade_max_id')->nullable()->constrained('job_grades');
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('job_requirements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('job_id')->constrained('jobs_catalog')->cascadeOnDelete();
            $table->enum('type', ['education', 'experience', 'certification', 'skill', 'other']);
            $table->string('description', 255);
            $table->boolean('is_mandatory')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('positions', function (Blueprint $table) {
            $this->standard($table);
            $table->unsignedBigInteger('company_id');
            $table->unsignedInteger('unit_id');
            $table->foreignId('job_id')->constrained('jobs_catalog');
            $table->foreignId('grade_id')->nullable()->constrained('job_grades');
            $table->string('code', 40);
            $table->string('title', 150);
            $table->enum('status', ['active', 'frozen', 'closed'])->default('active');
            $table->boolean('is_critical')->default(false);
            $table->string('work_location', 150)->nullable();
            $table->unsignedSmallInteger('headcount')->default(1);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'unit_id']);
            $table->foreign('company_id')->references('id_corporate')->on('corporate');
            $table->foreign('unit_id')->references('id_unit')->on('unit');
        });

        Schema::create('position_relationships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('reports_to_id')->constrained('positions')->cascadeOnDelete();
            $table->enum('type', ['solid', 'dotted'])->default('solid');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'reports_to_id']);
            $table->index(['position_id', 'type']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        foreach (['position_histories' => ['position_id', 'positions'], 'organization_histories' => ['unit_id', 'unit']] as $name => [$fk, $ref]) {
            Schema::create($name, function (Blueprint $table) use ($fk, $ref) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $fk === 'unit_id' ? $table->unsignedInteger($fk) : $table->unsignedBigInteger($fk);
                $table->enum('action', ['created', 'updated', 'deleted']);
                $table->json('changes')->nullable()->comment('{kolom: [lama, baru]}');
                $table->date('effective_date')->nullable();
                $table->unsignedInteger('changed_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['tenant_id', $fk]);
                $table->foreign('tenant_id')->references('id')->on('tenants');
                // Tanpa FK ke $ref: riwayat tetap ada walau master dihapus
            });
        }
    }

    public function down(): void
    {
        foreach (['organization_histories', 'position_histories', 'position_relationships', 'positions', 'job_requirements', 'jobs_catalog', 'job_grades', 'job_levels', 'job_families'] as $name) {
            Schema::dropIfExists($name);
        }
    }

    /** Kolom standar master data (blueprint 17.3). */
    private function standard(Blueprint $table): void
    {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->uuid('uuid')->unique();
        $table->boolean('is_active')->default(true);
        $table->unsignedInteger('created_by')->nullable();
        $table->unsignedInteger('updated_by')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->index('tenant_id');
        $table->foreign('tenant_id')->references('id')->on('tenants');
    }
};
