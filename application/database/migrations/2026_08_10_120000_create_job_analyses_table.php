<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_analyses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_id')->constrained();
            $table->foreignUuid('analysis_id')->constrained('analyses');
            $table->jsonb('results')->nullable();
            $table->timestampsTz();
        });

        // Backfill: each pre-existing analysis was a single job's results.
        // Preserve that as its own job_analyses row before analyses.job_id/results are dropped.
        DB::table('analyses')->orderBy('id')->each(function ($analysis) {
            DB::table('job_analyses')->insert([
                'id' => Str::uuid(),
                'job_id' => $analysis->job_id,
                'analysis_id' => $analysis->id,
                'results' => $analysis->results,
                'created_at' => $analysis->created_at,
                'updated_at' => $analysis->updated_at,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_analyses');
    }
};
