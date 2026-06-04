<?php

namespace App\Jobs;

use App\Models\Analysis;
use App\Models\Job;
use App\Services\Dto\GetSuggestionsOptions;
use App\Services\InternalTranslationMemoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Job $jobModel;
    private Analysis $analysis;

    public function __construct(Job $jobModel, Analysis $analysis)
    {
        $this->jobModel = $jobModel;
        $this->analysis = $analysis;
    }

    public function handle(): void
    {
        $segments = $this->jobModel->segments()->orderBy('position')->get();

        if ($segments->isEmpty()) {
            $this->analysis->results = $this->emptyResults();
            $this->analysis->save();
            return;
        }

        $sourceLocale = $this->jobModel->project->source_locale;
        $targetLocale = $this->jobModel->target_locale;

        $tmOptions = GetSuggestionsOptions::make()
            ->setSourceLocale($sourceLocale)
            ->setTargetLocale($targetLocale);

        // foreach ($segments->pluck('source')->unique() as $source) {
        //     $tmOptions->addQuery($source);
        // }

        for ($i = 0; $i < $segments->count(); $i++) { 
            $previousSource = data_get($segments, $i - 1 . '.source');
            $currentSource  = data_get($segments, $i . '.source');
            $nextSource     = data_get($segments, $i - 1 . '.source');
            $tmOptions->addQuery($currentSource, $previousSource, $nextSource);
        }


        $tmResults = InternalTranslationMemoryService::getSuggestionsBatch($tmOptions);

        $bands = [
            'ice'          => ['segments' => 0, 'words' => 0, 'chars' => 0],
            'exact'        => ['segments' => 0, 'words' => 0, 'chars' => 0],
            'repetitions'  => ['segments' => 0, 'words' => 0, 'chars' => 0],
            'high_fuzzy'   => ['segments' => 0, 'words' => 0, 'chars' => 0],
            'medium_fuzzy' => ['segments' => 0, 'words' => 0, 'chars' => 0],
            'low_fuzzy'    => ['segments' => 0, 'words' => 0, 'chars' => 0],
            'slight_fuzzy' => ['segments' => 0, 'words' => 0, 'chars' => 0],
            'no_match'     => ['segments' => 0, 'words' => 0, 'chars' => 0],
        ];

        foreach ($segments as $segment) {
            $plain = strip_tags($segment->source);
            $words = str_word_count($plain);
            $chars = mb_strlen($plain);

            $band = $this->classifySegment($segment, $tmResults[$segment->source] ?? []);

            $bands[$band]['segments']++;
            $bands[$band]['words'] += $words;
            $bands[$band]['chars'] += $chars;
        }

        $total = array_reduce(array_values($bands), function ($carry, $item) {
            $carry['segments'] += $item['segments'];
            $carry['words'] += $item['words'];
            $carry['chars'] += $item['chars'];
            return $carry;
        }, ['segments' => 0, 'words' => 0, 'chars' => 0]);

        $this->analysis->results = ['bands' => $bands, 'total' => $total];
        $this->analysis->save();
    }

    private function classifySegment($segment, array $suggestions): string
    {
        // Subsequent repetitions (not the first occurrence)
        if ($segment->repetition_group && $segment->id !== $segment->repetition_group) {
            return 'repetitions';
        }

        $bestScore = collect($suggestions)
            ->pluck('score')
            ->filter(fn($s) => $s !== null)
            ->max();

        if ($bestScore === null) {
            return 'no_match';
        }

        if ($bestScore >= 101) return 'ice';
        if ($bestScore >= 100) return 'exact';
        if ($bestScore >= 95)  return 'high_fuzzy';
        if ($bestScore >= 85)  return 'medium_fuzzy';
        if ($bestScore >= 75)  return 'low_fuzzy';
        if ($bestScore >= 50)  return 'slight_fuzzy';

        return 'no_match';
    }

    private function emptyResults(): array
    {
        $empty = ['segments' => 0, 'words' => 0, 'chars' => 0];
        return [
            'bands' => [
                'ice'          => $empty,
                'exact'        => $empty,
                'repetitions'  => $empty,
                'high_fuzzy'   => $empty,
                'medium_fuzzy' => $empty,
                'low_fuzzy'    => $empty,
                'slight_fuzzy' => $empty,
                'no_match'     => $empty,
            ],
            'total' => $empty,
        ];
    }
}
