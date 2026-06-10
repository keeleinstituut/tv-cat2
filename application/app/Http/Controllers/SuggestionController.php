<?php

namespace App\Http\Controllers;

use App\Http\Resources\SuggestionResource;
use App\Http\Requests\SuggestionIndexRequest;
use App\Http\Requests\SuggestionIndexJobRequest;
use App\Services\SuggestionService;
use App\Services\Dto\GetSuggestionsOptions;
use App\Models\Job;
use App\Models\Segment;

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

        $job = Job::getModel()->with('project.translationMemories')->findOrFail($jobId);

        $projectTmIds = $job->project->translationMemories
            ->filter(fn($tm) => $tm->pivot->read)
            ->pluck('id')
            ->toArray();

        if ($segmentId = $params->get('segment_id')) {
            $segment = Segment::where('id', $segmentId)
                ->where('job_id', $jobId)
                ->firstOrFail();

            $contextBefore = Segment::where('job_id', $jobId)
                ->where('position', '<', $segment->position)
                ->orderBy('position', 'desc')
                ->value('source');

            $contextAfter = Segment::where('job_id', $jobId)
                ->where('position', '>', $segment->position)
                ->orderBy('position', 'asc')
                ->value('source');
        } else {
            $segment = null;
            $contextBefore = $params->get('context_before');
            $contextAfter = $params->get('context_after');
        }

        $options = GetSuggestionsOptions::make()
            ->setQ($segment ? $segment->source : $params->get('q'))
            ->setSourceLocale($job->project->source_locale)
            ->setTargetLocale($job->target_locale)
            ->setProviders($params->get('providers'))
            ->setContextBefore($contextBefore)
            ->setContextAfter($contextAfter)
            ->setLimit($params->get('limit'));

        if (!empty($projectTmIds)) {
            $options->setTranslationMemoryIds($projectTmIds);
        }

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
