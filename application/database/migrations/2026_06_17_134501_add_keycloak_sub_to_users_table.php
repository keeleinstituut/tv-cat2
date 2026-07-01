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
        Schema::table('users', function (Blueprint $table) {
            $table->string('keycloak_sub')->nullable()->after('id');
            $table->string('tolkevarav_institution_id')->nullable()->after('id');
            $table->string('tolkevarav_institution_user_id')->nullable()->after('id');

            $table->unique(['keycloak_sub', 'tolkevarav_institution_id', 'tolkevarav_institution_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('keycloak_sub');
            $table->dropColumn('tolkevarav_institution_id');
            $table->dropColumn('tolkevarav_institution_user_id');
        });
    }
};
