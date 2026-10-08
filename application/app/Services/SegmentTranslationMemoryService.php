<?php

namespace App\Services;

use App\Models\Segment;
use App\Models\TranslationMemorySegment;
use Illuminate\Support\Collection;

class SegmentTranslationMemoryService
{
    /**
     * Save confirmed segments to the writable TMs of their project, with the
     * neighbouring segments as context.
     *
     * @param Collection<int, Segment> $segments        Confirmed segments to save.
     * @param Collection<int, Segment>|null $contextSegments  Every segment of the job, used to resolve
     *        the real previous/next neighbours. When omitted, neighbours are taken from $segments and
     *        fall back to the database outside that set.
     */
    public static function saveConfirmed(Collection $segments, ?Collection $contextSegments = null): void
    {
        if ($segments->isEmpty()) {
            return;
        }

        $writableTms = $segments->first()?->job
            ?->project
            ?->translationMemories()
            ->wherePivot('write', true)
            ->get() ?? collect();

        if ($writableTms->isEmpty()) {
            return;
        }

        // Build position-keyed map for efficient prev/next lookup
        $pool       = $contextSegments ?? $segments;
        $byPosition = $pool->keyBy('position');
        $positions  = $byPosition->keys()->sort()->values();

        foreach ($segments as $seg) {
            $posIdx  = $positions->search($seg->position);
            $prevPos = $posIdx > 0 ? $positions->get($posIdx - 1) : null;
            $nextPos = $posIdx !== false ? $positions->get($posIdx + 1) : null;

            // Fall back to DB for prev/next outside the pool
            $prev = $prevPos !== null
                ? $byPosition->get($prevPos)
                : ($contextSegments ? null : Segment::where('job_id', $seg->job_id)
                    ->where('position', '<', $seg->position)
                    ->orderBy('position', 'desc')
                    ->first());

            $next = $nextPos !== null
                ? $byPosition->get($nextPos)
                : ($contextSegments ? null : Segment::where('job_id', $seg->job_id)
                    ->where('position', '>', $seg->position)
                    ->orderBy('position', 'asc')
                    ->first());

            foreach ($writableTms as $tm) {
                TranslationMemorySegment::updateOrCreate(
                    [
                        'translation_memory_id' => $tm->id,
                        'segment_id'            => $seg->id,
                    ],
                    [
                        'source'                => $seg->source,
                        'source_context_before' => $prev?->source,
                        'source_context_after'  => $next?->source,
                        'target'                => $seg->target,
                        'target_context_before' => $prev?->target,
                        'target_context_after'  => $next?->target,
                    ]
                );
            }
        }
    }
}
