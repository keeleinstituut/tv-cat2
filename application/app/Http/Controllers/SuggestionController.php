<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\SuggestionResource;
use App\Http\Requests\SuggestionIndexRequest;
use App\Http\Requests\SuggestionIndexJobRequest;
use App\Services\SuggestionService;
use App\Services\Dto\GetSuggestionsOptions;
use App\Models\Job;

class SuggestionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(SuggestionIndexRequest $request)
    {
        $params = collect($request->validated());

        $options = GetSuggestionsOptions::make()
            ->setQ($params->get('q'))
            ->setSourceLocale($params->get('source_locale'))
            ->setTargetLocale($params->get('target_locale'))
            ->setProviders($params->get('providers'))
            // ->setTranslationMemoryIds($params->get('translation_memory_ids'))
            ->setContextBefore($params->get('context_before'))
            ->setContextAfter($params->get('context_after'))
            ->setLimit($params->get('limit'));

        $data = SuggestionService::getSuggestions($options);

        return SuggestionResource::collection($data);
    }

    /**
     * Display a listing of the resource.
     */
    public function indexJob(string $jobId, SuggestionIndexJobRequest $request)
    {
        $params = collect($request->validated());

        $job = Job::getModel()->findOrFail($jobId);

        $options = GetSuggestionsOptions::make()
            ->setQ($params->get('q'))
            ->setSourceLocale($job->project->source_locale)
            ->setTargetLocale($job->target_locale)
            ->setProviders($params->get('providers'))
            ->setContextBefore($params->get('context_before'))
            ->setContextAfter($params->get('context_after'))
            ->setLimit($params->get('limit'));

        // dump($options);

        $data = SuggestionService::getSuggestions($options);

        return SuggestionResource::collection($data);
    }

    // /**
    //  * Store a newly created resource in storage.
    //  */
    // public function store(Request $request)
    // {
    //     //
    // }

    // /**
    //  * Display the specified resource.
    //  */
    // public function show(string $id)
    // {
    //     //
    // }

    // /**
    //  * Update the specified resource in storage.
    //  */
    // public function update(Request $request, string $id)
    // {
    //     //
    // }

    // /**
    //  * Remove the specified resource from storage.
    //  */
    // public function destroy(string $id)
    // {
    //     //
    // }
}
