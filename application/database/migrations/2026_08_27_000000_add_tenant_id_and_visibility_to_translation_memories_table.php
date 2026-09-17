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
        Schema::table('translation_memories', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('target_locale');
            $table->string('visibility')->default('private')->after('tenant_id');
        });

        DB::table('translation_memories')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $meta = json_decode($row->meta, true) ?? [];

                $tenantId = $meta['institution_id'] ?? null;
                $visibility = $meta['visibility'] ?? 'private';

                unset($meta['institution_id'], $meta['visibility']);

                DB::table('translation_memories')
                    ->where('id', $row->id)
                    ->update([
                        'tenant_id' => $tenantId,
                        'visibility' => $visibility,
                        'meta' => json_encode($meta),
                    ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('translation_memories')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $meta = json_decode($row->meta, true) ?? [];

                if ($row->tenant_id !== null) {
                    $meta['institution_id'] = $row->tenant_id;
                }
                $meta['visibility'] = $row->visibility;

                DB::table('translation_memories')
                    ->where('id', $row->id)
                    ->update(['meta' => json_encode($meta)]);
            }
        });

        Schema::table('translation_memories', function (Blueprint $table) {
            $table->dropColumn(['tenant_id', 'visibility']);
        });
    }
};
