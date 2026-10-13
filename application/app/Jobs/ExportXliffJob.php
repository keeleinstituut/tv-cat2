<?php

namespace App\Jobs;

use App\Models\Job;
use App\Services\XliffExporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Writes the job as a bilingual XLIFF file (the job's xliff file), for handing the project to another CAT tool.
 */
class ExportXliffJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private Job $jobModel)
    {
    }

    public function handle(XliffExporter $exporter): void
    {
        $name = $this->jobModel->sourceFileCollection()->first()?->file_name ?? $this->jobModel->id;

        $this->jobModel
            ->addMediaFromString($exporter->export($this->jobModel))
            ->usingFileName($name . '.xlf')
            ->toMediaCollection(Job::XLIFF_FILE_COLLECTION);
    }
}
