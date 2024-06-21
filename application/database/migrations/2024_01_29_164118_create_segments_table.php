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
        Schema::create('segments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_id')->constrained();
            $table->text('source');
            $table->text('target')->nullable();
            $table->unsignedBigInteger('position');
            // $table->boolean('confirmed')->default(false);
            // $table->boolean('locked')->default(false);
            $table->string('xliff_internal_id');
            $table->string('xliff_mrk_id');
            $table->uuid('repetition_group')->nullable();
            $table->timestampsTz();
        });

        DB::statement("
            CREATE INDEX segments_source_idx ON segments USING GIST (source gist_trgm_ops);
        ");
        DB::statement("
            CREATE INDEX segments_target_idx ON segments USING GIST (target gist_trgm_ops);
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('segments');
    }
};
