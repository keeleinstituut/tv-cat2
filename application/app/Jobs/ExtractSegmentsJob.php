<?php

namespace App\Jobs;

use App\Models\Job;
use App\Models\Segment;
use App\Services\FiltersClient;
use App\Support\SegmentText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads the translatable text out of the source file of a job into segments.
 */
class ExtractSegmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 500;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(private Job $jobModel)
    {
    }

    public function handle(FiltersClient $filters): void
    {
        $file = $this->jobModel->sourceFileCollection()->firstOrFail();

        $extracted = $filters->extract(
            $file,
            $this->jobModel->project->source_locale,
            $this->jobModel->target_locale,
        );

        DB::transaction(function () use ($extracted) {
            $now = now();
            collect($extracted)
                ->values()
                ->map(fn (array $segment, int $position) => [
                    'id' => Str::uuid()->toString(),
                    'job_id' => $this->jobModel->id,
                    'source' => $segment['text'],
                    'source_words' => SegmentText::wordCount($segment['text']),
                    'source_chars' => SegmentText::charCount($segment['text']),
                    'position' => $position,
                    'filter_segment_id' => $segment['id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->chunk(self::CHUNK_SIZE)
                ->each(fn ($chunk) => Segment::insert($chunk->all()));

            $this->jobModel->forceFill(['status' => Job::STATUS_READY, 'error' => null])->save();
        });
    }

    public function failed(Throwable $exception): void
    {
        $this->jobModel->forceFill([
            'status' => Job::STATUS_FAILED,
            'error' => Str::limit($exception->getMessage(), 2000),
        ])->save();
    }
}
