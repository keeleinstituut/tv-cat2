<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Services\Dto\GetSuggestionsOptions;


class LibreTranslateService
{
    // private static $base = "http://matecat-filters:8732";

    public static function translateSegments(GetSuggestionsOptions $options) {
        // return Cache::remember("service.libretranslate/$options->q", 86400, function () use ($options) {
            $responses = Http::pool(fn (Pool $pool) => [
                $pool->post('http://host.docker.internal:6003/translate', [
                    'q' => $options->q,
                    'source' => self::transformLocale($options->sourceLocale),
                    'target' => self::transformLocale($options->targetLocale),
                ]),
            ]);

            $mtResponse = $responses[0]->json();

            if (isset($mtResponse['error'])) {
                throw new \Exception("Error from LibreTranslate: " . $mtResponse['error'], 1);
            }

            return [
                [
                    'provider' => [
                        'type' => 'MT',
                        'name' => 'LibreTranslate'
                    ],
                    'source' => $options->q,
                    'target' => $mtResponse['translatedText'],
                ],
            ];
        // });
    }

    private static function transformLocale($locale)
    {
        return Str::of($locale)->explode('-')[0];
    }

    // private static function client() {
    //     return Http::baseUrl(static::$base);
    // }
}
