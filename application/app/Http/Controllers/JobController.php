<?php

namespace App\Http\Controllers;

use App\Http\Requests\JobIndexRequest;
use App\Http\Requests\JobPretranslateRequest;
use App\Http\Requests\JobStoreRequest;
use App\Http\Resources\JobResource;
use App\Jobs\XliffToSegmentsJob;
use App\Jobs\OriginalToXliffJob;
use App\Jobs\DetectRepetitionsJob;
use App\Jobs\PretranslateJob;
use App\Models\Analysis;
use App\Models\Job;
use App\Models\JobAnalysis;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;

class JobController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(JobIndexRequest $request)
    {
        $params = collect($request->validated());

        $projectId = $params->get('project_id');

        if ($projectId) {
            $this->authorize('view', Project::findOrFail($projectId));
        } else {
            $this->authorize('viewAny', Job::class);
        }

        $query = $this->getBaseQuery();

        if ($projectId) {
            $query = $query->where('project_id', $projectId);
        }

        $data = $query
            ->with(
                'project',
                'sourceFileCollection',
                'xliffFileCollection'
            )
            ->paginate();

        $additionalData = [
            'translate_urls' => $data
                ->reduce(function ($acc, $v) {
                    $acc[$v->id] = env('FRONTEND_URL') . '/jobs/' . $v->id . '/translate';
                    return $acc;
                }, []),
        ];
        return JobResource::collection($data)
            ->additional($additionalData);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(JobStoreRequest $request)
    {
        $params = collect($request->validated());

        return DB::transaction(function () use ($params) {
            $obj = new Job();
            tap($params->only([
                'project_id',
                'target_locale',
            ])->filter()->toArray(), $obj->fill(...));

            $this->authorize('create', $obj);

            $obj->save();

            if ($params->has('source_file_url')) {
                $obj->addMediaFromUrl($params->get('source_file_url'))
                    ->usingFileName($params->get('source_file_name'))
                    ->toMediaCollection(Job::SOURCE_FILE_COLLECTION);
            } else {
                collect($params->get('source_files'))
                    ->each(function ($file) use ($obj) {
                        $obj->addMedia($file)->toMediaCollection(Job::SOURCE_FILE_COLLECTION);
                    });
            }

            Bus::chain([
                new OriginalToXliffJob($obj->sourceFileCollection->first(), $obj->project->source_locale, $obj->target_locale),
                new XliffToSegmentsJob($obj),
                new DetectRepetitionsJob($obj),
                // new PretranslateJob($obj),
            ])->dispatch();

            $obj->load('sourceFileCollection', 'xliffFileCollection');

            return JobResource::make($obj);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $query = $this->getBaseQuery();
        $obj = $query->findOrFail($id);

        $this->authorize('view', $obj);

        $obj->load('project', 'xliffFileCollection');

        return JobResource::make($obj);
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

            $analysisIds = JobAnalysis::where('job_id', $obj->id)->pluck('analysis_id')->unique();
            JobAnalysis::where('job_id', $obj->id)->delete();
            Analysis::whereIn('id', $analysisIds)->whereDoesntHave('jobAnalyses')->delete();

            $obj->segments()->delete();
            $obj->delete();
            return JobResource::make($obj);
        });
    }

    public function pretranslate(JobPretranslateRequest $request)
    {
        $jobs = $this->getBaseQuery()->whereIn('id', $request->validated()['job_ids'])->get();
        $jobs->each(fn($job) => $this->authorize('update', $job));
        $jobs->each(fn($job) => PretranslateJob::dispatch($job));
        return response()->noContent();
    }

    private function getBaseQuery() {
        return Job::getModel();
    }
}
