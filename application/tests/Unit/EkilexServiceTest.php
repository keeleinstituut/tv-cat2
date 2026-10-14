<?php

namespace Tests\Unit;

use App\Services\Dto\GetSuggestionsOptions;
use App\Services\EkilexService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EkilexServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.ekilex.base_url' => 'https://ekilex.test/api',
            'services.ekilex.api_key' => 'secret-key',
        ]);
    }

    private function makeOptions(string $q = 'maja', ?string $dataset = null): GetSuggestionsOptions
    {
        return GetSuggestionsOptions::make()
            ->setQ($q)
            ->setSourceLocale('et')
            ->setTargetLocale('en')
            ->setDataset($dataset);
    }

    private function fakeSearch(array $overrides = []): void
    {
        Http::fake($overrides + [
            '*/word/search/*' => Http::response(['words' => [
                ['wordId' => 1, 'lang' => 'est'],
                ['wordId' => 2, 'lang' => 'eng'],
            ]]),
            '*/word/details/10*' => Http::response(['lexemes' => [[
                'usages' => [['value' => 'This is a house.']],
                'lexemeNoteLangGroups' => [['notes' => [['valueText' => 'common term']]]],
            ]]]),
            '*/word/details/1*' => Http::response(['word' => ['wordValue' => 'maja'], 'lexemes' => [
                [
                    'datasetName' => 'Ehitus',
                    'meaning' => ['definitions' => [['value' => '<b>building</b> for living']]],
                    'synonymLangGroups' => [
                        ['lang' => 'eng', 'synonyms' => [['words' => [['wordId' => 10, 'wordValue' => 'house']]]]],
                        ['lang' => 'rus', 'synonyms' => [['words' => [['wordId' => 11, 'wordValue' => 'dom']]]]],
                    ],
                ],
                [
                    // no "meaning" key, and the same synonym again
                    'datasetName' => 'Ehitus',
                    'synonymLangGroups' => [
                        ['lang' => 'eng', 'synonyms' => [['words' => [['wordId' => 10, 'wordValue' => 'house']]]]],
                    ],
                ],
            ]]),
        ]);
    }

    public function test_get_suggestions_maps_synonyms_in_target_language(): void
    {
        $this->fakeSearch();

        $result = EkilexService::getSuggestions($this->makeOptions());

        $this->assertCount(1, $result);
        $this->assertSame('TB', $result[0]['provider']['type']);
        $this->assertSame('Ekilex', $result[0]['provider']['name']);
        $this->assertSame('maja', $result[0]['source']);
        $this->assertSame('house', $result[0]['target']);
        $this->assertSame('building for living', $result[0]['meta']['definition']);
        $this->assertSame('Ehitus', $result[0]['meta']['dataset']);
        $this->assertSame('common term', $result[0]['meta']['note']);
        $this->assertSame('This is a house.', $result[0]['meta']['sentence']);
    }

    public function test_get_suggestions_sends_api_key_and_dataset(): void
    {
        $this->fakeSearch();

        EkilexService::getSuggestions($this->makeOptions('maja', 'ehitus'));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/word/search/maja/ehitus')
            && $r->header('ekilex-api-key') === ['secret-key']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/word/details/1/ehitus'));
    }

    public function test_get_suggestions_is_cached(): void
    {
        $this->fakeSearch();

        $first = EkilexService::getSuggestions($this->makeOptions());
        $count = count(Http::recorded());
        $second = EkilexService::getSuggestions($this->makeOptions());

        $this->assertSame($first, $second);
        $this->assertCount($count, Http::recorded());
    }

    public function test_get_suggestions_returns_empty_on_http_error(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->assertSame([], EkilexService::getSuggestions($this->makeOptions()));
    }

    public function test_get_suggestions_returns_empty_for_unsupported_language(): void
    {
        Http::fake();

        $options = $this->makeOptions()->setTargetLocale('xx');

        $this->assertSame([], EkilexService::getSuggestions($options));
        Http::assertNothingSent();
    }

    public function test_get_datasets_filters_hidden_and_sorts(): void
    {
        Http::fake(['*/datasets' => Http::response([
            ['code' => 'b', 'name' => 'Bravo', 'visible' => true],
            ['code' => 'h', 'name' => 'Hidden', 'visible' => false],
            ['code' => 'a', 'name' => 'Alpha', 'visible' => true],
        ])]);

        $this->assertSame([
            ['id' => 'a', 'name' => 'Alpha'],
            ['id' => 'b', 'name' => 'Bravo'],
        ], EkilexService::getDatasets());
    }
}
