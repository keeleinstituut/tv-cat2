<?php

namespace Tests\Feature;

use App\Exceptions\UnsupportedFileTypeException;
use App\Jobs\ExtractSegmentsJob;
use App\Jobs\MergeSegmentsJob;
use App\Models\Job;
use App\Models\Project;
use App\Models\Segment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Needs Postgres, run against a throwaway DB (DB_DATABASE=app_test). */
class FiltersPipelineTest extends TestCase
{
    use DatabaseTransactions;

    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['services.filters.base_url' => 'http://filters.test']);

        $project = Project::create(['name' => 'pipeline-test', 'source_locale' => 'en-US']);
        $this->job = Job::create(['project_id' => $project->id, 'target_locale' => 'et-EE']);
        $this->job->addMediaFromString('original bytes')->usingFileName('report.docx')->toMediaCollection(Job::SOURCE_FILE_COLLECTION);
    }

    public function test_new_jobs_are_processing_until_extracted(): void
    {
        $this->assertSame(Job::STATUS_PROCESSING, $this->job->refresh()->status);
    }

    public function test_extract_creates_the_segments_in_order_and_marks_the_job_ready(): void
    {
        Http::fake(['filters.test/extract' => Http::response(['segments' => collect(range(0, 1204))->map(fn ($i) => [
            'id' => "tu$i#0", 'text' => $i === 1 ? 'Hello <b>world</b> &amp; all' : "Segment $i",
        ])->all()])]);

        ExtractSegmentsJob::dispatchSync($this->job);

        $segments = Segment::where('job_id', $this->job->id)->orderBy('position')->get();
        $this->assertCount(1205, $segments);
        $this->assertSame(range(0, 1204), $segments->pluck('position')->all());
        $this->assertSame('tu1#0', $segments[1]->filter_segment_id);
        $this->assertSame('Hello <b>world</b> &amp; all', $segments[1]->source);
        $this->assertNull($segments[1]->target);
        $this->assertFalse($segments[1]->confirmed);

        $this->job->refresh();
        $this->assertSame(Job::STATUS_READY, $this->job->status);
        $this->assertNull($this->job->error);
        Http::assertSent(fn (Request $r) => $r->url() === 'http://filters.test/extract');
    }

    public function test_extract_of_an_unsupported_file_marks_the_job_failed(): void
    {
        Http::fake(['filters.test/extract' => Http::response(['error' => 'Unsupported file type: report.docx'], 415)]);

        try {
            ExtractSegmentsJob::dispatchSync($this->job);
            $this->fail('The unsupported file should fail the job');
        } catch (UnsupportedFileTypeException) {
            // expected
        }

        $this->job->refresh();
        $this->assertSame(Job::STATUS_FAILED, $this->job->status);
        $this->assertStringContainsString('Unsupported file type', $this->job->error);
        $this->assertSame(0, Segment::where('job_id', $this->job->id)->count());
    }

    public function test_merge_sends_only_segments_with_a_target_and_stores_the_file(): void
    {
        $make = fn (int $position, ?string $target, ?string $filterId) => (new Segment())->forceFill([
            'job_id' => $this->job->id, 'position' => $position, 'source' => "Source $position",
            'target' => $target, 'filter_segment_id' => $filterId,
        ])->save();
        $make(0, 'Tere <b>maailm</b>', 'tu0#0');
        $make(1, null, 'tu1#0');
        $make(2, '', 'tu2#0');
        $make(3, 'Ilma id-ta', null);
        $make(4, 'Ä &amp; Ö', 'tu4#0');

        Http::fake(['filters.test/merge' => Http::response('translated bytes')]);

        MergeSegmentsJob::dispatchSync($this->job);

        Http::assertSent(function (Request $request) {
            $parts = collect($request->data())->mapWithKeys(fn ($p) => [$p['name'] => $p['contents']]);

            return json_decode($parts['translations'], true) === ['tu0#0' => 'Tere <b>maailm</b>', 'tu4#0' => 'Ä &amp; Ö']
                && $parts['file'] !== ''
                && $parts['sourceLocale'] === 'en-US'
                && $parts['targetLocale'] === 'et-EE';
        });

        $target = $this->job->targetFileCollection()->first();
        $this->assertSame('report.docx', $target->file_name);
        $this->assertSame('translated bytes', Storage::disk('public')->get($target->getPathRelativeToRoot()));
    }
}
