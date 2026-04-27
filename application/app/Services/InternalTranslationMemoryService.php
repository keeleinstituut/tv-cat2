<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\Dto\GetSuggestionsOptions;
use App\Models\TranslationMemorySegment;
use App\Models\TranslationMemory;


class InternalTranslationMemoryService
{
    public static function getSuggestions(GetSuggestionsOptions $options)
    {
        // $minimumDistance = 0.2;

        // $translationMemoryResult = TranslationMemorySegment::getModel()
        //     ->where('source', $q)
        //     ->get()
        //     ->map(function ($tmSegment) {
        //         return [
        //             'provider' => [
        //                 'type' => 'TM',
        //             ],
        //             'source' => $tmSegment->source,
        //             'target' => $tmSegment->target,
        //             'score' => 5
        //         ];
        //     })
        //     ->toArray();

        // $translationMemoryResult = TranslationMemorySegment::getModel()
        //     ->where('source', $q)
        //     ->get()
        //     ->map(function ($tmSegment) {
        //         return [
        //             'provider' => [
        //                 'type' => 'TM',
        //             ],
        //             'source' => $tmSegment->source,
        //             'target' => $tmSegment->target,
        //             'score' => 5
        //         ];
        //     })
        //     ->toArray();


        // round(levenshtein(source, ?)::numeric / length(source), 4)::decimal as scorel

        // $query = Segment::getModel()
        //   ->whereRaw("source <-> ? < ?", ["'$q'", $minimumDistance])
        //   ->selectRaw("
        //     DISTINCT ON (source, target)
        //     source,
        //     target,
        //     source <-> ? as score
        //   ", ["'$q'"])
        //   ->orderBy('score', 'asc')
        //   ->get();

        // $table = TranslationMemorySegment::getModel()->getTable();

        // $query = DB::select("
        //   SELECT
        //     DISTINCT ON (source, target)
        //     *
        //   FROM (
        //     SELECT
        //       source,
        //       target,
        //       source <-> ? as score
        //     FROM $table
        //     WHERE source <-> ? < ?
        //     ORDER BY score desc
        //   )
        // ", [
        //   "'$q'",
        //   "'$q'",
        //   $minimumDistance,
        // ]);

        $minSimilarity = 0.2;
        $queryStringLength = strlen($options->q);
        $minlen = self::minLevenshteinLength($queryStringLength, $minSimilarity);
        $maxlen = self::maxLevenshteinLength($queryStringLength, $minSimilarity);

        $table = TranslationMemorySegment::getModel()->getTable();

        $sql = "
            SELECT
                DISTINCT ON (source, target, source_context_before, source_context_after)
                *
            FROM
                $table,
                phraseto_tsquery('simple', ?) query,
                similarity(source, ?) score,
                length(source) source_length
            WHERE
                source_length BETWEEN ? AND ?
                AND source_tsvector @@ query
        ";

        $sqlParams = [
            $options->q,
            $options->q,
            $minlen,
            $maxlen,
        ];

        if ($options->translationMemoryIds != null) {
            $sql .= " AND translation_memory_id IN (";
            $sql .= collect($options->translationMemoryIds)->map(fn() => '?')->join(', ');
            array_push($sqlParams, ...$options->translationMemoryIds);
            $sql .= ")";
        }

        $results = DB::select($sql, $sqlParams);


        $translationMemories = TranslationMemory::getModel()
            ->whereIn('id', collect($results)->pluck('translation_memory_id'))
            ->get()
            ->reduce(function ($carry, $item) {
                return $carry->put($item->id, $item);
            }, collect());

        $translationMemoryResult = collect($results)
            ->map(function ($tmSegment) use ($options, $translationMemories) {
                $score = round($tmSegment->score * 100, 2);

                // $shouldCheckContext = isset($options->contextBefore) && isset($options->contextAfter);
                $shouldCheckContext = true;

                if ($shouldCheckContext) {
                    $matchesBefore = $tmSegment->source_context_before == $options->contextBefore;
                    $matchesAfter = $tmSegment->source_context_after == $options->contextAfter;

                    if ($matchesBefore && $matchesAfter) {
                        $score += 1;
                    }
                }

                return [
                    'provider' => [
                        'type' => 'TM',
                        'name' => $translationMemories[$tmSegment->translation_memory_id]->name,
                        'translation_memory_id' => $tmSegment->translation_memory_id,
                    ],
                    'source' => $tmSegment->source,
                    'target' => $tmSegment->target,
                    // 'score' => (1 - $tmSegment->score) * 100,
                    'score' => $score,
                    'raw_score' => $tmSegment->score,
                    'updated_at' => $tmSegment->updated_at,
                    'meta' => [
                        'source_context_before' => $tmSegment->source_context_before,
                        'source_context_after' => $tmSegment->source_context_after,
                        'target_context_before' => $tmSegment->target_context_before,
                        'target_context_after' => $tmSegment->target_context_after,
                    ]
                ];
            })
            ->sortBy([
                ['score', 'desc'],
                ['updated_at', 'desc'],
            ])
            ->toArray();

        if ($options->limit !== null) {
            $translationMemoryResult = array_slice($translationMemoryResult, 0, $options->limit);
        }

        return $translationMemoryResult;
    }

    public static function getSegmentCount($translationMemoryId)
    {
        return TranslationMemorySegment::getModel()
            ->where('translation_memory_id', $translationMemoryId)
            ->count();
    }

    public static function importSegments($translationMemoryId, $file)
    {
        $xml_string = file_get_contents($file);
        $xml = simplexml_load_string($xml_string);

        $translationUnits = collect([]);

        foreach ($xml->body->tu as $TU) {
            $sourceUnit = $TU->tuv[0];
            $targetUnit = $TU->tuv[1];

            $sourceSegment = (string) $sourceUnit->seg[0];
            $targetSegment = (string) $targetUnit->seg[0];

            $sourceMeta = [];
            $targetMeta = [];

            foreach ($sourceUnit->prop as $prop) {
                $sourceMeta[(string) $prop->attributes()['type']] = (string) $prop;
            }

            foreach ($targetUnit->prop as $prop) {
                $targetMeta[(string) $prop->attributes()['type']] = (string) $prop;
            }

            $translationUnit = [
                'source' => [
                    'segment' => $sourceSegment,
                    'meta' => $sourceMeta
                ],
                'target' => [
                    'segment' => $targetSegment,
                    'meta' => $targetMeta
                ],
            ];
            $translationUnits->push($translationUnit);
        }

        $translationUnits
            ->map(function ($translationUnit) use ($translationMemoryId) {

                $get = function ($keys, $default = null) use ($translationUnit) {
                    return collect($keys)
                        ->map(function ($key) use ($translationUnit, $default) {
                            return data_get($translationUnit, $key, $default);
                        })
                        ->filter()
                        ->first();
                };

                return [
                    'id' => Str::uuid()->toString(),
                    'translation_memory_id' => $translationMemoryId,
                    'source' => $get( 'source.segment'),
                    'source_context_before' => $get( ['source.meta.context_before', 'source.meta.context_prev']),
                    'source_context_after' => $get( ['source.meta.context_after', 'source.meta.context_next']),

                    'target' => $get( 'target.segment'),
                    'target_context_before' => $get( ['target.meta.context_before', 'target.meta.context_prev']),
                    'target_context_after' => $get( ['target.meta.context_after', 'target.meta.context_next']),
                ];
            })
            ->filter(function ($unit) {
                return !empty($unit['source']) &&
                    !empty($unit['target']);
            })
            ->chunk(5000)
            ->each(function ($chunk) {
                TranslationMemorySegment::insert($chunk->toArray());
            });
    }


    /**
     * Write a single translation memory to a TMX file at $outputPath.
     * Uses XMLWriter to stream directly to disk — safe for large TMs.
     */
    public static function exportToTmx(TranslationMemory $tm, string $outputPath): void
    {
        $xml = new \XMLWriter();
        $xml->openUri($outputPath);
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('tmx');
        $xml->writeAttribute('version', '1.4');

        $xml->startElement('header');
        $xml->writeAttribute('creationtool', 'Catto');
        $xml->writeAttribute('datatype', 'plaintext');
        $xml->writeAttribute('segtype', 'sentence');
        $xml->writeAttribute('adminlang', 'en');
        $xml->writeAttribute('srclang', $tm->source_locale);
        $xml->endElement();

        $xml->startElement('body');

        TranslationMemorySegment::getModel()
            ->where('translation_memory_id', $tm->id)
            ->lazyById()
            ->each(function ($segment) use ($xml, $tm) {
                self::writeTu($xml, $segment, $tm->source_locale, $tm->target_locale);
            });

        $xml->endElement(); // body
        $xml->endElement(); // tmx
        $xml->endDocument();
        $xml->flush();
    }

    /**
     * Write multiple translation memories into a single combined TMX file at $outputPath.
     * Uses srclang="*all*" in the header when locales differ across TMs.
     *
     * @param \Illuminate\Support\Collection<TranslationMemory> $tms
     */
    public static function exportCombinedToTmx(\Illuminate\Support\Collection $tms, string $outputPath): void
    {
        $sourceLocales = $tms->pluck('source_locale')->unique();
        $srclang = $sourceLocales->count() === 1 ? $sourceLocales->first() : '*all*';

        $xml = new \XMLWriter();
        $xml->openUri($outputPath);
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('tmx');
        $xml->writeAttribute('version', '1.4');

        $xml->startElement('header');
        $xml->writeAttribute('creationtool', 'Catto');
        $xml->writeAttribute('datatype', 'plaintext');
        $xml->writeAttribute('segtype', 'sentence');
        $xml->writeAttribute('adminlang', 'en');
        $xml->writeAttribute('srclang', $srclang);
        $xml->endElement();

        $xml->startElement('body');

        foreach ($tms as $tm) {
            TranslationMemorySegment::getModel()
                ->where('translation_memory_id', $tm->id)
                ->lazyById()
                ->each(function ($segment) use ($xml, $tm) {
                    self::writeTu($xml, $segment, $tm->source_locale, $tm->target_locale);
                });
        }

        $xml->endElement(); // body
        $xml->endElement(); // tmx
        $xml->endDocument();
        $xml->flush();
    }

    private static function writeTu(\XMLWriter $xml, $segment, string $sourceLang, string $targetLang): void
    {
        $xml->startElement('tu');

        $xml->startElement('tuv');
        $xml->writeAttribute('xml:lang', $sourceLang);
        if ($segment->source_context_before) {
            $xml->startElement('prop');
            $xml->writeAttribute('type', 'context_before');
            $xml->text($segment->source_context_before);
            $xml->endElement();
        }
        if ($segment->source_context_after) {
            $xml->startElement('prop');
            $xml->writeAttribute('type', 'context_after');
            $xml->text($segment->source_context_after);
            $xml->endElement();
        }
        $xml->writeElement('seg', $segment->source);
        $xml->endElement(); // tuv

        $xml->startElement('tuv');
        $xml->writeAttribute('xml:lang', $targetLang);
        if ($segment->target_context_before) {
            $xml->startElement('prop');
            $xml->writeAttribute('type', 'context_before');
            $xml->text($segment->target_context_before);
            $xml->endElement();
        }
        if ($segment->target_context_after) {
            $xml->startElement('prop');
            $xml->writeAttribute('type', 'context_after');
            $xml->text($segment->target_context_after);
            $xml->endElement();
        }
        $xml->writeElement('seg', $segment->target);
        $xml->endElement(); // tuv

        $xml->endElement(); // tu
    }

    private static function minLevenshteinLength($length, $min_similarity = 0.7, $min_length = 1)
    {
        return intval(ceil(max($length * $min_similarity, 1)));
    }

    private static function maxLevenshteinLength($length, $min_similarity = 0.7, $max_length = 2000)
    {
        return intval(floor(min($length / $min_similarity, $max_length)));
    }
}
