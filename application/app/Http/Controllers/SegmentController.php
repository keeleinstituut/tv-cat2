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

        $hasEmpty = $params->get('filter_empty');
        $hasNotEmpty = $params->get('filter_not_empty');
        $hasFirstRepetition = $params->get('filter_first_repetition');

        if ($hasEmpty || $hasNotEmpty || $hasFirstRepetition) {
            $query = $query->where(function ($q) use ($hasEmpty, $hasNotEmpty, $hasFirstRepetition) {
                if ($hasEmpty) {
                    $q->orWhere(fn($q2) => $q2->whereNull('target')->orWhere('target', ''));
                }
                if ($hasNotEmpty) {
                    $q->orWhere(fn($q2) => $q2->whereNotNull('target')->where('target', '!=', ''));
                }
                if ($hasFirstRepetition) {
                    $q->orWhere(fn($q2) => $q2->whereNotNull('repetition_group')->whereColumn('repetition_group', 'id'));
                }
            });
        }

        $ptFilters = [
            'filter_score_101' => fn($q) => $q->where('pretranslate_suggestion_score', '>', 100),
            'filter_score_100' => fn($q) => $q->where('pretranslate_suggestion_score', 100),
            'filter_score_99'  => fn($q) => $q->where('pretranslate_suggestion_score', 99),
            'filter_fuzzy'     => fn($q) => $q->where('pretranslate_suggestion_provider_type', 'TM')->where('pretranslate_suggestion_score', '<', 99),
            'filter_tm'        => fn($q) => $q->where('pretranslate_suggestion_provider_type', 'TM'),
            'filter_nt'        => fn($q) => $q->where('pretranslate_suggestion_provider_type', 'NT'),
            'filter_mt'        => fn($q) => $q->where('pretranslate_suggestion_provider_type', 'MT'),
            'filter_no_match'  => fn($q) => $q->whereNull('pretranslate_suggestion_provider_type'),
        ];
        $activePtFilters = array_filter($ptFilters, fn($_, $key) => $params->get($key), ARRAY_FILTER_USE_BOTH);

        if (!empty($activePtFilters)) {
            $query = $query->where(function ($q) use ($activePtFilters) {
                foreach ($activePtFilters as $fn) {
                    $q->orWhere($fn);
                }
            });
        }

        match($params->get('sort')) {
            'source_asc'  => $query = $query->orderBy('source', 'asc'),
            'source_desc' => $query = $query->orderBy('source', 'desc'),
            'shortest'    => $query = $query->orderByRaw('LENGTH(source) ASC'),
            'longest'     => $query = $query->orderByRaw('LENGTH(source) DESC'),
            'match_asc'   => $query = $query->orderByRaw('pretranslate_suggestion_score ASC NULLS LAST'),
            'match_desc'  => $query = $query->orderByRaw('pretranslate_suggestion_score DESC NULLS LAST'),
            default       => $query = $query->orderBy('position', 'asc'),
        };

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
