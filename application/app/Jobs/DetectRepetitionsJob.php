<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Models\Job;

class DetectRepetitionsJob implements ShouldQueue
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
        $jobId = $this->jobModel->id;

        // Creates a subquery that iterates over all segments in a job and
        // partitions segments by source into repetition groups. Subquery is
        // used to update segments to assign each segment into repetition group if
        // repetition group contains more than 1 element. Repetition group is identified
        // by uuid of the first segment in repetition group.
        DB::statement("
            UPDATE segments
            SET repetition_group = subquery.first_segment_id
            FROM (
                SELECT
                    id,
                    COUNT(source) OVER (PARTITION BY source) AS repetition_count,
                    FIRST_VALUE(id) OVER (PARTITION BY job_id, source ORDER BY position ASC) AS first_segment_id
                FROM segments
                WHERE job_id = '$jobId'
            ) AS subquery
            WHERE segments.id = subquery.id AND subquery.repetition_count > 1;
        ");
    }
}
