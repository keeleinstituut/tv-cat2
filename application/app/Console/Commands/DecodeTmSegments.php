<?php

namespace App\Console\Commands;

use App\Models\TranslationMemorySegment;
use Illuminate\Console\Command;

class DecodeTmSegments extends Command
{
    protected $signature = 'app:decode-tm-segments';
    protected $description = 'Decode HTML-encoded formatting tags in TranslationMemorySegment fields (idempotent)';

    private const TAGS = ['i', 'sup', 'b', 'sub', 'strong', 'em'];

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
                    $decoded = self::decode($segment->$field);
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

    public static function decode(string $content): string
    {
        foreach (self::TAGS as $tag) {
            $content = str_replace("&lt;{$tag}&gt;", "<{$tag}>", $content);
            $content = str_replace("&lt;/{$tag}&gt;", "</{$tag}>", $content);
            // half-encoded edge cases (e.g. <sup&gt;)
            $content = str_replace("<{$tag}&gt;", "<{$tag}>", $content);
            $content = str_replace("</{$tag}&gt;", "</{$tag}>", $content);
        }

        return $content;
    }
}
