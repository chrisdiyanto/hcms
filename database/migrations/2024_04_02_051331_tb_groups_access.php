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
        Schema::create('tb_groups_access', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('group_id');
            $table->foreignId('module_id');
            $table->longtext('access_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_groups_access');
    }
};
