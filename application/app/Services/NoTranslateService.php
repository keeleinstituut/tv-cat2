<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use App\Services\Dto\GetSuggestionsOptions;


class NoTranslateService
{
    private static $REGEX_LIST = [
        '/^[0-9]+$/',
        '/^\([0-9]+\)$/',
        '/^§ ?([0-9]+\.?)*$/',
    ];

    public static function getSuggestions(GetSuggestionsOptions $options) {
        $q = $options->getQ();
        foreach (static::$REGEX_LIST as $re) {
            if (preg_match($re, $q)) {
                return [
                    [
                        'provider' => [
                            'type' => 'NT',
                        ],
                        'source' => $q,
                        'target' => $q,
                        'score' => 100,
                    ],
                ];
            }
        }
        return [];
    }
}
