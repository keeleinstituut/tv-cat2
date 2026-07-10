<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectIndexRequest;
use App\Http\Requests\ProjectStoreRequest;
use App\Http\Requests\ProjectUpdateRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\TranslationMemory;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ProjectIndexRequest $request)
    {
        $params = collect($request->validated());

        $this->authorize('viewAny', Project::class);

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
                'source_locale',
                'tenant_id',
            ])->filter()->toArray(), $obj->fill(...));

            $this->authorize('create', $obj);

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
        $obj = $query->with('translationMemories')->findOrFail($id);

        $this->authorize('view', $obj);

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
           $obj = $query->findOrFail($id);

           $this->authorize('update', $obj);

           $obj->fill($params->only(['name', 'source_locale'])->filter()->toArray());
           $obj->save();

           if ($params->has('translation_memories')) {
               $syncData = collect($params->get('translation_memories'))
                   ->keyBy('id')
                   ->map(fn($tm) => ['read' => $tm['read'], 'write' => $tm['write']])
                   ->toArray();

               $translationMemories = TranslationMemory::getModel()->whereIn('id', array_keys($syncData))->get();
               foreach ($translationMemories as $tm) {
                   $this->authorize('view', $tm);
                   if ($syncData[$tm->id]['write']) {
                       $this->authorize('update', $tm);
                   }
               }

               $obj->translationMemories()->sync($syncData);
           }

           $obj->load('translationMemories');
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
            $obj = $query->findOrFail($id);

            $this->authorize('delete', $obj);

            $obj->delete();

            return ProjectResource::make($obj);
        });
    }

    private function getBaseQuery() {
        return Project::getModel();
    }
}
