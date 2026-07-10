<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalysisIndexRequest;
use App\Http\Requests\AnalysisStoreRequest;
use App\Http\Resources\AnalysisResource;
use App\Jobs\AnalyzeJob;
use App\Models\Analysis;
use App\Models\Job;
use App\Models\JobAnalysis;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class AnalysisController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(AnalysisIndexRequest $request)
    {
        $params = collect($request->validated());

        $projectId = $params->get('project_id');

        if ($projectId) {
            $this->authorize('view', Project::findOrFail($projectId));
        } else {
            $this->authorize('viewAny', Analysis::class);
        }

        $query = $this->getBaseQuery()
            ->with('jobAnalyses.job.project')
            ->with('jobAnalyses.job.sourceFileCollection');

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $data = $query
            ->orderBy('created_at', 'desc')
            ->paginate($params->get('per_page'));
        return AnalysisResource::collection($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AnalysisStoreRequest $request)
    {
        $jobs = collect($request->validated('job_id'))->map(fn ($id) => Job::findOrFail($id));

        if ($jobs->pluck('project_id')->unique()->count() > 1) {
            abort(422, 'All jobs must belong to the same project.');
        }

        $project = $jobs->first()->project;
        $tmIds = $project->translationMemories
            ->filter(fn ($tm) => $tm->pivot->read)
            ->pluck('id')
            ->values()
            ->all();

        $analysis = new Analysis();
        $analysis->project_id = $project->id;
        $analysis->translation_memory_ids = $tmIds;

        $this->authorize('create', $analysis);

        $analysis->save();

        $jobs->each(function ($job) use ($analysis) {
            $jobAnalysis = new JobAnalysis();
            $jobAnalysis->job_id = $job->id;
            $jobAnalysis->analysis_id = $analysis->id;
            $jobAnalysis->save();
            AnalyzeJob::dispatch($job, $jobAnalysis);
        });

        return new AnalysisResource($analysis->load('jobAnalyses.job.project'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $analysis = $this->getBaseQuery()
            ->with('jobAnalyses.job.project')
            ->with('jobAnalyses.job.sourceFileCollection')
            ->findOrFail($id);

        $this->authorize('view', $analysis);

        return new AnalysisResource($analysis);
    }

    // /**
    //  * Update the specified resource in storage.
    //  */
    // public function update(Request $request, string $id)
    // {
    //     //
    // }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        return DB::transaction(function () use ($id) {
            $obj = $this->getBaseQuery()->findOrFail($id);

            $this->authorize('delete', $obj);

            $obj->jobAnalyses()->delete();
            $obj->delete();
            return AnalysisResource::make($obj);
        });
    }

    private function getBaseQuery() {
        return Analysis::getModel();
    }
}
