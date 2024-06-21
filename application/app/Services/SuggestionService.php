<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\Dto\GetSuggestionsOptions;
use App\Models\TranslationMemorySegment;


class SuggestionService
{
    public static function getSuggestions(GetSuggestionsOptions $options) {
        $providerFunctions = [
            'mt' => fn () => LibreTranslateService::translateSegments($options),
            'nt' => fn () => NoTranslateService::getSuggestions($options),
            'tm' => fn () => InternalTranslationMemoryService::getSuggestions($options),
        ];

        return collect($providerFunctions)
            ->map(function ($providerFunction, $key) use ($options) {
                if ($options->providers == null || in_array($key, $options->providers)) {
                    return $providerFunction();
                }
                return [];
            })
            ->flatten(1)
            ->toArray();
    }
}
