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
        Schema::create('tb_categories', function (Blueprint $table) {
            $table->increments('cid');
            $table->string('name', 50)->unique();
            $table->text('alias');
            $table->string('desc', 255);
            $table->string('image', 255);
            $table->string('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_categories');
    }
};
