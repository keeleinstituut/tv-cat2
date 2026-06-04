<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Services\Dto\GetSuggestionsOptions;


class LibreTranslateService
{
    private const CHUNK_SIZE = 10;

    public static function translateSegments(GetSuggestionsOptions $options) {
        $q = $options->getQ();
        $batch = self::translateBatch([$q], $options->sourceLocale, $options->targetLocale);
        return $batch[$q] ?? [];
    }

    public static function translateBatch(array $sources, string $sourceLocale, string $targetLocale): array {
        if (empty($sources)) {
            return [];
        }

        $chunks = array_chunk($sources, self::CHUNK_SIZE);
        $src = self::transformLocale($sourceLocale);
        $tgt = self::transformLocale($targetLocale);

        $result = [];
        foreach ($chunks as $chunk) {
            $response = Http::post('http://host.docker.internal:6003/translate', [
                'q'      => $chunk,
                'source' => $src,
                'target' => $tgt,
            ]);

            $body = $response->json();
            if (isset($body['error'])) {
                throw new \Exception("Error from LibreTranslate: " . $body['error'], 1);
            }

            foreach ($chunk as $i => $source) {
                $result[$source] = [[
                    'provider' => ['type' => 'MT', 'name' => 'LibreTranslate'],
                    'source'   => $source,
                    'target'   => $body['translatedText'][$i],
                ]];
            }
        }

        return $result;
    }

    private static function transformLocale($locale)
    {
        return Str::of($locale)->explode('-')[0];
    }
}
