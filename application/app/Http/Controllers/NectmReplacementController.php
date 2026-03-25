<?php

namespace App\Http\Controllers;

use App\Models\TranslationMemory;
use App\Models\TranslationMemorySegment;
use App\Services\Dto\GetSuggestionsOptions;
use App\Services\SuggestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class NectmReplacementController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function index(Request $request)
    {
        $params = collect($request->validate([
            'q' => 'string',
            'slang' => 'string',
            'tlang' => 'string',
            // 'limit' => 'number',
            // 'aut_trans' => 'string',
            // 'concordance' => 'boolean',
            // 'min_match' => 'number',
            'smeta' => 'string',
            'tag' => 'array',
            'tag.*' => 'string',
        ]));

        $smeta = json_decode($params->get('smeta', true));

        $options = GetSuggestionsOptions::make()
            ->setQ($params->get('q'))
            ->setSourceLocale($params->get('slang'))
            ->setTargetLocale($params->get('tlang'))
            ->setProviders(['tm'])
            ->setContextBefore(data_get($smeta, 'context_before'))
            ->setContextAfter(data_get($smeta, 'context_after'));

        if ($paramsTag = $params->get('tag')) {
            $options->setTranslationMemoryIds($paramsTag);
        }

        $data = SuggestionService::getSuggestions($options);

        $translationMemories = TranslationMemory::getModel()
            ->whereIn('id', collect($data)->pluck('provider.translation_memory_id')->unique())
            ->get();

        return [
            'query' => $options->q,
            'results' => collect($data)->map(function ($suggestion) {
                return [
                    'tu' => [
                        "_id" => null,
                        "domain" => "['" . $suggestion['provider']['translation_memory_id'] . "']",
                        "source_text" => $suggestion['source'],
                        "target_text" => $suggestion['target'] . 'asd',
                        "source_metadata" => [
                            "context_before" => $suggestion['meta']['source_context_before'],
                            "context_after" => $suggestion['meta']['source_context_after'],
                        ],
                        "target_metadata" => null
                    ],
                    'match' => $suggestion["score"],
                    'mt' => false,
                    // "update_date" => "20251124T080807Z", // TODO: output correct data
                    "update_date" => $suggestion['updated_at'],
                    "username" => "20b4d4e4-bcc2-4ac7-a66b-11dd80f40613",
                    "file_name" => "['tv-test1.tmx']",
                    "tag" => [
                        $suggestion['provider']['translation_memory_id'],
                    ]
                ];
            }),
            'tags' => collect($translationMemories)->map(function ($translationMemory) {
                return[
                    'id' => $translationMemory->id,
                    'name' => $translationMemory->name,
                    'type' => 'private',
                ];
            })
        ];
    }

    public function store(Request $request) {
        $params = Validator::make($request->all(), [
            'slang' => 'string',
            'tlang' => 'string',
            'stext' => 'string',
            'ttext' => 'string',
            'smeta' => 'string',
            'tag' => 'array',
            'tag.*' => 'string',
        ])->validated();

        $smeta = [];
        if ($smetaJson = json_decode(data_get($params, 'smeta', ""), true)) {
            $smeta = Validator::make($smetaJson, [
                'context_before' => 'string|nullable',
                'context_after' => 'string|nullable',
            ])->validated();
        }

        $tmeta = [];
        if ($tmetaJson = json_decode(data_get($params, 'tmeta', ""), true)) {
            $tmeta = Validator::make($tmetaJson, [
                'context_before' => 'string|nullable',
                'context_after' => 'string|nullable',
            ])->validated();
        }

        collect($params['tag'])
            ->each(function ($tagId) use ($params, $smeta, $tmeta) {
                $obj = TranslationMemorySegment::make([
                    'translation_memory_id' => $tagId,
                    'source' => data_get($params, 'stext'),
                    'source_context_before' => data_get($smeta, 'context_before'),
                    'source_context_after' => data_get($smeta, 'context_after'),
                    'target' => data_get($params, 'ttext'),
                    'target_context_before' => data_get($tmeta, 'context_before'),
                    'target_context_after' => data_get($tmeta, 'context_after'),
                ]);
                $obj->save();
            });
    }
}
