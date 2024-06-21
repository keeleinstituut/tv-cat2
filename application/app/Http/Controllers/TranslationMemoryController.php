<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\TranslationMemory;
use App\Http\Resources\TranslationMemoryResource;
use App\Http\Requests\TranslationMemoryIndexRequest;
use App\Http\Requests\TranslationMemoryStoreRequest;
use App\Http\Requests\TranslationMemoryImportRequest;
use App\Services\InternalTranslationMemoryService;

class TranslationMemoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(TranslationMemoryIndexRequest $request)
    {
        $params = collect($request->validated());

        // dump($params);

        $query = $this->getBaseQuery();

        // if ($param = $params->get('project_id')) {
        //     $query = $query->whereHas('job', function ($q) use ($param) {
        //         $q->where('project_id', $param);
        //     });
        // }

        if ($sourceLocale = $params->get('source_locale')) {
            $query = $query
                ->where('source_locale', $sourceLocale);
        }

        if ($targetLocale = $params->get('target_locale')) {
            $query = $query
                ->where('target_locale', $targetLocale);
        }

        if ($meta = $params->get('meta')) {
            $query = collect($meta)
                ->reduce(function ($queryBuilder, $v, $k) {
                    return $queryBuilder
                        ->where('meta->' . $k, $v);
                }, $query);
        }

        $data = $query->get();
        // $data = $query->paginate();


        return TranslationMemoryResource::collection($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TranslationMemoryStoreRequest $request)
    {
        $params = collect($request->validated());

        return DB::transaction(function () use ($params) {
            $obj = new TranslationMemory();
            tap($params->only([
                'name',
                'source_locale',
                'target_locale',
                'meta',
            ])->filter()->toArray(), $obj->fill(...));
            $obj->save();

            return TranslationMemoryResource::make($obj);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $query = $this->getBaseQuery();
        $obj = $query->findOrFail($id);

        return TranslationMemoryResource::make($obj)
            ->additional([
                'segment_count' => InternalTranslationMemoryService::getSegmentCount($obj->id),
            ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // $params = collect($request->validated());
        $params = collect($request->all());

        return DB::transaction(function () use ($params, $id) {

            $query = $this->getBaseQuery();
            $obj = $query->findOrFail($id);

            // $obj = new TranslationMemory();
            tap($params->only([
                'name',
                'source_locale',
                'target_locale',
            ])->filter()->toArray(), $obj->fill(...));

            if ($paramsMeta = $params->get('meta')) {
                $obj->meta = collect($paramsMeta)
                    ->reduce(function ($acc, $v, $k) {
                        data_set($acc, $k, $v);
                        return $acc;
                    }, $obj->meta);
            }


            $obj->save();

            return TranslationMemoryResource::make($obj);

            // return [
            //     'data' => TranslationMemoryResource::make($obj),
            //     'params' => $params,
            // ];
        });
    }

    // /**
    //  * Remove the specified resource from storage.
    //  */
    // public function destroy(string $id)
    // {
    //     //
    // }

    public function import(TranslationMemoryImportRequest $request)
    {
        $params = collect($request->validated());

        $query = $this->getBaseQuery();
        $obj = $query->find($params->get('translation_memory_id'));

        collect($params->get('files'))
            ->each(function ($file) use ($obj) {
                InternalTranslationMemoryService::importSegments($obj->id, $file);
            });

        return [
            'message' => 'OK',
        ];
    }

    private function getBaseQuery() {
        return TranslationMemory::getModel();
    }
}
