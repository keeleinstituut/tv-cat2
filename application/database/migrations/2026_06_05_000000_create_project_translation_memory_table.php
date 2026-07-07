<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_translation_memory', function (Blueprint $table) {
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('translation_memory_id')->constrained()->cascadeOnDelete();
            $table->boolean('read')->default(true);
            $table->boolean('write')->default(true);
            $table->primary(['project_id', 'translation_memory_id']);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_translation_memory');
    }
};
