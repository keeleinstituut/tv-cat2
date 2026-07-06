<?php

namespace App\Http\Controllers;

use App\Http\Requests\TranslationMemorySegmentIndexRequest;
use App\Http\Requests\TranslationMemorySegmentReplaceRequest;
use App\Http\Requests\TranslationMemorySegmentUpdateRequest;
use App\Http\Resources\TranslationMemorySegmentResource;
use App\Models\TranslationMemory;
use App\Models\TranslationMemorySegment;

class TranslationMemorySegmentController extends Controller
{
    public function index(TranslationMemorySegmentIndexRequest $request)
    {
        $params = collect($request->validated());

        $translationMemory = TranslationMemory::find($params->get('translation_memory_id'));

        $this->authorize('viewAnySegments', $translationMemory);

        $query = TranslationMemorySegment::getModel()
            ->where('translation_memory_id', $translationMemory->id);

        if ($param = $params->get('source')) {
            $query = $query->where('source', 'ilike', "%$param%");
        }

        if ($param = $params->get('target')) {
            $query = $query->where('target', 'ilike', "%$param%");
        }

        $query = $query->orderBy('source', 'asc');

        $data = $query->paginate($params->get('per_page', 100));

        return TranslationMemorySegmentResource::collection($data);
            // ->additional([
            //     'meta' => [
            //         'segment_count' => $query->count(),
            //     ],
            // ]);
    }

    public function replace(TranslationMemorySegmentReplaceRequest $request)
    {
        $params = collect($request->validated());
        $source = $params->get('source');
        $target = $params->get('target');
        $replaceTarget = $params->get('replace_target', '');

        $translationMemory = TranslationMemory::find($params->get('translation_memory_id'));
        $this->authorize('updateAnySegments', $translationMemory);

        $query = TranslationMemorySegment::getModel()
            ->where('translation_memory_id', $translationMemory->id);

        if ($source) {
            $query = $query->where('source', 'ilike', "%$source%");
        }

        if ($target) {
            $query = $query->where('target', 'ilike', "%$target%");
        }

        $segments = $query->get();

        foreach ($segments as $segment) {
            $segment->target = str_replace($target, $replaceTarget, $segment->target);
            $segment->save();
        }

        return response()->json([
            'replaced_count' => $segments->count(),
        ]);
            // ->json(['segment_count' => $segments->count()]);
    }

    public function update(TranslationMemorySegmentUpdateRequest $request)
    {
        $id = $request->route('id');
        $params = collect($request->validated());

        $obj = TranslationMemorySegment::findOrFail($id);

        $this->authorize('updateAnySegments', $obj->translationMemory);

        $obj->fill(['target' => $params->get('target')]);
        $obj->save();

        return TranslationMemorySegmentResource::make($obj);
    }
}
