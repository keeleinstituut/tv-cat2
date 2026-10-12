<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Project;
use App\Models\Segment;
use App\Models\TranslationMemory;
use App\Models\TranslationMemorySegment;
use App\Models\User;
use App\Services\InternalTranslationMemoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Text entering the backend is brought into the segment text form (b i u s sup sub br, escaped & < >). */
class SegmentTextEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        $project = Project::create(['name' => 'text-test', 'source_locale' => 'en']);
        $this->job = Job::create(['project_id' => $project->id, 'target_locale' => 'et']);
    }

    private function asUser(): static
    {
        return $this->actingAs(new User(['name' => 'service-account-test']), 'web');
    }

    private function segment(string $source, ?string $target = null): Segment
    {
        return tap((new Segment())->forceFill([
            'job_id' => $this->job->id, 'position' => Segment::where('job_id', $this->job->id)->count(),
            'source' => $source, 'target' => $target,
        ]))->save();
    }

    public function test_editor_output_is_normalized_when_a_target_is_saved(): void
    {
        $segment = $this->segment('Hello');

        $this->asUser()
            ->putJson("/api/segments/{$segment->id}", ['target' => '<strong>Tere</strong>&nbsp;<span style="x">maailm</span> &amp; <em>kõik</em><div>teine rida</div>'])
            ->assertOk();

        $this->assertSame("<b>Tere</b>\u{00A0}maailm &amp; <i>kõik</i><br>teine rida", $segment->refresh()->target);
    }

    public function test_bulk_update_normalizes_the_target(): void
    {
        $a = $this->segment('A');
        $b = $this->segment('B');

        $this->asUser()
            ->putJson('/api/segments/bulk', ['job_id' => $this->job->id, 'segment_ids' => [$a->id, $b->id], 'target' => 'x&nbsp;<font>y</font>'])
            ->assertOk();

        $this->assertSame("x\u{00A0}y", $a->refresh()->target);
        $this->assertSame("x\u{00A0}y", $b->refresh()->target);
    }

    public function test_search_finds_typed_text_in_escaped_segment_text(): void
    {
        $this->segment('Tom &amp; Jerry <b>win</b>');
        $this->segment('100% sure');
        $this->segment('plain');

        $search = fn (string $q) => $this->asUser()
            ->getJson('/api/segments?' . http_build_query(['job_id' => $this->job->id, 'source' => $q]))
            ->assertOk()->json('data.*.source');

        $this->assertSame(['Tom &amp; Jerry <b>win</b>'], $search('Tom & Jerry'));
        $this->assertSame(['100% sure'], $search('100%'));
        // % and _ are not wildcards
        $this->assertSame([], $search('p%n'));
        $this->assertSame([], $search('pl_in'));
    }

    public function test_replace_escapes_the_typed_text(): void
    {
        $segment = $this->segment('Source', 'Tom &amp; Jerry and Tom');

        $this->asUser()
            ->putJson('/api/segments/replace', ['job_id' => $this->job->id, 'target' => 'Tom & Jerry', 'replace_target' => 'A < B & C'])
            ->assertOk()
            ->assertJsonPath('meta.count', 1);

        $this->assertSame('A &lt; B &amp; C and Tom', $segment->refresh()->target);
    }

    public function test_tm_segment_edits_are_normalized(): void
    {
        $tm = TranslationMemory::create(['name' => 'tm', 'source_locale' => 'en', 'target_locale' => 'et']);
        $entry = TranslationMemorySegment::create(['translation_memory_id' => $tm->id, 'source' => 'a', 'target' => 'b']);

        $this->asUser()
            ->putJson("/api/translation-memory-segments/{$entry->id}", ['source' => '<strong>a</strong>&nbsp;x', 'target' => 'b <span>c</span>'])
            ->assertOk();

        $entry->refresh();
        $this->assertSame("<b>a</b>\u{00A0}x", $entry->source);
        $this->assertSame('b c', $entry->target);
    }

    public function test_tmx_import_brings_text_into_the_segment_text_form(): void
    {
        $tm = TranslationMemory::create(['name' => 'tm', 'source_locale' => 'en', 'target_locale' => 'et']);
        $tmx = tempnam(sys_get_temp_dir(), 'tmx');
        file_put_contents($tmx, '<?xml version="1.0"?><tmx version="1.4"><header srclang="en"/><body>'
            . '<tu><tuv xml:lang="en"><seg>Tom &amp; Jerry &lt; 3</seg></tuv><tuv xml:lang="et"><seg>Tom ja Jerry &lt;b&gt;paks&lt;/b&gt;</seg></tuv></tu>'
            . '</body></tmx>');

        InternalTranslationMemoryService::importSegments($tm->id, $tmx);
        unlink($tmx);

        $entry = TranslationMemorySegment::where('translation_memory_id', $tm->id)->firstOrFail();
        $this->assertSame('Tom &amp; Jerry &lt; 3', $entry->source);
        $this->assertSame('Tom ja Jerry <b>paks</b>', $entry->target);
    }
}
