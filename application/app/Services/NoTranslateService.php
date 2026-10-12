<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use App\Services\Dto\GetSuggestionsOptions;
use App\Support\SegmentText;


class NoTranslateService
{
    private static $REGEX_LIST = [
        '/^[0-9]+$/',
        '/^\([0-9]+\)$/',
        '/^§ ?([0-9]+\.?)*$/',
    ];

    public static function getSuggestions(GetSuggestionsOptions $options) {
        $q = $options->getQ();
        // The rules look at the text a person reads; the suggestion keeps the segment's formatting.
        $plain = SegmentText::plain($q);
        foreach (static::$REGEX_LIST as $re) {
            if (preg_match($re, $plain)) {
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
