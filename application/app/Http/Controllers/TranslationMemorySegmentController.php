<?php

namespace App\Http\Controllers;

use App\Http\Requests\TranslationMemorySegmentIndexRequest;
use App\Http\Requests\TranslationMemorySegmentUpdateRequest;
use App\Http\Resources\TranslationMemorySegmentResource;
use App\Models\TranslationMemorySegment;

class TranslationMemorySegmentController extends Controller
{
    public function index(TranslationMemorySegmentIndexRequest $request)
    {
        $params = collect($request->validated());
        $query = TranslationMemorySegment::getModel()
            ->where('translation_memory_id', $params->get('translation_memory_id'));

        if ($param = $params->get('source')) {
            $query = $query->where('source', 'ilike', "%$param%");
        }

        if ($param = $params->get('target')) {
            $query = $query->where('target', 'ilike', "%$param%");
        }

        $query = $query->orderBy('source', 'asc');

        $data = $query->paginate($params->get('per_page', 100));

        return TranslationMemorySegmentResource::collection($data)
            ->additional([
                'meta' => [
                    'count' => $query->count(),
                ],
            ]);
    }

    public function update(TranslationMemorySegmentUpdateRequest $request)
    {
        $id = $request->route('id');
        $params = collect($request->validated());

        $obj = TranslationMemorySegment::findOrFail($id);
        $obj->fill(['target' => $params->get('target')]);
        $obj->save();

        return TranslationMemorySegmentResource::make($obj);
    }
}
