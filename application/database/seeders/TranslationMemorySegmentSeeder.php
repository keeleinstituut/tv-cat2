<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

use App\Models\TranslationMemory;
use App\Models\TranslationMemorySegment;
use Database\Factories\TranslationMemorySegmentFactory;


class TranslationMemorySegmentSeeder extends Seeder
{
    const TOTAL_SIZE = 5000000;
    const BATCH_SIZE = 10000;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sentencesFile = file('./database/seeders/data/sentences_en.txt');
        $sentences = collect([]);

        foreach ($sentencesFile as $sentence) {
            // array_push($sentences, $sentence);
            $sentences->push($sentence);
        }

        $uuid = Str::uuid()->toString();
        $prefix = Str::of($uuid)->explode('-')[0];

        $translationMemory = new TranslationMemory();
        $translationMemory->id = $uuid;
        $translationMemory->name = '[seeded] ' . $uuid;
        $translationMemory->source_locale = 'en';
        $translationMemory->target_locale = 'et';
        $translationMemory->save();

        $sentences->chunk(500)->each(function ($chunk) use ($translationMemory, $prefix) {
            TranslationMemorySegment::insert(
                $chunk->map(fn ($sentence) => [
                    'id' => Str::uuid()->toString(),
                    'translation_memory_id' => $translationMemory->id,
                    'source' => "$prefix $sentence",
                    'target' => "translated - $prefix $sentence",
                ])->toArray()
            );
        });



        // $counter = 0;



        // $fullBatchesCount = (int) (self::TOTAL_SIZE / self::BATCH_SIZE);
        // $remainderBatch = (self::TOTAL_SIZE % self::BATCH_SIZE);
        // $batches = collect()->range(1, $fullBatchesCount)
        //     ->map(fn () => self::BATCH_SIZE)
        //     ->push($remainderBatch);


        // $batches->each(function ($batchSize) use ($sentences, $counter) {
        //     gc_collect_cycles();

        //     $batch = [];

        //     for ($i=0; $i < $batchSize; $i++) { 
        //         array_push($batch, [
        //             'id' => Str::uuid()->toString(),
        //             'source' => $sentences[$counter],
        //             'target' => 'translated - ' . $sentences[$counter],
        //         ]);

        //         $counter += 1;

        //         if ($counter >= count($sentences)) {
        //             $counter = 0;
        //         }
        //     }

        //     TranslationMemorySegment::insert($batch);
        // });
        
    }
}
