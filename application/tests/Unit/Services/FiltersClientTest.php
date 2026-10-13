<?php

namespace Tests\Unit\Services;

use App\Exceptions\UnsupportedFileTypeException;
use App\Models\Media;
use App\Services\FiltersClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class FiltersClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.filters.base_url' => 'http://filters.test']);
    }

    private function file(string $name = 'a.docx'): Media
    {
        return new class(['file_name' => $name]) extends Media {
            public function stream()
            {
                $stream = fopen('php://temp', 'r+');
                fwrite($stream, 'file content');
                rewind($stream);

                return $stream;
            }
        };
    }

    /** @return array<string, string> the parts of a multipart request by name */
    private function parts(Request $request): array
    {
        return collect($request->data())->mapWithKeys(fn ($part) => [$part['name'] => $part['contents']])->all();
    }

    public function test_formats_are_cached(): void
    {
        Http::fake(['filters.test/formats' => Http::response(['extensions' => ['docx', 'html']])]);

        $client = new FiltersClient();

        $this->assertSame(['docx', 'html'], $client->formats());
        $this->assertSame(['docx', 'html'], $client->formats());
        Http::assertSentCount(1);
    }

    public function test_extract_sends_the_file_and_locales_and_returns_the_segments(): void
    {
        Http::fake(['filters.test/extract' => Http::response(['segments' => [
            ['id' => 'tu1#0', 'text' => 'Hello <b>world</b>'],
            ['id' => 'tu2#0', 'text' => 'Second'],
        ]])]);

        $segments = (new FiltersClient())->extract($this->file('report.docx'), 'en-US', 'et-EE');

        $this->assertSame('tu1#0', $segments[0]['id']);
        $this->assertSame('Hello <b>world</b>', $segments[0]['text']);
        Http::assertSent(function (Request $request) {
            $parts = $this->parts($request);

            return $request->url() === 'http://filters.test/extract'
                && $request->hasFile('file', null, 'report.docx')
                && $parts['sourceLocale'] === 'en-US'
                && $parts['targetLocale'] === 'et-EE'
                && str_contains($request->header('Content-Type')[0], 'multipart/form-data');
        });
    }

    public function test_merge_sends_translations_as_a_json_object_and_returns_the_file(): void
    {
        Http::fake(['filters.test/merge' => Http::response('translated bytes')]);

        $content = (new FiltersClient())->merge($this->file(), ['tu1#0' => 'Tere <b>maailm</b>', 'tu2#0' => 'Ä & Ö'], 'en', 'et');

        $this->assertSame('translated bytes', $content);
        Http::assertSent(function (Request $request) {
            return json_decode($this->parts($request)['translations'], true) === ['tu1#0' => 'Tere <b>maailm</b>', 'tu2#0' => 'Ä & Ö'];
        });
    }

    public function test_merge_without_translations_still_sends_an_object(): void
    {
        Http::fake(['filters.test/merge' => Http::response('x')]);

        (new FiltersClient())->merge($this->file(), [], 'en', 'et');

        Http::assertSent(fn (Request $request) => $this->parts($request)['translations'] === '{}');
    }

    public function test_unsupported_file_type_is_reported(): void
    {
        Http::fake(['filters.test/extract' => Http::response(['error' => 'Unsupported file type: a.xyz'], 415)]);

        $this->expectException(UnsupportedFileTypeException::class);
        $this->expectExceptionMessage('Unsupported file type: a.xyz');

        (new FiltersClient())->extract($this->file('a.xyz'), 'en', 'et');
    }

    public function test_invalid_request_is_reported(): void
    {
        Http::fake(['filters.test/extract' => Http::response(['error' => 'Missing sourceLocale'], 400)]);

        $this->expectException(InvalidArgumentException::class);

        (new FiltersClient())->extract($this->file(), '', 'et');
    }

    public function test_server_errors_are_thrown(): void
    {
        Http::fake(['filters.test/merge' => Http::response(['error' => 'boom'], 500)]);

        $this->expectException(RequestException::class);

        (new FiltersClient())->merge($this->file(), [], 'en', 'et');
    }
}
