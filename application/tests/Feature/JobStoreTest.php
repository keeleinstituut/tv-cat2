<?php

namespace Tests\Feature;

use App\Jobs\DetectRepetitionsJob;
use App\Jobs\ExtractSegmentsJob;
use App\Models\Job;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Creating a job: file type check, status and the extract chain. Needs Postgres (DB_DATABASE=app_test). */
class JobStoreTest extends TestCase
{
    use DatabaseTransactions;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Cache::flush();
        config(['services.filters.base_url' => 'http://filters.test']);

        $this->project = Project::create(['name' => 'store-test', 'source_locale' => 'en-US']);
    }

    private function store(UploadedFile $file)
    {
        return $this->actingAs(new User(['name' => 'service-account-test']), 'web')
            ->post('/api/jobs', ['project_id' => $this->project->id, 'target_locale' => 'et-EE', 'source_files' => [$file]], ['Accept' => 'application/json']);
    }

    public function test_a_supported_file_creates_a_processing_job_and_dispatches_the_extract_chain(): void
    {
        Http::fake(['filters.test/formats' => Http::response(['extensions' => ['docx', 'xml']])]);
        Bus::fake();

        $response = $this->store(UploadedFile::fake()->createWithContent('Report.DOCX', 'bytes'));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.source_file.file_name', 'Report.DOCX');

        $job = Job::findOrFail($response->json('data.id'));
        $this->assertSame(Job::STATUS_PROCESSING, $job->status);
        Bus::assertChained([
            fn (ExtractSegmentsJob $j) => true,
            fn (DetectRepetitionsJob $j) => true,
        ]);
    }

    public function test_an_unsupported_file_type_is_refused(): void
    {
        Http::fake(['filters.test/formats' => Http::response(['extensions' => ['docx', 'xml']])]);
        Bus::fake();

        $this->store(UploadedFile::fake()->createWithContent('picture.png', 'bytes'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_files.0');

        Bus::assertNothingDispatched();
        $this->assertSame(0, Job::where('project_id', $this->project->id)->count());
    }

    public function test_when_the_filters_service_is_down_the_upload_is_accepted(): void
    {
        Http::fake(['filters.test/formats' => Http::response('down', 503)]);
        Bus::fake();

        $this->store(UploadedFile::fake()->createWithContent('anything.xyz', 'bytes'))->assertCreated();

        Bus::assertChained([ExtractSegmentsJob::class, DetectRepetitionsJob::class]);
    }
}
