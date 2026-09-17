<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->foreignUuid('project_id')->nullable()->constrained();
            $table->jsonb('translation_memory_ids')->nullable();
        });

        // Backfill project_id for pre-existing analyses from their (still present) job_id.
        DB::statement(
            'update analyses set project_id = jobs.project_id ' .
            'from jobs where jobs.id = analyses.job_id'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn('translation_memory_ids');
        });
    }
};
