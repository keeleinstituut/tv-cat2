<?php

namespace App\Http\Controllers;

use App\Models\TranslationMemorySegment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Models\TranslationMemory;
use App\Http\Resources\TranslationMemoryResource;
use App\Http\Requests\TranslationMemoryIndexRequest;
use App\Http\Requests\TranslationMemoryStoreRequest;
use App\Http\Requests\TranslationMemoryImportRequest;
use App\Http\Requests\TranslationMemoryExportRequest;
use App\Services\InternalTranslationMemoryService;

class TranslationMemoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(TranslationMemoryIndexRequest $request)
    {
        $params = collect($request->validated());

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

        if ($filter = $params->get('filter')) {
            $query = $this->applyFilter($query, $filter);
        }

        $data = $query->get();
        $additionalData = [];
        // $data = $query->paginate();

        if ($params->get('with_segment_count', false)) {
            $additionalData['segment_counts'] = TranslationMemorySegment::getModel()
                ->whereIn('translation_memory_id', $data->pluck('id'))
                ->groupBy('translation_memory_id')
                ->select('translation_memory_id', DB::raw('count(*) as count'))
                ->get();
        }


        return TranslationMemoryResource::collection($data)
            ->additional($additionalData);
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

    public function export(TranslationMemoryExportRequest $request)
    {
        $params   = collect($request->validated());
        $combined = (bool) $params->get('combined', false);
        $ids      = $params->get('translation_memory_ids');

        $tms = TranslationMemory::whereIn('id', $ids)->get();

        $zipPath   = tempnam(sys_get_temp_dir(), 'tm_export_') . '.zip';
        $zip       = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $tmxTempFiles = [];

        if ($combined) {
            $tmxPath        = tempnam(sys_get_temp_dir(), 'tm_export_');
            $tmxTempFiles[] = $tmxPath;
            InternalTranslationMemoryService::exportCombinedToTmx($tms, $tmxPath);
            $zip->addFile($tmxPath, 'export.tmx');
        } else {
            $usedNames = [];

            foreach ($tms as $tm) {
                $base = Str::slug($tm->name) ?: $tm->id;
                $name = $base;
                $i    = 1;
                while (\in_array($name, $usedNames)) {
                    $name = "{$base}_{$i}";
                    $i++;
                }
                $usedNames[] = $name;

                $tmxPath        = tempnam(sys_get_temp_dir(), 'tm_export_');
                $tmxTempFiles[] = $tmxPath;
                InternalTranslationMemoryService::exportToTmx($tm, $tmxPath);
                $zip->addFile($tmxPath, "{$name}.tmx");
            }
        }

        $zip->close();

        // ZipArchive has compressed the TMX files into the ZIP; temp files can be removed.
        foreach ($tmxTempFiles as $f) {
            @unlink($f);
        }

        return response()
            ->download($zipPath, 'translation-memories.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

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

    private function applyFilter($query, array $filter)
    {
        foreach ($filter as $key => $value) {
            if ($key === 'or') {
                $query = $query->where(function ($q) use ($value) {
                    foreach ($value as $i => $group) {
                        $method = $i === 0 ? 'where' : 'orWhere';
                        $q = $q->$method(function ($sub) use ($group) {
                            return $this->applyFilter($sub, $group);
                        });
                    }
                });
            } elseif ($key === 'and') {
                // $query = $query->where(function ($q) use ($value) {
                //     return $this->applyFilter($q, $value);
                // });
                $query = $query->where(function ($q) use ($value) {
                    foreach ($value as $group) {
                        $q = $q->where(function ($sub) use ($group) {
                            $this->applyFilter($sub, $group);
                        });
                    }
                });
            } else {
                $column = str_starts_with($key, 'meta.')
                    ? 'meta->' . substr($key, 5)
                    : $key;
                $query = $query->where($column, $value);
            }
        }
        return $query;
    }
}
