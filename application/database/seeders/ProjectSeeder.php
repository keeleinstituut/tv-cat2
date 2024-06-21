<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

use App\Models\Project;
use App\Models\Job;
use App\Models\Segment;
use App\Models\Media;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Project::factory()
            ->count(2)
            ->has(
                Job::factory()
                    ->count(2)
                    ->has(
                        Media::factory()
                            ->count(2)
                            ->sequence([
                                'name' => 'seeded-file',
                                'file_name' => 'seeded-file.txt',
                                'collection_name' => 'source',
                            ],
                            [
                                'name' => 'seeded-file.txt',
                                'file_name' => 'seeded-file.txt.xliff',
                                'collection_name' => 'xliff',
                            ])
                    )
                    ->has(
                        Segment::factory()
                            ->count(100)
                            ->state(new Sequence(function ($seq) {
                                return [
                                    'position' => $seq->index,
                                    'xliff_internal_id' => "$seq->index",
                                    'xliff_mrk_id' => '0',
                                ];
                            }))
                    )
            )
            ->create();

    }
}
