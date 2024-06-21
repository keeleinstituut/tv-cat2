<?php

namespace App\Jobs;

use App\Models\Job;
use App\Services\XliffConverterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class XliffToOriginalJob implements ShouldQueue
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
        $originalFileConversion = XliffConverterService::convertXliffToOriginal($this->jobModel->targetXliffFileCollection->first());

        $decodedContent = base64_decode($originalFileConversion['documentContent']);

        $this->jobModel
            ->addMediaFromString($decodedContent)
            ->usingFileName($originalFileConversion['filename'])
            ->toMediaCollection(Job::TARGET_FILE_COLLECTION);

        // $this->media->model
        //     ->addMediaFromString($xliffFileConversion['xliffContent'])
        //     ->usingFileName($this->media->file_name . '.xliff')
        //     ->toMediaCollection(Job::XLIFF_FILE_COLLECTION);
    }
}
