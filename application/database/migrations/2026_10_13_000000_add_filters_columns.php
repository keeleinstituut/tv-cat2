<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('segments', function (Blueprint $table) {
            // Id of the segment in catto-filters; needed to write the translation back into the file.
            $table->string('filter_segment_id')->nullable();
            $table->unique(['job_id', 'filter_segment_id']);
            $table->index(['job_id', 'position']);

            // XLIFF is no longer used; the columns are dropped once existing jobs were re-imported.
            $table->string('xliff_internal_id')->nullable()->change();
            $table->string('xliff_mrk_id')->nullable()->change();
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->string('status')->default('processing');
            $table->text('error')->nullable();
        });

        // Jobs created before this migration have their segments already.
        DB::table('jobs')->update(['status' => 'ready']);
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropColumn(['status', 'error']);
        });

        Schema::table('segments', function (Blueprint $table) {
            $table->dropIndex(['job_id', 'position']);
            $table->dropUnique(['job_id', 'filter_segment_id']);
            $table->dropColumn('filter_segment_id');
        });
    }
};
