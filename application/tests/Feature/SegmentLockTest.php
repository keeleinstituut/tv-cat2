<?php

namespace Tests\Feature;

use App\Jobs\PretranslateJob;
use App\Models\Job;
use App\Models\Project;
use App\Models\Segment;
use App\Models\TranslationMemory;
use App\Models\TranslationMemorySegment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Needs Postgres (pg_trgm) — run against a throwaway DB, e.g. DB_DATABASE=app_test.
 */
class SegmentLockTest extends TestCase
{
    use DatabaseTransactions;

    private Project $project;
    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::create(['name' => 'lock-test', 'source_locale' => 'en']);
        $this->job = Job::create(['project_id' => $this->project->id, 'target_locale' => 'et']);
    }

    private function actingAsServiceAccount(): static
    {
        return $this->actingAs(new User(['name' => 'service-account-test']), 'web');
    }

    private function makeSegment(int $position, string $source, array $attrs = []): Segment
    {
        // forceFill: repetition_group (and friends) are not mass-assignable
        return tap((new Segment())->forceFill(array_merge([
            'job_id'   => $this->job->id,
            'position' => $position,
            'source'   => $source,
            'target'   => null,
            'xliff_internal_id' => "tu-$position",
            'xliff_mrk_id'      => "mrk-$position",
        ], $attrs)))->save();
    }

    /** TM attached to the project (read-only), with one entry per $entries row. */
    private function makeTm(array $entries): void
    {
        $tm = TranslationMemory::create([
            'name' => 'lock-test-tm', 'source_locale' => 'en', 'target_locale' => 'et',
        ]);
        $this->project->translationMemories()->attach($tm->id, ['read' => true, 'write' => false]);

        foreach ($entries as $entry) {
            TranslationMemorySegment::create(array_merge(['translation_memory_id' => $tm->id], $entry));
        }
    }

    // ---------------------------------------------------------------- update

    public function test_locked_segment_rejects_target_and_confirm_changes(): void
    {
        $segment = $this->makeSegment(1, 'Hello', ['target' => 'Tere', 'locked' => true]);

        $this->actingAsServiceAccount()
            ->putJson("/api/segments/{$segment->id}", ['target' => 'Changed'])
            ->assertStatus(423);

        $this->actingAsServiceAccount()
            ->putJson("/api/segments/{$segment->id}", ['confirmed' => true])
            ->assertStatus(423);

        $segment->refresh();
        $this->assertSame('Tere', $segment->target);
        $this->assertFalse($segment->confirmed);
        $this->assertTrue($segment->locked);
    }

    public function test_locked_segment_can_be_unlocked_then_edited_then_locked_again(): void
    {
        $segment = $this->makeSegment(1, 'Hello', ['target' => 'Tere', 'locked' => true]);

        $this->actingAsServiceAccount()
            ->putJson("/api/segments/{$segment->id}", ['locked' => false])
            ->assertOk()
            ->assertJsonPath('data.locked', false);

        $this->actingAsServiceAccount()
            ->putJson("/api/segments/{$segment->id}", ['target' => 'Changed'])
            ->assertOk();
        $this->assertSame('Changed', $segment->refresh()->target);

        $this->actingAsServiceAccount()
            ->putJson("/api/segments/{$segment->id}", ['locked' => true])
            ->assertOk()
            ->assertJsonPath('data.locked', true);
    }

    public function test_repetition_propagation_skips_locked_siblings(): void
    {
        $group = (string) Str::uuid();
        $a = $this->makeSegment(1, 'Same', ['repetition_group' => $group, 'target' => 'old']);
        $b = $this->makeSegment(2, 'Same', ['repetition_group' => $group, 'target' => 'old']);
        $locked = $this->makeSegment(3, 'Same', ['repetition_group' => $group, 'target' => 'old', 'locked' => true]);

        $this->actingAsServiceAccount()
            ->putJson("/api/segments/{$a->id}", ['target' => 'new'])
            ->assertOk();

        $this->assertSame('new', $a->refresh()->target);
        $this->assertSame('new', $b->refresh()->target);
        $this->assertSame('old', $locked->refresh()->target);
    }

    // ------------------------------------------------------------------ bulk

    public function test_bulk_update_skips_locked_segments(): void
    {
        $open = $this->makeSegment(1, 'One', ['target' => 'x']);
        $locked = $this->makeSegment(2, 'Two', ['target' => 'y', 'locked' => true]);

        $this->actingAsServiceAccount()
            ->putJson('/api/segments/bulk', [
                'job_id'      => $this->job->id,
                'segment_ids' => [$open->id, $locked->id],
                'confirmed'   => true,
            ])
            ->assertOk()
            ->assertJsonPath('meta.count', 1);

        $this->assertTrue($open->refresh()->confirmed);
        $this->assertFalse($locked->refresh()->confirmed);
    }

    // --------------------------------------------------------------- filters

    public function test_index_filters_by_locked_state(): void
    {
        $open = $this->makeSegment(1, 'One');
        $locked = $this->makeSegment(2, 'Two', ['locked' => true]);

        $ids = fn(array $q) => collect(
            $this->actingAsServiceAccount()
                ->getJson('/api/segments?' . http_build_query(['job_id' => $this->job->id] + $q))
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();

        $this->assertSame([$locked->id], $ids(['filter_locked' => 1]));
        $this->assertSame([$open->id], $ids(['filter_not_locked' => 1]));
        $this->assertEqualsCanonicalizing([$open->id, $locked->id], $ids([]));
    }

    // ------------------------------------------------------------ pretranslate

    private function seedPerfectAndPlainMatches(): array
    {
        $this->makeTm([
            // Same text AND same neighbours as segment 2 -> 101
            ['source' => 'Hello world', 'target' => 'Tere maailm',
                'source_context_before' => 'Intro line', 'source_context_after' => 'Outro line'],
            // Same text, different neighbours as segment 4 -> 100
            ['source' => 'Goodbye moon', 'target' => 'Nägemist kuu',
                'source_context_before' => 'Something else', 'source_context_after' => 'Another thing'],
        ]);

        return [
            $this->makeSegment(1, 'Intro line'),
            $this->makeSegment(2, 'Hello world'),
            $this->makeSegment(3, 'Outro line'),
            $this->makeSegment(4, 'Goodbye moon'),
        ];
    }

    public function test_pretranslate_locks_only_101_tm_matches(): void
    {
        [, $perfect, , $plain] = $this->seedPerfectAndPlainMatches();

        (new PretranslateJob($this->job))->handle();

        $perfect->refresh();
        $this->assertSame('Tere maailm', $perfect->target);
        $this->assertGreaterThanOrEqual(101, $perfect->pretranslate_suggestion_score);
        $this->assertTrue($perfect->locked);

        $plain->refresh();
        $this->assertSame('Nägemist kuu', $plain->target);
        $this->assertEquals(100, $plain->pretranslate_suggestion_score);
        $this->assertFalse($plain->locked);
    }

    public function test_pretranslate_does_not_lock_when_disabled(): void
    {
        [, $perfect] = $this->seedPerfectAndPlainMatches();

        (new PretranslateJob($this->job, lockPerfectMatches: false))->handle();

        $perfect->refresh();
        $this->assertSame('Tere maailm', $perfect->target);
        $this->assertFalse($perfect->locked);
    }

    public function test_pretranslate_does_not_overwrite_locked_segments(): void
    {
        [, $perfect] = $this->seedPerfectAndPlainMatches();
        $perfect->update(['target' => 'Manual translation', 'locked' => true]);

        (new PretranslateJob($this->job))->handle();

        $perfect->refresh();
        $this->assertSame('Manual translation', $perfect->target);
        $this->assertTrue($perfect->locked);
    }

    public function test_pretranslate_does_not_overwrite_locked_repetition_sibling(): void
    {
        $this->makeTm([['source' => 'Repeated', 'target' => 'Korduv']]);
        $group = (string) Str::uuid();
        $open = $this->makeSegment(1, 'Repeated', ['repetition_group' => $group]);
        $locked = $this->makeSegment(2, 'Repeated', [
            'repetition_group' => $group, 'target' => 'Kept', 'locked' => true,
        ]);

        (new PretranslateJob($this->job))->handle();

        $this->assertSame('Korduv', $open->refresh()->target);
        $this->assertSame('Kept', $locked->refresh()->target);
    }
}
