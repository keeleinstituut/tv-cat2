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
use App\Services\InternalTranslationMemoryService;
use App\Services\Dto\GetSuggestionsOptions;

class PretranslateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Job $jobModel;

    /**
     * Create a new job instance.
     */
    public function __construct(Job $jobModel)
    {
        $this->jobModel = $jobModel;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $segments = $this->jobModel->segments()->orderBy('position')->get();
        $untranslated = $segments->filter(fn($s) => !$s->target);

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

        // Determine which sources still need MT (no TM/NT match with score >= 98)
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
        if (!empty($mtCandidates)) {
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

        foreach ($untranslated as $segment) {
            $best = $bestMatches[$segment->source] ?? null;
            if (!$best) {
                continue;
            }

            $data = [
                'target' => $best['target'],
                'score' => data_get($best, 'score'),
                'provider_type' => data_get($best, 'provider.type'),
            ];

            if ($segment->repetition_group) {
                $groupUpdates[$segment->repetition_group] = $data;
            } else {
                $individualUpdates[$segment->id] = $data;
            }
        }

        // Bulk update individual segments
        if (!empty($individualUpdates)) {
            $placeholders = implode(', ', array_fill(0, count($individualUpdates), '(?, ?, ?, ?)'));
            $params = [];
            foreach ($individualUpdates as $id => $targetData) {
                $params[] = $id;
                $params[] = $targetData['target'];
                $params[] = $targetData['provider_type'];
                $params[] = $targetData['score'];
            }
            DB::statement(
                "UPDATE segments SET
                    target = v.target, updated_at = NOW(),
                    pretranslate_suggestion_provider_type = v.provider_type,
                    pretranslate_suggestion_score = v.score::decimal
                 FROM (VALUES $placeholders) AS v(id, target, provider_type, score)
                 WHERE segments.id::text = v.id",
                $params
            );
        }

        // Bulk update repetition groups (updates all members including already-translated ones)
        if (!empty($groupUpdates)) {
            $placeholders = implode(', ', array_fill(0, count($groupUpdates), '(?, ?, ?, ?)'));
            $params = [];
            foreach ($groupUpdates as $groupId => $targetData) {
                $params[] = $groupId;
                $params[] = $targetData['target'];
                $params[] = $targetData['provider_type'];
                $params[] = $targetData['score'];
            }
            DB::statement(
                "UPDATE
                    segments SET target = v.target, updated_at = NOW(),
                    pretranslate_suggestion_provider_type = v.provider_type,
                    pretranslate_suggestion_score = v.score::decimal
                 FROM (VALUES $placeholders) AS v(group_id, target, provider_type, score)
                 WHERE segments.repetition_group::text = v.group_id",
                $params
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

        if ($bestScoreMatch && $bestScoreMatch['score'] >= 98) {
            return $bestScoreMatch;
        }

        $mtMatch = collect($suggestions)
            ->filter(function ($suggestion) {
                return $suggestion['provider']['type'] == 'MT';
            })
            ->first();

        return $mtMatch;
    }
}
