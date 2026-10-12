<?php

namespace Tests\Feature;

use App\Jobs\ExportXliffJob;
use App\Models\Job;
use App\Models\Project;
use App\Models\Segment;
use App\Models\User;
use App\Services\XliffExporter;
use DOMDocument;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Needs Postgres, run against a throwaway DB (DB_DATABASE=app_test, the phpunit default). */
class XliffDownloadTest extends TestCase
{
    use DatabaseTransactions;

    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $project = Project::create(['name' => 'xliff-test', 'source_locale' => 'en-US']);
        $this->job = $this->makeJob($project, 'Report.docx');
    }

    private function makeJob(Project $project, string $fileName): Job
    {
        $job = Job::create(['project_id' => $project->id, 'target_locale' => 'et-EE']);
        $job->addMediaFromString('original bytes')->usingFileName($fileName)->toMediaCollection(Job::SOURCE_FILE_COLLECTION);

        return $job;
    }

    private function segment(Job $job, int $position, string $source, ?string $target = null, array $attrs = []): Segment
    {
        return tap((new Segment())->forceFill(array_merge([
            'job_id' => $job->id, 'position' => $position, 'source' => $source, 'target' => $target,
            'filter_segment_id' => "tu$position#0",
        ], $attrs)))->save();
    }

    private function export(): DOMDocument
    {
        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML((new XliffExporter())->export($this->job)), 'The export is well-formed XML');

        return $doc;
    }

    /** @return \DOMElement[] by segment id */
    private function units(DOMDocument $doc): array
    {
        $units = [];
        foreach ($doc->getElementsByTagName('trans-unit') as $unit) {
            $units[$unit->getAttribute('id')] = $unit;
        }

        return $units;
    }

    public function test_the_document_describes_the_job(): void
    {
        $this->segment($this->job, 0, 'Hello');

        $doc = $this->export();
        $file = $doc->getElementsByTagName('file')->item(0);

        $this->assertSame('1.2', $doc->documentElement->getAttribute('version'));
        $this->assertSame('urn:oasis:names:tc:xliff:document:1.2', $doc->documentElement->namespaceURI);
        $this->assertSame('Report.docx', $file->getAttribute('original'));
        $this->assertSame('en-US', $file->getAttribute('source-language'));
        $this->assertSame('et-EE', $file->getAttribute('target-language'));
    }

    public function test_segments_are_exported_in_order_with_their_state(): void
    {
        $empty = $this->segment($this->job, 0, 'No target yet');
        $draft = $this->segment($this->job, 1, 'Draft', 'Mustand', ['pretranslate_suggestion_provider_type' => 'TM', 'pretranslate_suggestion_score' => 87]);
        $exact = $this->segment($this->job, 2, 'Exact', 'Täpne', ['pretranslate_suggestion_provider_type' => 'TM', 'pretranslate_suggestion_score' => 101]);
        $machine = $this->segment($this->job, 3, 'Machine', 'Masin', ['pretranslate_suggestion_provider_type' => 'MT']);
        $done = $this->segment($this->job, 4, 'Done', 'Valmis', ['confirmed' => true]);
        $locked = $this->segment($this->job, 5, 'Locked', 'Lukus', ['confirmed' => true, 'locked' => true]);

        $doc = $this->export();
        $units = $this->units($doc);

        $this->assertSame(array_map(fn ($s) => $s->id, [$empty, $draft, $exact, $machine, $done, $locked]), array_keys($units));
        $this->assertSame('tu1#0', $units[$draft->id]->getAttribute('resname'));

        $targetOf = fn (Segment $s) => $units[$s->id]->getElementsByTagName('target')->item(0);
        $this->assertNull($targetOf($empty));
        $this->assertSame(['needs-review-translation', 'fuzzy-match'], [$targetOf($draft)->getAttribute('state'), $targetOf($draft)->getAttribute('state-qualifier')]);
        $this->assertSame('exact-match', $targetOf($exact)->getAttribute('state-qualifier'));
        $this->assertSame('mt-suggestion', $targetOf($machine)->getAttribute('state-qualifier'));
        $this->assertSame(['translated', ''], [$targetOf($done)->getAttribute('state'), $targetOf($done)->getAttribute('state-qualifier')]);
        $this->assertSame('yes', $units[$done->id]->getAttribute('approved'));
        $this->assertSame('final', $targetOf($locked)->getAttribute('state'));
        $this->assertSame('', $units[$draft->id]->getAttribute('approved'));
        $this->assertSame('Pretranslation: TM 87%', $units[$draft->id]->getElementsByTagName('note')->item(0)->textContent);
        $this->assertSame('et-EE', $targetOf($draft)->getAttribute('xml:lang'));
        $this->assertSame('Täpne', $targetOf($exact)->textContent);
    }

    public function test_formatting_becomes_inline_elements_with_matching_ids(): void
    {
        $segment = $this->segment($this->job, 0, 'Plain <b>bold</b> and x<sup>2</sup> &amp; more', '<sup>2</sup>x ja <b>paks</b> &amp; veel<br>rida');

        $unit = $this->units($this->export())[$segment->id];
        $inner = fn (string $tag) => trim(implode('', array_map(
            fn ($n) => $unit->ownerDocument->saveXML($n),
            iterator_to_array($unit->getElementsByTagName($tag)->item(0)->childNodes)
        )));

        $this->assertSame('Plain <g id="1" ctype="bold">bold</g> and x<g id="2" ctype="x-superscript">2</g> &amp; more', $inner('source'));
        $this->assertSame('<g id="2" ctype="x-superscript">2</g>x ja <g id="1" ctype="bold">paks</g> &amp; veel<x id="3" ctype="lb"/>rida', $inner('target'));
    }

    public function test_a_job_without_segments_is_a_valid_empty_document(): void
    {
        $this->assertCount(0, $this->units($this->export()));
    }

    // ------------------------------------------------------------ download endpoint

    private function download(array $jobIds, string $type)
    {
        return $this->actingAs(new User(['name' => 'service-account-test']), 'web')
            ->postJson('/api/download', ['type' => $type, 'job_ids' => $jobIds]);
    }

    public function test_the_xliff_can_be_downloaded(): void
    {
        $this->segment($this->job, 0, 'Hello <b>world</b>', 'Tere <b>maailm</b>', ['confirmed' => true]);

        $response = $this->download([$this->job->id], 'xliff')->assertOk();

        $this->assertStringContainsString('Report.docx.xlf', $response->headers->get('content-disposition'));
        $media = $this->job->xliffFileCollection()->first();
        $this->assertSame('Report.docx.xlf', $media->file_name);
        $this->assertStringContainsString('<g id="1" ctype="bold">maailm</g>', Storage::disk('public')->get($media->getPathRelativeToRoot()));
    }

    public function test_several_jobs_are_downloaded_as_a_zip(): void
    {
        $other = $this->makeJob($this->job->project, 'Second.xml');
        $this->segment($this->job, 0, 'A');
        $this->segment($other, 0, 'B');

        $response = $this->download([$this->job->id, $other->id], 'xliff')->assertOk();

        $this->assertStringContainsString('files.zip', $response->headers->get('content-disposition'));
    }

    public function test_unknown_download_types_are_still_refused(): void
    {
        $this->download([$this->job->id], 'target_xliff')->assertUnprocessable();
    }

    public function test_the_export_job_replaces_the_previous_file(): void
    {
        $this->segment($this->job, 0, 'One');
        ExportXliffJob::dispatchSync($this->job);
        $this->segment($this->job, 1, 'Two');
        ExportXliffJob::dispatchSync($this->job);

        $this->assertSame(1, $this->job->xliffFileCollection()->count());
        $this->assertStringContainsString('Two', Storage::disk('public')->get($this->job->xliffFileCollection()->first()->getPathRelativeToRoot()));
    }
}
