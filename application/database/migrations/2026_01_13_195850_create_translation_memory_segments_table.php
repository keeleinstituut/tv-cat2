<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Schema::create('translation_memory_segments', function (Blueprint $table) {
        //     $table->uuid('id')->primary();

        //     // $table->foreignUuid('translation_memory_id')->constrained();
        //     $table->uuid('segment_id')->nullable();

        //     $table->text('source');
        //     $table->text('target');

        //     $table->timestampsTz();
        // });

        // DB::statement("
        //     CREATE INDEX translation_memory_segments_source_idx ON translation_memory_segments USING GIST (source gist_trgm_ops);
        // ");
        // DB::statement("
        //     CREATE INDEX translation_memory_segments_target_idx ON translation_memory_segments USING GIST (target gist_trgm_ops);
        // ");




        Schema::create('translation_memory_segments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('translation_memory_id')->constrained();
            $table->uuid('segment_id')->nullable();

            $table->text('source');
            $table->text('source_context_before')->nullable();
            $table->text('source_context_after')->nullable();

            $table->text('target');
            $table->text('target_context_before')->nullable();
            $table->text('target_context_after')->nullable();

            $table->timestampsTz();
        });

        // DB::statement("
        //     CREATE INDEX translation_memory_segments_source_idx ON translation_memory_segments USING GIST (source gist_trgm_ops);
        // ");
        // DB::statement("
        //     CREATE INDEX translation_memory_segments_target_idx ON translation_memory_segments USING GIST (target gist_trgm_ops);
        // ");

        // Create additional source_tsvector column, that is generated from source column when row is updated.
        DB::statement("
            ALTER TABLE translation_memory_segments
                ADD COLUMN source_tsvector tsvector
                    GENERATED ALWAYS AS (to_tsvector('simple', source)) STORED;
        ");

        // Add index to source_tsvector column
        DB::statement("
            CREATE INDEX translation_memory_segments_source_tsvector_idx ON translation_memory_segments USING GIN (source_tsvector);
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translation_memory_segments');
    }
};
