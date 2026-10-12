<?php

namespace App\Services;

use App\Support\SegmentText;
use App\Services\Dto\GetSuggestionsOptions;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Term lookup in Ekilex termbases. Only used as a search aid for translators.
 */
class EkilexService
{
    private const MAX_SOURCE_WORDS = 5;
    private const MAX_SYNONYMS_PER_LEXEME = 10;
    private const DEFAULT_LIMIT = 50;
    private const SEPARATOR = '; ';

    private static function client(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('services.ekilex.base_url'), '/'))
            ->withHeader('ekilex-api-key', (string) config('services.ekilex.api_key'))
            ->acceptJson()
            ->timeout(10);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function getDatasets(): array
    {
        try {
            return Cache::remember('ekilex:datasets', self::cacheTtl(), function () {
                $datasets = self::client()->get('/datasets')->throw()->json() ?? [];

                return collect($datasets)
                    ->filter(fn ($dataset) => $dataset['visible'] ?? false)
                    ->map(fn ($dataset) => ['id' => $dataset['code'], 'name' => $dataset['name']])
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all();
            });
        } catch (\Throwable $e) {
            Log::warning('Ekilex datasets request failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Suggestions in the shape used by SuggestionService.
     */
    public static function getSuggestions(GetSuggestionsOptions $options): array
    {
        // A terminology search takes the text a person reads, not the segment's markup
        $q = trim(SegmentText::plain($options->getQ()));
        $sourceLang = EkilexLanguageMapper::toEkilex($options->sourceLocale);
        $targetLang = EkilexLanguageMapper::toEkilex($options->targetLocale);

        if ($q === '' || !$sourceLang || !$targetLang) {
            return [];
        }

        $dataset = $options->dataset;
        $limit = $options->limit ?? self::DEFAULT_LIMIT;
        $key = 'ekilex:search:' . implode(':', [$sourceLang, $targetLang, $dataset ?? '', $limit, md5($q)]);

        try {
            return Cache::remember($key, self::cacheTtl(), fn () => self::search($q, $sourceLang, $targetLang, $dataset, $limit));
        } catch (\Throwable $e) {
            Log::warning('Ekilex search failed: ' . $e->getMessage());
            return [];
        }
    }

    private static function search(string $q, string $sourceLang, string $targetLang, ?string $dataset, int $limit): array
    {
        $path = '/word/search/' . rawurlencode($q) . '/' . rawurlencode($dataset ?? '');
        $words = self::client()->get(rtrim($path, '/'))->throw()->json('words') ?? [];

        $wordIds = collect($words)
            ->filter(fn ($word) => ($word['lang'] ?? null) === $sourceLang)
            ->pluck('wordId')
            ->unique()
            ->take(self::MAX_SOURCE_WORDS)
            ->values()
            ->all();

        if (empty($wordIds)) {
            return [];
        }

        $sourceDetails = self::getWordDetails($wordIds, $dataset);

        // Collect synonyms in the target language, de-duplicated by synonym word id.
        $synonyms = [];
        foreach ($sourceDetails as $details) {
            $sourceTerm = $details['word']['wordValue'] ?? $q;

            foreach ($details['lexemes'] ?? [] as $lexeme) {
                $count = 0;
                foreach ($lexeme['synonymLangGroups'] ?? [] as $group) {
                    if (($group['lang'] ?? null) !== $targetLang) {
                        continue;
                    }
                    foreach ($group['synonyms'] ?? [] as $synonym) {
                        foreach ($synonym['words'] ?? [] as $word) {
                            $id = $word['wordId'] ?? null;
                            if ($id === null || isset($synonyms[$id]) || $count >= self::MAX_SYNONYMS_PER_LEXEME) {
                                continue;
                            }
                            $count++;
                            $synonyms[$id] = [
                                'source' => $sourceTerm,
                                'target' => $word['wordValue'] ?? '',
                                'definition' => self::definitions($lexeme),
                                'dataset' => $lexeme['datasetName'] ?? null,
                                'source_note' => self::notes($lexeme),
                                'source_sentence' => self::sentences($lexeme),
                            ];
                        }
                    }
                }
            }
        }

        $synonyms = array_slice($synonyms, 0, $limit, true);

        if (empty($synonyms)) {
            return [];
        }

        $targetDetails = self::getWordDetails(array_keys($synonyms), $dataset);

        $suggestions = [];
        foreach ($synonyms as $id => $synonym) {
            $targetLexemes = $targetDetails[$id]['lexemes'] ?? [];

            $suggestions[] = [
                'provider' => ['type' => 'TB', 'name' => 'Ekilex'],
                'source' => $synonym['source'],
                'target' => $synonym['target'],
                'meta' => [
                    'definition' => $synonym['definition'],
                    'dataset' => $synonym['dataset'],
                    'note' => self::joinUnique(array_map(fn ($l) => self::notes($l), $targetLexemes)) ?: $synonym['source_note'],
                    'sentence' => self::joinUnique(array_map(fn ($l) => self::sentences($l), $targetLexemes)) ?: $synonym['source_sentence'],
                ],
            ];
        }

        return $suggestions;
    }

    /**
     * Fetches word details in parallel. Returns details keyed by word id; failed requests are skipped.
     */
    private static function getWordDetails(array $wordIds, ?string $dataset): array
    {
        $baseUrl = rtrim(config('services.ekilex.base_url'), '/');
        $apiKey = (string) config('services.ekilex.api_key');

        $responses = Http::pool(function (Pool $pool) use ($wordIds, $dataset, $baseUrl, $apiKey) {
            return collect($wordIds)->map(fn ($id) => $pool->as((string) $id)
                ->withHeader('ekilex-api-key', $apiKey)
                ->acceptJson()
                ->timeout(10)
                ->get(rtrim("$baseUrl/word/details/$id/" . rawurlencode($dataset ?? ''), '/'))
            )->all();
        });

        $details = [];
        foreach ($wordIds as $id) {
            $response = $responses[(string) $id] ?? null;
            if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
                $details[$id] = $response->json() ?? [];
            } else {
                Log::warning("Ekilex word details failed for word $id");
            }
        }

        return $details;
    }

    private static function definitions(array $lexeme): string
    {
        $values = array_map(fn ($d) => strip_tags($d['value'] ?? ''), $lexeme['meaning']['definitions'] ?? []);

        return self::joinUnique([self::joinUnique($values)]);
    }

    private static function notes(array $lexeme): string
    {
        $values = [];
        foreach ($lexeme['lexemeNoteLangGroups'] ?? [] as $group) {
            foreach ($group['notes'] ?? [] as $note) {
                $values[] = $note['valueText'] ?? '';
            }
        }

        return self::joinUnique($values);
    }

    private static function sentences(array $lexeme): string
    {
        $values = array_merge(
            array_map(fn ($u) => $u['value'] ?? '', $lexeme['usages'] ?? []),
            array_map(fn ($f) => $f['value'] ?? '', $lexeme['forums'] ?? []),
        );

        return self::joinUnique(array_map('strip_tags', $values));
    }

    private static function joinUnique(array $values): string
    {
        return implode(self::SEPARATOR, array_unique(array_filter(array_map('trim', $values))));
    }

    private static function cacheTtl(): int
    {
        return (int) config('services.ekilex.cache_ttl', 3600);
    }
}
