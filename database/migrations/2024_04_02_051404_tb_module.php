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
        Schema::create('tb_module', function (Blueprint $table) {
            $table->increments('module_id');
            $table->string('module_name', 100);
            $table->string('module_title', 100);
            $table->string('module_note', 255);
            $table->string('module_author', 100);
            $table->timestamp('module_created');
            $table->text('module_desc');
            $table->string('module_db', 255);
            $table->string('module_db_key', 100);
            $table->string('module_type', 20);
            $table->longtext('module_config');
            $table->text('module_lang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_module');
    }
};
