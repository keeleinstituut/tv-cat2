<?php

namespace App\Jobs;

use App\Models\Job;
use App\Models\Media;
use App\Services\XliffConverterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OriginalToXliffJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Media $media;
    private string $sourceLocale;
    private string $targetLocale;

    /**
     * Create a new job instance.
     */
    public function __construct(Media $media, string $sourceLocale, string $targetLocale)
    {
        $this->media = $media;
        $this->sourceLocale = $sourceLocale;
        $this->targetLocale = $targetLocale;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $xliffFileConversion = XliffConverterService::convertOriginalToXliff($this->sourceLocale, $this->targetLocale, $this->media);

        $this->media->model
            ->addMediaFromString($xliffFileConversion['xliffContent'])
            ->usingFileName($this->media->file_name . '.xliff')
            ->toMediaCollection(Job::XLIFF_FILE_COLLECTION);

//            $xliffFile = new Media();
//            $xliffFile->type = Media::XLIF;
//            $xliffFile->path = $order->getXliffFilePath($file->getName() . '.xlf');
//            $xliffFile->fileable_id = $file->fileable_id;
//            $xliffFile->fileable_type = $file->fileable_type;
//
//            $xliffFile->putContent($xliffFileConversion['xliffContent']);
//            $xliffFile->save();
    }
}
