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
        Schema::create('tb_forms', function (Blueprint $table) {
            $table->increments('formID');
            $table->string('name', 255)->unique();
            $table->enum('method', ['eav', 'table', 'email']);
            $table->string('tablename', 255);
            $table->string('email', 255);
            $table->longtext('configuration', 255);
            $table->text('sucess');
            $table->text('failed');
            $table->text('redirect');
            $table->dateTime('create_at');
            $table->dateTime('update_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_forms');
    }
};
