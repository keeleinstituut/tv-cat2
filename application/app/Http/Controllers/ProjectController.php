<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectIndexRequest;
use App\Http\Requests\ProjectStoreRequest;
use App\Http\Requests\ProjectUpdateRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ProjectIndexRequest $request)
    {
        $params = collect($request->validated());
        $query = $this->getBaseQuery();

        $data = $query->paginate();
        return ProjectResource::collection($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProjectStoreRequest $request)
    {
        $params = collect($request->validated());

        return DB::transaction(function () use ($params) {
            $obj = new Project();
            tap($params->only([
                'name',
                'source_locale'
            ])->filter()->toArray(), $obj->fill(...));
            $obj->save();

            return ProjectResource::make($obj);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $query = $this->getBaseQuery();
        $obj = $query->find($id);

        return ProjectResource::make($obj);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProjectUpdateRequest $request)
    {
        $id = $request->route('id');
        $params = collect($request->validated());

        return DB::transaction(function () use ($id, $params) {
           $query = $this->getBaseQuery();
           $obj = $query->find($id);

           $obj->fill($params->toArray());
           $obj->save();

           return ProjectResource::make($obj);
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $query = $this->getBaseQuery();
            $obj = $query->find($id);

            $obj->destroy();

            return ProjectResource::make($obj);
        });
    }

    private function getBaseQuery() {
        return Project::getModel();
    }
}
