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
        Schema::create('tb_rag_documents', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('file_path');
            $table->timestamps();
        });

        Schema::create('tb_rag_chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->longText('content');
            $table->json('embedding'); // Storing vector float array (e.g. [0.123, -0.456, ...])
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('tb_rag_documents')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_rag_chunks');
        Schema::dropIfExists('tb_rag_documents');
    }
};
