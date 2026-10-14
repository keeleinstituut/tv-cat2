<?php

namespace App\Console\Commands;

use App\Models\Segment;
use App\Support\SegmentText;
use Illuminate\Console\Command;

/**
 * Fills source_words and source_chars of segments that were created before the columns existed.
 * Safe to run again: it recounts every segment from its source text.
 */
class BackfillSegmentCounts extends Command
{
    protected $signature = 'app:backfill-segment-counts
        {--job=* : Only the segments of these job ids (default: every job)}';

    protected $description = 'Count the words and characters of the source text of existing segments';

    public function handle(): int
    {
        $query = Segment::query()->select(['id', 'source', 'source_words', 'source_chars']);

        if ($jobs = $this->option('job')) {
            $query->whereIn('job_id', $jobs);
        }

        $updated = 0;
        $query->chunkById(1000, function ($segments) use (&$updated) {
            foreach ($segments as $segment) {
                $words = SegmentText::wordCount($segment->source);
                $chars = SegmentText::charCount($segment->source);

                if ($segment->source_words === $words && $segment->source_chars === $chars) {
                    continue;
                }

                Segment::whereKey($segment->id)->update(['source_words' => $words, 'source_chars' => $chars]);
                $updated++;
            }
        });

        $this->info("Updated {$updated} segments.");

        return self::SUCCESS;
    }
}
