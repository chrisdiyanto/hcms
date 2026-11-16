<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee Management (blueprint bagian 6, 11.1 minimal, 17.2 domain 3).
 *
 * - employees: master pegawai; masa kerja dihitung dari join_date (tidak disimpan).
 * - employee_identities: identitas negara terenkripsi (APP_KEY) + hash untuk cek
 *   duplikat tanpa membuka enkripsi (blueprint 3.3 & 19.2).
 * - employee_assignments: satu primer aktif + rangkap/Plt, effective dating, nomor SK.
 * - employee_status_histories & employee_terminations: jejak perubahan status dan
 *   data terminasi minimal untuk turnover.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $this->standard($table);
            $table->unsignedBigInteger('company_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('employee_number', 30);
            $table->string('full_name', 150);
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['M', 'F'])->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->string('ptkp_status', 5)->nullable()->comment('TK/0 … K/3');
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address_ktp')->nullable();
            $table->text('address_domicile')->nullable();
            $table->enum('employment_status', ['pkwtt', 'pkwt', 'probation', 'outsourcing', 'internship']);
            $table->date('join_date');
            $table->date('probation_end_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->enum('status', ['active', 'terminated'])->default('active');
            $table->date('terminated_at')->nullable();
            $table->unique(['tenant_id', 'employee_number']);
            $table->unique('user_id');
            $table->index(['tenant_id', 'company_id', 'status']);
            $table->foreign('company_id')->references('id_corporate')->on('corporate');
            $table->foreign('user_id')->references('id')->on('tb_users')->nullOnDelete();
        });

        Schema::create('employee_identities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->text('nik_ktp')->nullable();
            $table->char('nik_hash', 64)->nullable();
            $table->text('npwp')->nullable();
            $table->text('bpjs_kesehatan')->nullable();
            $table->text('bpjs_ketenagakerjaan')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'nik_hash']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        $this->child('employee_families', function (Blueprint $table) {
            $table->string('name', 150);
            $table->enum('relationship', ['spouse', 'child', 'parent', 'sibling', 'other']);
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['M', 'F'])->nullable();
            $table->boolean('is_dependent')->default(false)->comment('Tanggungan PTKP/BPJS/tunjangan');
        });

        $this->child('employee_emergency_contacts', function (Blueprint $table) {
            $table->string('name', 150);
            $table->string('relationship', 50);
            $table->string('phone', 30);
        });

        $this->child('employee_education', function (Blueprint $table) {
            $table->enum('level', ['sd', 'smp', 'sma', 'd1', 'd2', 'd3', 'd4', 's1', 's2', 's3', 'other']);
            $table->string('institution', 150);
            $table->string('major', 150)->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();
        });

        $this->child('employee_experiences', function (Blueprint $table) {
            $table->string('company_name', 150);
            $table->string('position', 150);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
        });

        $this->child('employee_certifications', function (Blueprint $table) {
            $table->string('name', 150);
            $table->string('issuer', 150)->nullable();
            $table->string('certificate_number', 100)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->index('expires_at');
        });

        Schema::create('employee_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('position_id')->constrained('positions');
            $table->enum('type', ['primary', 'concurrent', 'acting'])->comment('acting = Plt./Pjs.');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('decree_number', 100)->nullable()->comment('Nomor SK');
            $table->date('decree_date')->nullable();
            $table->string('notes', 255)->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id', 'effective_from']);
            $table->index(['position_id', 'effective_to']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('employee_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('field', 50);
            $table->string('old_value', 255)->nullable();
            $table->string('new_value', 255)->nullable();
            $table->date('effective_date');
            $table->string('reason', 255)->nullable();
            $table->unsignedInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'employee_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('employee_terminations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('termination_date');
            $table->enum('type', ['resign', 'layoff', 'retirement', 'contract_end', 'death', 'other']);
            $table->string('reason', 500);
            $table->boolean('rehire_eligible')->default(true);
            $table->enum('state', ['scheduled', 'processed', 'cancelled'])->default('scheduled');
            $table->timestamp('processed_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'termination_date']);
            $table->index(['state', 'termination_date']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });
    }

    public function down(): void
    {
        foreach (['employee_terminations', 'employee_status_histories', 'employee_assignments', 'employee_certifications', 'employee_experiences', 'employee_education',
            'employee_emergency_contacts', 'employee_families', 'employee_identities', 'employees'] as $name) {
            Schema::dropIfExists($name);
        }
    }

    private function standard(Blueprint $table): void
    {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->uuid('uuid')->unique();
        $table->unsignedInteger('created_by')->nullable();
        $table->unsignedInteger('updated_by')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->index('tenant_id');
        $table->foreign('tenant_id')->references('id')->on('tenants');
    }

    /** Tabel riwayat milik pegawai (keluarga, pendidikan, dst.). */
    private function child(string $name, callable $columns): void
    {
        Schema::create($name, function (Blueprint $table) use ($columns) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $columns($table);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });
    }
};
