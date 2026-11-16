<?php

use App\Domain\Organization\Services\OrgUnitClosure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data scope (blueprint 2.3 & 3.2):
 * - org_unit_closure: semua pasangan atasan–bawahan unit (termasuk diri sendiri, depth 0)
 *   agar "unit ini dan seluruh bawahannya" cukup satu query.
 * - user_data_scopes: unit yang boleh dilihat pengguna di luar penugasannya sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_unit_closure', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedInteger('ancestor_id');
            $table->unsignedInteger('descendant_id');
            $table->unsignedSmallInteger('depth');
            $table->primary(['ancestor_id', 'descendant_id']);
            $table->index('descendant_id');
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('ancestor_id')->references('id_unit')->on('unit')->cascadeOnDelete();
            $table->foreign('descendant_id')->references('id_unit')->on('unit')->cascadeOnDelete();
        });

        Schema::create('user_data_scopes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('unit_id');
            $table->boolean('include_children')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('reason', 255)->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'unit_id']);
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('user_id')->references('id')->on('tb_users')->cascadeOnDelete();
            $table->foreign('unit_id')->references('id_unit')->on('unit')->cascadeOnDelete();
        });

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            OrgUnitClosure::rebuild((int) $tenantId);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_data_scopes');
        Schema::dropIfExists('org_unit_closure');
    }
};
