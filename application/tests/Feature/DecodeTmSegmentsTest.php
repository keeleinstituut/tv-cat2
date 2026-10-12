<?php

namespace Tests\Feature;

use App\Models\TranslationMemory;
use App\Models\TranslationMemorySegment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DecodeTmSegmentsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_entity_encoded_formatting_tags_are_decoded_and_the_command_is_idempotent(): void
    {
        $tm = TranslationMemory::create(['name' => 'tm', 'source_locale' => 'et', 'target_locale' => 'en']);
        $encoded = TranslationMemorySegment::create([
            'translation_memory_id' => $tm->id,
            'source' => '(2&lt;sup&gt;1&lt;/sup&gt;) &lt;i&gt;apostille&lt;/i&gt; <sup&gt;half</sup&gt;',
            'target' => 'Tom &amp; Jerry &lt;b&gt;bold&lt;/b&gt; &lt;u&gt;kept as text&lt;/u&gt;',
        ]);
        $clean = TranslationMemorySegment::create(['translation_memory_id' => $tm->id, 'source' => 'a <b>b</b>', 'target' => 'c']);

        $this->artisan('app:decode-tm-segments')->expectsOutputToContain('Updated 1 record')->assertSuccessful();
        $this->artisan('app:decode-tm-segments')->expectsOutputToContain('Updated 0 record')->assertSuccessful();

        $this->assertSame('(2<sup>1</sup>) <i>apostille</i> <sup>half</sup>', $encoded->refresh()->source);
        // only the six formatting tags are decoded; escaped & and other tags stay as they are
        $this->assertSame('Tom &amp; Jerry <b>bold</b> &lt;u&gt;kept as text&lt;/u&gt;', $encoded->target);
        $this->assertSame('a <b>b</b>', $clean->refresh()->source);
    }
}
