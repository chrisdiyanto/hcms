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
        Schema::create('unit_category', function (Blueprint $table) {
            $table->increments('id_unit_category');
            $table->char('slug_unit_category', 20)->unique();
            $table->char('unit_category', 100);
            $table->char('unit_category_code', 5);
            $table->text('unit_category_explan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_category');
    }
};
