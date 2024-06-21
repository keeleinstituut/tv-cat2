<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;


/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TranslationMemorySegment>
 */
class TranslationMemorySegmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // $sourceText = fake()->text();
        $sourceText = 'asd';

        return [
            'id' => fake()->uuid(),
            'source' => $sourceText,
            'target' => 'translated-' . $sourceText,
        ];
    }
}
