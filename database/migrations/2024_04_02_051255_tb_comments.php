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
        Schema::create('tb_comments', function (Blueprint $table) {
            $table->increments('commentID');
            $table->foreignId('pageID', 11);
            $table->foreignId('userID', 11);
            $table->longtext('comments');
            $table->dateTime('posted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_comments');
    }
};
