<?php

namespace Tests\Unit;

use App\Services\LibreTranslateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LibreTranslateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_translate_batch_caches_result_and_skips_api_on_second_call(): void
    {
        Http::fake([
            '*/translate' => Http::response(['translatedText' => ['tere']]),
        ]);

        $first = LibreTranslateService::translateBatch(['hello'], 'en', 'et');
        $second = LibreTranslateService::translateBatch(['hello'], 'en', 'et');

        Http::assertSentCount(1);
        $this->assertSame('tere', $first['hello'][0]['target']);
        $this->assertSame($first, $second);
    }

    public function test_translate_batch_only_sends_uncached_sources(): void
    {
        Http::fake([
            '*/translate' => Http::sequence()
                ->push(['translatedText' => ['tere']])
                ->push(['translatedText' => ['nägemist']]),
        ]);

        LibreTranslateService::translateBatch(['hello'], 'en', 'et');

        $result = LibreTranslateService::translateBatch(['hello', 'bye'], 'en', 'et');

        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            return $request['q'] === ['bye'];
        });
        $this->assertSame('tere', $result['hello'][0]['target']);
        $this->assertSame('nägemist', $result['bye'][0]['target']);
    }
}
