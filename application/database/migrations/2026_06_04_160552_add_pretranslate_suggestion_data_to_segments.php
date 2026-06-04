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
        Schema::table('segments', function (Blueprint $table) {
            $table->string('pretranslate_suggestion_provider_type')->nullable();
            $table->double('pretranslate_suggestion_score')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('segments', function (Blueprint $table) {
            $table->dropColumn('pretranslate_suggestion_provider_type');
            $table->dropColumn('pretranslate_suggestion_score');
        });
    }
};
