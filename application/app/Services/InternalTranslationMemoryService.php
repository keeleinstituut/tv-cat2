<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\Dto\GetSuggestionsOptions;
use App\Models\TranslationMemorySegment;
use App\Models\TranslationMemory;
use Log;


class InternalTranslationMemoryService
{
    public static function getSuggestions(GetSuggestionsOptions $options)
    {
        $batch = self::getSuggestionsBatch($options);
        return $batch[0] ?? [];
    }

    /**
     * Fetch TM suggestions for multiple source strings in a single SQL round-trip.
     * Each query in $options->queries carries its own contextBefore/contextAfter.
     *
     * @return array<int, array>  Indexed by query position (same order as $options->queries).
     */
    public static function getSuggestionsBatch(GetSuggestionsOptions $options): array
    {
        if (empty($options->queries)) {
            return [];
        }

        $minSimilarity = 0.2;
        $table = TranslationMemorySegment::getModel()->getTable();

        $seen = [];
        $uniqueForSql = [];
        foreach ($options->queries as $query) {
            if (!isset($seen[$query->q])) {
                $seen[$query->q] = true;
                $uniqueForSql[] = $query;
            }
        }

        $valueRows = [];
        $sqlParams = [];
        foreach ($uniqueForSql as $query) {
            $len = strlen($query->q);
            $valueRows[] = '(?::text, ?::int, ?::int)';
            $sqlParams[] = $query->q;
            $sqlParams[] = self::minLevenshteinLength($len, $minSimilarity);
            $sqlParams[] = self::maxLevenshteinLength($len, $minSimilarity);
        }

        $valuesSql = implode(', ', $valueRows);

        $tmFilter = '';
        if ($options->translationMemoryIds !== null) {
            $placeholders = collect($options->translationMemoryIds)->map(fn() => '?')->join(', ');
            $tmFilter = "AND translation_memory_id IN ($placeholders)";
            array_push($sqlParams, ...$options->translationMemoryIds);
        }

        $lateralLimit = $options->limit !== null ? 'LIMIT ' . (int) $options->limit : '';

        $sql = "
            WITH queries(q, minlen, maxlen) AS (
                VALUES $valuesSql
            )
            SELECT
                queries.q AS queried_source,
                lateral_result.*
            FROM queries,
            LATERAL (
                SELECT *
                FROM (
                    SELECT DISTINCT ON (source, target, source_context_before, source_context_after)
                        *,
                        similarity(source, queries.q) AS score,
                        length(source) AS source_length
                    FROM $table
                    WHERE length(source) BETWEEN queries.minlen AND queries.maxlen
                      AND source_tsvector @@ phraseto_tsquery('simple', queries.q)
                      $tmFilter
                    ORDER BY source, target, source_context_before, source_context_after
                ) deduped
                ORDER BY score DESC
                $lateralLimit
            ) AS lateral_result
        ";

        $results = DB::select($sql, $sqlParams);

        $translationMemories = TranslationMemory::getModel()
            ->whereIn('id', collect($results)->pluck('translation_memory_id')->unique())
            ->get()
            ->keyBy('id');

        $rawBySource = [];
        foreach ($results as $row) {
            $rawBySource[$row->queried_source][] = $row;
        }

        $grouped = [];
        foreach ($options->queries as $i => $query) {
            $suggestions = [];
            foreach ($rawBySource[$query->q] ?? [] as $tmSegment) {
                $score = round($tmSegment->score * 100, 2);
                if ($tmSegment->source_context_before == $query->contextBefore
                    && $tmSegment->source_context_after == $query->contextAfter) {
                    $score += 1;
                }
                $suggestions[] = [
                    'provider' => [
                        'type' => 'TM',
                        'name' => $translationMemories[$tmSegment->translation_memory_id]->name,
                        'translation_memory_id' => $tmSegment->translation_memory_id,
                    ],
                    'source'     => $tmSegment->source,
                    'target'     => $tmSegment->target,
                    'score'      => $score,
                    'raw_score'  => $tmSegment->score,
                    'updated_at' => $tmSegment->updated_at,
                    'meta' => [
                        'source_context_before' => $tmSegment->source_context_before,
                        'source_context_after'  => $tmSegment->source_context_after,
                        'target_context_before' => $tmSegment->target_context_before,
                        'target_context_after'  => $tmSegment->target_context_after,
                    ],
                ];
            }

            $suggestions = collect($suggestions)
                ->sortBy([['score', 'desc'], ['updated_at', 'desc']])
                ->values()
                ->toArray();

            if ($options->limit !== null) {
                $suggestions = array_slice($suggestions, 0, $options->limit);
            }

            $grouped[$i] = $suggestions;
        }

        return $grouped;
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
