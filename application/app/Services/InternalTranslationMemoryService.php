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

                if (isset($options->contextBefore) && isset($options->contextAfter)) {
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
                    'meta' => [
                        'source_context_before' => $tmSegment->source_context_before,
                        'source_context_after' => $tmSegment->source_context_after,
                        'target_context_before' => $tmSegment->target_context_before,
                        'target_context_after' => $tmSegment->target_context_after,
                    ]
                ];
            })
            ->sortByDesc('score')
            ->toArray();

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
            ->chunk(10000)
            ->each(function ($chunk) {
                TranslationMemorySegment::insert($chunk->toArray());
            });
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
