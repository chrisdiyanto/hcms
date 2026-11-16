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
        Schema::create('tb_notification', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('userid');
            $table->string('url', 255);
            $table->string('title', 255);
            $table->text('note');
            $table->dateTime('created');
            $table->string('icon', 20);
            $table->enum('is_read', ['0', '1']);
            $table->integer('postedBy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_notification');
    }
};
