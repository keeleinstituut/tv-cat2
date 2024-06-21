<?php

namespace App\Jobs;

use App\Models\Job;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Matecat\XliffParser\XliffParser;

class SegmentsToXliffJob implements ShouldQueue
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
        $originalXliffFile = $this->jobModel->xliffFileCollection->first();
        $originalXliffStream = $originalXliffFile->stream();
        $originalXliffContent = stream_get_contents($originalXliffStream);

        $uuid = Str::uuid();

        $relativeTemporaryPath = $this->jobModel->id . "/translated/" . $uuid . '.xliff';
        Storage::disk('temporary')->put($relativeTemporaryPath, $originalXliffContent);
        $temporaryPath = Storage::disk('temporary')->url($relativeTemporaryPath);

        $relativeOutputPath = $this->jobModel->id . "/translated/" . $uuid . '.output.xliff';
        $outputPath = Storage::disk('temporary')->url($relativeOutputPath);

        $setSourceInTarget = false;

        $data = collect($this->jobModel->segments)->map(function ($segment) {
           return [
//               'segment' => $segment->source,
//               'translation' => $segment->target,
//               'mrk_id' => $segment->xliff_mrk_id,
//               'internal_id' => $segment->xliff_internal_id,
//               'status' => 'TRANSLATED',

                'sid' =>  $segment->id,
                'segment' =>  $segment->source,
                'internal_id' =>  $segment->xliff_internal_id,
                'mrk_id' =>  $segment->xliff_mrk_id,
                'prev_tags' =>  '',
                'succ_tags' =>  '',
                'mrk_prev_tags' =>  null,
                'mrk_succ_tags' =>  null,
                'translation' =>  $segment->target,
                'status' =>  'TRANSLATED',
                'error' =>  '',
                'eq_word_count' =>  '4.10',
                'raw_word_count' =>  '5.00',
                'data_ref_map' =>  null
           ];
        })->toArray();

        $transUnits = [];

        foreach ( $data as $i => $k ) {
            //create a secondary indexing mechanism on segments' array; this will be useful
            //prepend a string so non-trans unit id ( ex: numerical ) are not overwritten
            $internalId = $k[ 'internal_id' ];

            $transUnits[ $internalId ] [] = $i;

            $data[ 'matecat|' . $internalId ] [] = $i;
        }

        $callback = null;

        $msg = [
            'xliffFilePath' => $temporaryPath,
            'data' => $data,
            'transUnits' => $transUnits,
            '_target_lang' => $this->jobModel->target_locale,
            'outputPath' => $outputPath,
            'setSourceInTarget' => $setSourceInTarget,
            'xliffReplacerCallback' => $callback,
        ];

        Storage::disk('temporary')->put($this->jobModel->id . "/translated/" . $uuid . '.json', json_encode($msg));

        $parser = new XliffParser();
        $parser->replaceTranslation(
            $temporaryPath,
            $data,
            $transUnits,
            $this->jobModel->target_locale,
            $outputPath,
            $setSourceInTarget,
            $callback,
        );


        $this->jobModel
            ->addMediaFromStream(Storage::disk('temporary')->readStream($relativeOutputPath))
            ->usingFileName($this->jobModel->sourceFileCollection[0]->file_name . 'target.xliff')
            ->toMediaCollection(Job::TARGET_XLIFF_FILE_COLLECTION);
    }
}
