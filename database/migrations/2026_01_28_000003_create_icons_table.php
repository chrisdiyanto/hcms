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
        Schema::create('tb_icons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('code'); // code, file, url
            $table->text('svg_code')->nullable();
            $table->string('file_path')->nullable();
            $table->string('icon_url')->nullable();
            $table->string('icon_class')->nullable();
            $table->string('category')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_icons');
    }
};
