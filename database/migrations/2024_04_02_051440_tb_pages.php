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
        Schema::create('tb_pages', function (Blueprint $table) {
            $table->increments('pageID');
            $table->integer('cid')->nullable();
            $table->string('title', 255);
            $table->string('alias', 100);
            $table->string('sinopsis', 250)->nullable();
            $table->text('note');
            $table->string('filename', 50);
            $table->enum('headline', ['1', '0'])->nullable();
            $table->enum('status', ['enable', 'disable']);
            $table->text('access');
            $table->enum('allow_guest', ['0', '1']);
            $table->enum('template', ['frontend', 'backend']);
            $table->string('metakey', 255)->nullable();
            $table->text('metadesc')->nullable();
            $table->enum('default', ['0', '1']);
            $table->enum('pagetype', ['page', 'post'])->nullable();
            $table->text('labels')->nullable();
            $table->integer('views')->nullable();
            $table->integer('userid')->nullable();
            $table->string('thumbnail', 100)->nullable();
            $table->string('cover', 100)->nullable();
            $table->string('image', 255)->nullable();
            $table->dateTime('updated')->nullable();
            $table->dateTime('created')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_pages');
    }
};
