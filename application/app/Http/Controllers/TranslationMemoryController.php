<?php

namespace App\Http\Controllers;

use App\Models\TranslationMemorySegment;
use App\Policies\TranslationMemoryPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
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

        $this->authorize('viewAny', TranslationMemory::class);

        $query = $this->getBaseQuery();

        if ($name = $params->get('name')) {
            $query = $query->where('name', 'ilike', '%' . $name . '%');
        }

        if ($tenantId = $params->get('tenant_id')) {
            $query = $query->where('tenant_id', $tenantId);
        }

        $sourceLocales = array_values(array_filter($params->get('source_locale', []), fn ($v) => $v !== null && $v !== ''));
        $targetLocales = array_values(array_filter($params->get('target_locale', []), fn ($v) => $v !== null && $v !== ''));

        if (!empty($sourceLocales) && !empty($targetLocales) && count($sourceLocales) === count($targetLocales)) {
            $query = $query->where(function ($q) use ($sourceLocales, $targetLocales) {
                foreach ($sourceLocales as $i => $source) {
                    $target = $targetLocales[$i];
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $q->$method(fn ($sub) => $sub->where('source_locale', $source)->where('target_locale', $target));
                }
            });
        } else {
            if (!empty($sourceLocales)) {
                $query = $query->whereIn('source_locale', $sourceLocales);
            }
            if (!empty($targetLocales)) {
                $query = $query->whereIn('target_locale', $targetLocales);
            }
        }

        $visibilities = array_values(array_filter($params->get('visibility', []), fn ($v) => $v !== null && $v !== ''));
        if (!empty($visibilities)) {
            $query = $query->whereIn('visibility', $visibilities);
        }

        $domains = array_values(array_filter($params->get('tv_domain', []), fn ($v) => $v !== null && $v !== ''));
        if (!empty($domains)) {
            $query = $query->whereIn('meta->tv_domain', $domains);
        }

        $tags = array_values(array_filter($params->get('tv_tags', []), fn ($v) => $v !== null && $v !== ''));
        if (!empty($tags)) {
            $query = $query->where(function ($q) use ($tags) {
                foreach ($tags as $i => $tag) {
                    $method = $i === 0 ? 'whereJsonContains' : 'orWhereJsonContains';
                    $q->$method('meta->tv_tags', $tag);
                }
            });
        }

        $additionalData = [];

        if ($perPage = $params->get('per_page')) {
            $data = $query->paginate($perPage, ['*'], 'page', $params->get('page'));
        } else {
            $data = $query->get();
        }

        if ($params->get('with_segment_count', false)) {
            $additionalData['segment_counts'] = TranslationMemorySegment::getModel()
                ->whereIn('translation_memory_id', $data->pluck('id'))
                ->groupBy('translation_memory_id')
                ->select('translation_memory_id', DB::raw('count(*) as count'))
                ->get()
                ->reduce(function ($acc, $v) {
                    $acc[$v['translation_memory_id']] = $v['count'];
                    return $acc;
                }, []);
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
                'tenant_id',
                'visibility',
                'meta',
            ])->filter()->toArray(), $obj->fill(...));

            $this->authorize('create', $obj);

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

        $this->authorize('view', $obj);

        return TranslationMemoryResource::make($obj)
            ->additional([
                'segment_count' => InternalTranslationMemoryService::getSegmentCount($obj->id),
                'edit_url' => env('FRONTEND_URL') . '/translation-memories/' . $obj->id . '/edit',
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

            $this->authorize('update', $obj);

            // $obj = new TranslationMemory();
            tap($params->only([
                'name',
                'source_locale',
                'target_locale',
                'tenant_id',
                'visibility',
            ])->filter()->toArray(), $obj->fill(...));

            if ($paramsMeta = $params->get('meta')) {
                $obj->meta = collect($paramsMeta)
                    ->reduce(function ($acc, $v, $k) {
                        data_set($acc, $k, $v);
                        return $acc;
                    }, $obj->meta);
            }


            $obj->save();

            return TranslationMemoryResource::make($obj)
                ->additional([
                    'segment_count' => InternalTranslationMemoryService::getSegmentCount($obj->id),
                    'edit_url' => env('FRONTEND_URL') . '/translation-memories/' . $obj->id . '/edit',
                ]);

            // return [
            //     'data' => TranslationMemoryResource::make($obj),
            //     'params' => $params,
            // ];
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        return DB::transaction(function () use ($id) {
            $obj = $this->getBaseQuery()->findOrFail($id);

            $this->authorize('delete', $obj);

            $obj->translationMemorySegments()->delete();
            $obj->delete();
            return TranslationMemoryResource::make($obj);
        });
    }

    public function export(TranslationMemoryExportRequest $request)
    {
        $params   = collect($request->validated());
        $combined = (bool) $params->get('combined', false);
        $ids      = $params->get('translation_memory_ids');

        $tms = $this->getBaseQuery()->whereIn('id', $ids)->get();

        // Check permissions for each TranslationMemory
        $tms->each(fn ($obj) => $this->authorize('export', $obj));

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

        $this->authorize('import', $obj);

        collect($params->get('files'))
            ->each(function ($file) use ($obj) {
                InternalTranslationMemoryService::importSegments($obj->id, $file);
            });

        return [
            'message' => 'OK',
        ];
    }

    private function getBaseQuery() {
        return TranslationMemory::getModel()->withGlobalScope('policy', TranslationMemoryPolicy::scope());
    }
}
