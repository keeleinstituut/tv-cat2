<?php

namespace App\Http\Controllers;

use App\Http\Requests\JobIndexRequest;
use App\Http\Requests\JobStoreRequest;
use App\Http\Resources\JobResource;
use App\Jobs\XliffToSegmentsJob;
use App\Jobs\OriginalToXliffJob;
use App\Jobs\DetectRepetitionsJob;
use App\Jobs\PretranslateJob;
use App\Models\Job;
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
        $query = $this->getBaseQuery();

        if ($param = $params->get('project_id')) {
            $query = $query->where('project_id', $param);
        }

        $data = $query
            ->with(
                'project',
                'sourceFileCollection',
                'xliffFileCollection'
            )
            ->paginate();
        return JobResource::collection($data);
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
            $obj->save();

            collect($params->get('source_files'))
                ->each(function ($file) use ($obj) {
                    $obj->addMedia($file)->toMediaCollection(Job::SOURCE_FILE_COLLECTION);
                });

            Bus::chain([
                new OriginalToXliffJob($obj->sourceFileCollection->first(), $obj->project->source_locale, $obj->target_locale),
                new XliffToSegmentsJob($obj),
                new DetectRepetitionsJob($obj),
                new PretranslateJob($obj),
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
        $obj = $query->find($id);

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

    // /**
    //  * Remove the specified resource from storage.
    //  */
    // public function destroy(string $id)
    // {
    //     //
    // }

    private function getBaseQuery() {
        return Job::getModel();
    }
}
