<?php

use App\Models\Media;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Run after jobs:reimport: files are no longer converted through XLIFF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('segments', function (Blueprint $table) {
            $table->dropColumn(['xliff_internal_id', 'xliff_mrk_id']);
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->dropColumn('xliff_parsed');
        });

        // The XLIFF files of jobs (source and target); deleting the model removes the file as well.
        Media::whereIn('collection_name', ['xliff', 'target-xliff'])->get()->each->delete();
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->json('xliff_parsed')->nullable();
        });

        Schema::table('segments', function (Blueprint $table) {
            $table->string('xliff_internal_id')->nullable();
            $table->string('xliff_mrk_id')->nullable();
        });
    }
};
