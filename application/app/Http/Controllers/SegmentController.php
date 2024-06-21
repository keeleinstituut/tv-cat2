<?php

namespace App\Http\Controllers;

use App\Http\Requests\SegmentIndexRequest;
use App\Http\Requests\SegmentUpdateRequest;
use App\Http\Resources\SegmentResource;
use App\Models\Segment;
use App\Models\TranslationMemorySegment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SegmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(SegmentIndexRequest $request)
    {
        $params = collect($request->validated());
        $query = $this->getBaseQuery();

        if ($param = $params->get('job_id')) {
            $query = $query->where('job_id', $param);
        }

        if ($param = $params->get('source')) {
            $query = $query->where('source', 'ilike', "%$param%");
        }

        if ($param = $params->get('target')) {
            $query = $query->where('target', 'ilike', "%$param%");
        }

        $query = $query->orderBy('position', 'asc');

        $data = $query->paginate($params->get('per_page'));

        return SegmentResource::collection($data)
            ->additional([
                'meta' => [
                    'count' => $query->count(),
                    'translated_count' => $query->whereNotNull('target')->count()
                ],
            ]);
    }

    // /**
    //  * Store a newly created resource in storage.
    //  */
    // public function store(Request $request)
    // {
    //     //
    // }

    // /**
    //  * Display the specified resource.
    //  */
    // public function show(string $id)
    // {
    //     //
    // }

    /**
     * Update the specified resource in storage.
     */
    public function update(SegmentUpdateRequest $request)
    {
        $id = $request->route('id');
        $params = collect($request->validated());

        return DB::transaction(function () use ($id, $params) {
            $query = $this->getBaseQuery();
            $obj = $query->find($id);

            if ($obj->repetition_group && $params->get('save_repetitions', True)) {
                $this->getBaseQuery()
                    ->where('repetition_group', $obj->repetition_group)
                    ->update([
                        'target' => $params->get('target'),
                    ]);
                $obj->refresh();
            } else {
                $obj->fill([
                    'target' => $params->get('target'),
                ]);
                $obj->save();
            }

            // $translationMemorySegment = TranslationMemorySegment::firstOrNew(['segment_id' => $obj->id]);
            // $translationMemorySegment->fill([
            //     'source' => $obj->source,
            //     'target' => $obj->target,
            // ]);
            // $translationMemorySegment->save();

            return SegmentResource::make($obj);
        });
    }

    // /**
    //  * Remove the specified resource from storage.
    //  */
    // public function destroy(string $id)
    // {
    //     //
    // }

    private function getBaseQuery() {
        return Segment::getModel();
    }
}
