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
        Schema::create('tb_menu', function (Blueprint $table) {
            $table->increments('menu_id');
            $table->foreignId('parent_id');
            $table->string('module', 50);
            $table->string('url', 100)->nullable();
            $table->string('menu_name', 100);
            $table->string('menu_type', 10);
            $table->string('role_id', 100)->nullable();
            $table->integer('deep')->nullable();
            $table->integer('ordering');
            $table->enum('position', ['top', 'sidebar', 'topnav', 'both']);
            $table->string('menu_icons', 30);
            $table->enum('active', ['0', '1']);
            $table->text('access_data');
            $table->enum('allow_guest', ['0', '1'])->nullable();
            $table->text('menu_lang')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_menu');
    }
};
