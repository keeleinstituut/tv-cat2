<?php

namespace App\Services;

class EkilexLanguageMapper
{
    /** Primary language subtag (ISO 639-1) => Ekilex language code (ISO 639-3). */
    private const MAP = [
        'et' => 'est',
        'en' => 'eng',
        'ru' => 'rus',
        'fi' => 'fin',
        'de' => 'ger',
        'fr' => 'fra',
        'sv' => 'swe',
        'lv' => 'lav',
        'lt' => 'lit',
        'es' => 'spa',
        'it' => 'ita',
        'la' => 'lat',
        'pl' => 'pol',
        'uk' => 'ukr',
        'da' => 'dan',
        'no' => 'nor',
        'nb' => 'nor',
        'pt' => 'por',
        'nl' => 'dut',
        'hu' => 'hun',
        'cs' => 'cze',
        'ja' => 'jpn',
        'zh' => 'chi',
    ];

    /** Returns null for locales Ekilex has no language code for. */
    public static function toEkilex(?string $locale): ?string
    {
        if (!$locale) {
            return null;
        }

        $primary = strtolower(preg_split('/[-_]/', $locale)[0]);

        return self::MAP[$primary] ?? null;
    }
}
