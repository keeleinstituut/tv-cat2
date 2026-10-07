<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Models\Job;
use App\Services\LibreTranslateService;
use App\Services\NoTranslateService;
use App\Services\SegmentTranslationMemoryService;
use App\Services\InternalTranslationMemoryService;
use App\Services\Dto\GetSuggestionsOptions;

class PretranslateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Score given to a 100% text match whose surrounding context also matches. */
    private const LOCK_SCORE = 101;

    private Job $jobModel;

    /**
     * @param float $tmMatchThreshold   Minimum TM/NT match score to accept a suggestion.
     * @param bool  $useMt              Fall back to machine translation when no TM/NT match reaches the threshold.
     * @param bool  $lockPerfectMatches Lock (and confirm) segments filled by a 101% (context) TM match.
     */
    public function __construct(
        Job $jobModel,
        private float $tmMatchThreshold = 100,
        private bool $useMt = false,
        private bool $lockPerfectMatches = true,
    ) {
        $this->jobModel = $jobModel;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $segments = $this->jobModel->segments()->orderBy('position')->get();
        // Locked segments are never overwritten, but stay in $segments as context for their neighbours
        $untranslated = $segments->filter(fn($s) => !$s->locked);

        if ($untranslated->isEmpty()) {
            return;
        }

        $project = $this->jobModel->project->load('translationMemories');
        $sourceLocale = $project->source_locale;
        $targetLocale = $this->jobModel->target_locale;

        $projectTmIds = $project->translationMemories
            ->filter(fn($tm) => $tm->pivot->read)
            ->pluck('id')
            ->toArray();

        $sources = $untranslated->pluck('source')->unique()->values()->toArray();

        // Build O(N) lookup maps so the TM query loop below is O(U) not O(U*N)
        $segmentsValues = $segments->values();
        $segmentIndexById = $segmentsValues->mapWithKeys(fn($s, $i) => [$s->id => $i])->all();
        $firstUntranslatedBySource = [];
        foreach ($untranslated as $s) {
            $firstUntranslatedBySource[$s->source] ??= $s;
        }

        // Single SQL round-trip for all TM lookups
        $tmOptions = GetSuggestionsOptions::make()
            ->setSourceLocale($sourceLocale)
            ->setTargetLocale($targetLocale)
            ->setLimit(1);

        if (!empty($projectTmIds)) {
            $tmOptions->setTranslationMemoryIds($projectTmIds);
        }

        foreach ($sources as $source) {
            $i = $segmentIndexById[$firstUntranslatedBySource[$source]->id];
            $previousSource = data_get($segmentsValues, $i - 1 . '.source');
            $nextSource     = data_get($segmentsValues, $i + 1 . '.source');
            $tmOptions->addQuery($source, $previousSource, $nextSource);
        }

        $tmResults = InternalTranslationMemoryService::getSuggestionsBatch($tmOptions);

        // NT is pure regex — run per source before deciding MT candidates
        $ntResults = [];
        foreach ($sources as $source) {
            $ntOptions = GetSuggestionsOptions::make()->setQ($source);
            $ntResults[$source] = NoTranslateService::getSuggestions($ntOptions);
        }

        // Determine which sources still need MT (no TM/NT match with score >= threshold)
        $bestMatches = [];
        $mtCandidates = [];

        foreach ($sources as $idx => $source) {
            $suggestions = array_merge($tmResults[$idx] ?? [], $ntResults[$source] ?? []);
            $best = $this->getBestMatch($suggestions);
            if ($best) {
                $bestMatches[$source] = $best;
            } else {
                $mtCandidates[] = $source;
            }
        }

        // Single HTTP request for all MT candidates
        if ($this->useMt && !empty($mtCandidates)) {
            $mtResults = LibreTranslateService::translateBatch($mtCandidates, $sourceLocale, $targetLocale);
            foreach ($mtCandidates as $source) {
                $mtSuggestions = $mtResults[$source] ?? [];
                $best = $this->getBestMatch($mtSuggestions);
                if ($best) {
                    $bestMatches[$source] = $best;
                }
            }
        }

        // Build update maps: individual segments vs. repetition groups
        $individualUpdates = [];
        $groupUpdates = [];
        // Segments that end up locked by a 101% match are confirmed too (and saved to writable TMs below)
        $confirmIds = [];

        foreach ($untranslated as $segment) {
            $best = $bestMatches[$segment->source] ?? null;
            if (!$best) {
                continue;
            }

            $data = [
                'target' => $best['target'],
                'score' => data_get($best, 'score'),
                'provider_type' => data_get($best, 'provider.type'),
                'locked' => $this->lockPerfectMatches
                    && data_get($best, 'provider.type') === 'TM'
                    && data_get($best, 'score') >= self::LOCK_SCORE,
            ];

            if ($data['locked']) {
                $confirmIds[] = $segment->id;
            }

            if ($segment->repetition_group) {
                $groupUpdates[$segment->repetition_group] = $data;
            } else {
                $individualUpdates[$segment->id] = $data;
            }
        }

        // Bulk update individual segments
        if (!empty($individualUpdates)) {
            $placeholders = implode(', ', array_fill(0, count($individualUpdates), '(?, ?, ?, ?, ?)'));
            $params = [];
            foreach ($individualUpdates as $id => $targetData) {
                $params[] = $id;
                $params[] = $targetData['target'];
                $params[] = $targetData['provider_type'];
                $params[] = $targetData['score'];
                $params[] = (int) $targetData['locked'];
            }
            DB::statement(
                "UPDATE segments SET
                    target = v.target, updated_at = NOW(),
                    pretranslate_suggestion_provider_type = v.provider_type,
                    pretranslate_suggestion_score = v.score::decimal,
                    locked = (v.locked::int = 1),
                    confirmed = (segments.confirmed OR v.locked::int = 1)
                 FROM (VALUES $placeholders) AS v(id, target, provider_type, score, locked)
                 WHERE segments.id::text = v.id AND segments.locked = false",
                $params
            );
        }

        // Bulk update repetition groups (updates all members including already-translated ones)
        if (!empty($groupUpdates)) {
            $placeholders = implode(', ', array_fill(0, count($groupUpdates), '(?, ?, ?, ?, ?)'));
            $params = [];
            foreach ($groupUpdates as $groupId => $targetData) {
                $params[] = $groupId;
                $params[] = $targetData['target'];
                $params[] = $targetData['provider_type'];
                $params[] = $targetData['score'];
                $params[] = (int) $targetData['locked'];
            }
            DB::statement(
                "UPDATE
                    segments SET target = v.target, updated_at = NOW(),
                    pretranslate_suggestion_provider_type = v.provider_type,
                    pretranslate_suggestion_score = v.score::decimal,
                    locked = (v.locked::int = 1),
                    confirmed = (segments.confirmed OR v.locked::int = 1)
                 FROM (VALUES $placeholders) AS v(group_id, target, provider_type, score, locked)
                 WHERE segments.repetition_group::text = v.group_id AND segments.locked = false",
                $params
            );
        }

        if (!empty($confirmIds)) {
            // Reload so targets and neighbours reflect the updates above
            $reloaded = $this->jobModel->segments()->orderBy('position')->get();
            SegmentTranslationMemoryService::saveConfirmed(
                $reloaded->whereIn('id', $confirmIds)->values(),
                $reloaded,
            );
        }
    }

    private function getBestMatch($suggestions)
    {
        $bestScoreMatch = collect($suggestions)
            ->filter(function ($suggestion) {
                return !!data_get($suggestion, 'score');
            })
            ->sortByDesc('score')
            ->first();

        if ($bestScoreMatch && $bestScoreMatch['score'] >= $this->tmMatchThreshold) {
            return $bestScoreMatch;
        }

        if (!$this->useMt) {
            return null;
        }

        $mtMatch = collect($suggestions)
            ->filter(function ($suggestion) {
                return $suggestion['provider']['type'] == 'MT';
            })
            ->first();

        return $mtMatch;
    }
}
