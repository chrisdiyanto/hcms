<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Kolom mengikuti modul Sximo "corporate" (config + view form/detail) dan
     * relasi unit.id_corporate (bigint unsigned).
     */
    public function up(): void
    {
        if (Schema::hasTable('corporate')) {
            return;
        }

        Schema::create('corporate', function (Blueprint $table) {
            $table->id('id_corporate');
            $table->string('slug_corporate', 100)->unique();
            $table->string('code', 20)->unique();
            $table->string('corporate_name', 255);
            $table->text('corporate_address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('corporate');
    }
};
