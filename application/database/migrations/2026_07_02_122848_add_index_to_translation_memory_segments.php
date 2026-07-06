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
        Schema::table('translation_memory_segments', function (Blueprint $table) {
            $table->index(['translation_memory_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('translation_memory_segments', function (Blueprint $table) {
            $table->dropIndex(['translation_memory_id']);
        });
    }
};
