<?php

namespace App\Console\Commands;

use App\Models\TranslationMemorySegment;
use App\Services\XmlInlineTagEncoder;
use Illuminate\Console\Command;

class DecodeTmSegments extends Command
{
    protected $signature = 'app:decode-tm-segments';
    protected $description = 'Decode HTML-encoded formatting tags in TranslationMemorySegment fields (idempotent)';

    private array $fields = [
        'source',
        'target',
        'source_context_before',
        'source_context_after',
        'target_context_before',
        'target_context_after',
    ];

    public function handle(): void
    {
        $updated = 0;

        TranslationMemorySegment::chunkById(500, function ($segments) use (&$updated) {
            foreach ($segments as $segment) {
                $dirty = false;

                foreach ($this->fields as $field) {
                    if ($segment->$field === null) {
                        continue;
                    }
                    $decoded = XmlInlineTagEncoder::decode($segment->$field);
                    if ($decoded !== $segment->$field) {
                        $segment->$field = $decoded;
                        $dirty = true;
                    }
                }

                if ($dirty) {
                    $segment->save();
                    $updated++;
                }
            }
        });

        $this->info("Done. Updated {$updated} record(s).");
    }
}
