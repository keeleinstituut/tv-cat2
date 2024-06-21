<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalysisIndexRequest;
use App\Http\Requests\AnalysisStoreRequest;
use App\Http\Resources\AnalysisResource;
use App\Models\Analysis;
use Illuminate\Http\Request;

class AnalysisController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(AnalysisIndexRequest $request)
    {
        $params = collect($request->validated());
        $query = $this->getBaseQuery();

        if ($param = $params->get('project_id')) {
            $query = $query->whereHas('job', function ($q) use ($param) {
                $q->where('project_id', $param);
            });
        }

        $data = $query->paginate();
        return AnalysisResource::collection($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AnalysisStoreRequest $request)
    {
        $params = collect($request->validated());

        return collect($params->get('job_id'))->map(function ($job_id) {
            $analysis = new Analysis();
            $analysis->job_id = $job_id;
            return $analysis->save();
        });
    }

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

    private function getBaseQuery() {
        return Analysis::getModel();
    }
}
