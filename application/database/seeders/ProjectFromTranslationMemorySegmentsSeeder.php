<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

use App\Models\Project;
use App\Models\Job;
use App\Models\Segment;
use App\Models\TranslationMemorySegment;

use App\Jobs\DetectRepetitionsJob;
use App\Jobs\PretranslateJob;

class ProjectFromTranslationMemorySegmentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $project = Project::factory()
                ->has(
                    Job::factory()
                        ->count(1)
                )
                ->create();

            $job = $project->jobs->first();


            $table = TranslationMemorySegment::getModel()->getTable();
            $count = 1000;

            $sources = DB::select("
                SELECT
                    source
                FROM $table
                ORDER BY random()
                LIMIT $count
            ");

            $segments = collect($sources)->map(function ($sourceObj, $i) use ($job) {
                return [
                    'id' => Str::uuid()->toString(),
                    'job_id' => $job->id,
                    'position' => $i,
                    'xliff_internal_id' => "$i",
                    'xliff_mrk_id' => '0',
                    'source' => $sourceObj->source,
                ];
            })->toArray();

            Segment::insert($segments);


            Bus::chain([
                new DetectRepetitionsJob($job),
                new PretranslateJob($job),
            ])->dispatch();
        });
    }
}
