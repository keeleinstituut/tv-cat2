<?php

namespace App\Http\Controllers;

use App\Http\Requests\SegmentBulkUpdateRequest;
use App\Http\Requests\SegmentIndexRequest;
use App\Http\Requests\SegmentUpdateRequest;
use App\Http\Resources\SegmentResource;
use App\Models\Segment;
use App\Models\TranslationMemorySegment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SegmentController extends Controller
{
    public function index(SegmentIndexRequest $request)
    {
        $params = collect($request->validated());
        $query = $this->getBaseQuery();

        if ($param = $params->get('job_id')) {
            $query = $query->where('job_id', $param);
        }

        // Normalise so applyFilters reads 'target_filter' for the target text search
        if ($params->has('target')) {
            $params->put('target_filter', $params->get('target'));
        }

        $query = $this->applyFilters($query, $params);

        match($params->get('sort')) {
            'source_asc'  => $query = $query->orderBy('source', 'asc'),
            'source_desc' => $query = $query->orderBy('source', 'desc'),
            'shortest'    => $query = $query->orderByRaw('LENGTH(source) ASC'),
            'longest'     => $query = $query->orderByRaw('LENGTH(source) DESC'),
            'match_asc'   => $query = $query->orderByRaw('pretranslate_suggestion_score ASC NULLS FIRST'),
            'match_desc'  => $query = $query->orderByRaw('pretranslate_suggestion_score DESC NULLS LAST'),
            default       => $query = $query->orderBy('position', 'asc'),
        };

        $data = $query->paginate($params->get('per_page'));

        return SegmentResource::collection($data)
            ->additional([
                'meta' => [
                    'count'            => $query->count(),
                    'translated_count' => $query->whereNotNull('target')->count()
                ],
            ]);
    }

    public function update(SegmentUpdateRequest $request)
    {
        $id = $request->route('id');
        $params = collect($request->validated());

        return DB::transaction(function () use ($id, $params) {
            $query = $this->getBaseQuery();
            $obj = $query->find($id);

            if ($params->has('target')) {
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
            }

            if ($params->has('confirmed')) {
                $obj->confirmed = $params->get('confirmed');
                $obj->save();
            }

            // TM saving part
            if ($params->get('confirmed')) {
                $prevSegment = Segment::where('job_id', $obj->job_id)
                    ->where('position', '<', $obj->position)
                    ->orderBy('position', 'desc')
                    ->first();

                $nextSegment = Segment::where('job_id', $obj->job_id)
                    ->where('position', '>', $obj->position)
                    ->orderBy('position', 'asc')
                    ->first();

                $writableTms = $obj->job
                    ->project
                    ->translationMemories()
                    ->wherePivot('write', true)
                    ->get();

                foreach ($writableTms as $tm) {
                    TranslationMemorySegment::updateOrCreate(
                        [
                            'translation_memory_id' => $tm->id,
                            'segment_id'            => $obj->id,
                        ],
                        [
                            'source'                => $obj->source,
                            'source_context_before' => $prevSegment?->source,
                            'source_context_after'  => $nextSegment?->source,
                            'target'                => $obj->target,
                            'target_context_before' => $prevSegment?->target,
                            'target_context_after'  => $nextSegment?->target,
                        ]
                    );
                }
            }

            return SegmentResource::make($obj);
        });
    }

    public function bulkUpdate(SegmentBulkUpdateRequest $request)
    {
        $params = collect($request->validated());

        return DB::transaction(function () use ($params) {
            $query = $this->getBaseQuery()->where('job_id', $params->get('job_id'));

            $segmentIds = $params->get('segment_ids', []);
            if (!empty($segmentIds)) {
                $query = $query->whereIn('id', $segmentIds);
            } else {
                $query = $this->applyFilters($query, $params);
            }

            $updateData = [];
            if ($params->has('confirmed')) {
                $updateData['confirmed'] = $params->get('confirmed');
            }
            if ($params->has('target')) {
                $updateData['target'] = $params->get('target');
            }

            if (empty($updateData)) {
                return response()->json(['meta' => ['count' => 0]]);
            }

            // Expand to repetition group members when updating target
            if ($params->has('target') && $params->get('save_repetitions', true)) {
                $repetitionGroups = (clone $query)
                    ->whereNotNull('repetition_group')
                    ->pluck('repetition_group')
                    ->unique()
                    ->values();

                if ($repetitionGroups->isNotEmpty()) {
                    $query = $this->getBaseQuery()
                        ->where('job_id', $params->get('job_id'))
                        ->where(function ($q) use ($segmentIds, $repetitionGroups) {
                            if (!empty($segmentIds)) {
                                $q->whereIn('id', $segmentIds);
                            }
                            $q->orWhereIn('repetition_group', $repetitionGroups);
                        });
                }
            }

            $count = (clone $query)->count();
            (clone $query)->update($updateData);

            // Save confirmed segments to writable TMs with position context
            if ($params->get('confirmed')) {
                $segments = (clone $query)->orderBy('position')->get();

                // Build position-keyed map for efficient prev/next lookup
                $byPosition = $segments->keyBy('position');
                $positions  = $byPosition->keys()->sort()->values();

                $writableTms = $segments->first()?->job
                    ?->project
                    ?->translationMemories()
                    ->wherePivot('write', true)
                    ->get() ?? collect();

                if ($writableTms->isNotEmpty()) {
                    foreach ($segments as $seg) {
                        $posIdx  = $positions->search($seg->position);
                        $prevPos = $posIdx > 0 ? $positions->get($posIdx - 1) : null;
                        $nextPos = $posIdx !== false ? $positions->get($posIdx + 1) : null;

                        // Fall back to DB for prev/next outside the bulk set
                        $prev = $prevPos !== null
                            ? $byPosition->get($prevPos)
                            : Segment::where('job_id', $seg->job_id)
                                ->where('position', '<', $seg->position)
                                ->orderBy('position', 'desc')
                                ->first();

                        $next = $nextPos !== null
                            ? $byPosition->get($nextPos)
                            : Segment::where('job_id', $seg->job_id)
                                ->where('position', '>', $seg->position)
                                ->orderBy('position', 'asc')
                                ->first();

                        foreach ($writableTms as $tm) {
                            TranslationMemorySegment::updateOrCreate(
                                [
                                    'translation_memory_id' => $tm->id,
                                    'segment_id'            => $seg->id,
                                ],
                                [
                                    'source'                => $seg->source,
                                    'source_context_before' => $prev?->source,
                                    'source_context_after'  => $next?->source,
                                    'target'                => $seg->target,
                                    'target_context_before' => $prev?->target,
                                    'target_context_after'  => $next?->target,
                                ]
                            );
                        }
                    }
                }
            }

            return response()->json(['meta' => ['count' => $count]]);
        });
    }

    private function applyFilters(Builder $query, Collection $params): Builder
    {
        if ($param = $params->get('source')) {
            $query = $query->where('source', 'ilike', "%$param%");
        }

        if ($param = $params->get('target_filter')) {
            $query = $query->where('target', 'ilike', "%$param%");
        }

        $hasEmpty           = $params->get('filter_empty');
        $hasNotEmpty        = $params->get('filter_not_empty');
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

        return $query;
    }

    private function getBaseQuery()
    {
        return Segment::getModel();
    }
}
