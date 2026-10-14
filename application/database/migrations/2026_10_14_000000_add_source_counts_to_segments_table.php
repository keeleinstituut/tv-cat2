<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Counted once when the segment is created (the source text never changes); existing rows are filled by
        // `php artisan app:backfill-segment-counts`.
        Schema::table('segments', function (Blueprint $table) {
            $table->unsignedInteger('source_words')->default(0);
            $table->unsignedInteger('source_chars')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('segments', function (Blueprint $table) {
            $table->dropColumn(['source_words', 'source_chars']);
        });
    }
};
