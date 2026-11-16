<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('unit', function (Blueprint $table) {
            $table->increments('id_unit');
            $table->char('slug_unit',255)->unique();
            $table->foreignId('parent_id')->nullable();
            $table->integer('urutan')->nullable();
            $table->text('unit_name');
            $table->char('unit_code',255);
            $table->foreignId('id_unit_category');
            $table->foreignId('id_corporate');
            $table->text('unit_addres');
            $table->text('unit_explan');
            $table->foreignId('id_unit_type');
            $table->integer('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit');
    }
};
