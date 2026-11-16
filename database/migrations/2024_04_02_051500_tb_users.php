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
        Schema::create('tb_users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username', 100);
            $table->string('nik', 25)->nullable();
            $table->string('hp', 15)->nullable();
            $table->string('password', 100);
            $table->string('email', 100);
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('avatar', 100);
            $table->tinyInteger('active');
            $table->tinyInteger('login_attempt')->nullable();
            $table->dateTime('last_login')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->nullable();
            $table->string('reminder', 64)->nullable();
            $table->string('activation', 50)->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->integer('last_activity')->nullable();
            $table->text('config')->nullable();
            $table->integer('entry_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_users');
    }
};
