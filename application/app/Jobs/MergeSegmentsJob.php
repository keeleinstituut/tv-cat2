<?php

namespace App\Jobs;

use App\Models\Job;
use App\Services\FiltersClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Writes the targets of a job into a copy of its source file (the job's target file).
 */
class MergeSegmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(private Job $jobModel)
    {
    }

    public function handle(FiltersClient $filters): void
    {
        $file = $this->jobModel->sourceFileCollection()->firstOrFail();

        // Segments without a target keep their source text in the file.
        $translations = $this->jobModel->segments()
            ->whereNotNull('filter_segment_id')
            ->whereNotNull('target')
            ->where('target', '!=', '')
            ->pluck('target', 'filter_segment_id')
            ->all();

        $content = $filters->merge(
            $file,
            $translations,
            $this->jobModel->project->source_locale,
            $this->jobModel->target_locale,
        );

        $this->jobModel
            ->addMediaFromString($content)
            ->usingFileName($file->file_name)
            ->toMediaCollection(Job::TARGET_FILE_COLLECTION);
    }
}
