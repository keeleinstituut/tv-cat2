<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Job;
use App\Services\SuggestionService;
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

        $segments->each(function ($segment) {
            // Skip segment pretranslation when target is already set.
            // E.g. user has entered it before pretranslation or
            // been filled by propagating to repetitions.
            if ($segment->target) {
                return;
            }

            $suggestions = SuggestionService::getSuggestions(
                GetSuggestionsOptions::make()
                    ->setQ($segment->source)
                    ->setSourceLocale($this->jobModel->project->source_locale)
                    ->setTargetLocale($this->jobModel->target_locale)
            );

            $bestMatch = $this->getBestMatch($suggestions);

            if ($segment->repetition_group) {
                $this->jobModel->segments()
                    ->where('repetition_group', $segment->repetition_group)
                    ->update([
                        'target' => $bestMatch['target'],
                    ]);
            } else {
                $segment->update([
                    'target' => $bestMatch['target'],
                ]);
            }
        });
    }

    private function getBestMatch($suggestions)
    {
        $bestScoreMatch = collect($suggestions)
            ->filter(function ($suggestion) {
                return !!data_get($suggestion, 'score');
            })
            ->sortBy('score')
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
