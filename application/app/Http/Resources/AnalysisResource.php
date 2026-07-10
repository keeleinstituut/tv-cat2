<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\AddsPageCounts;
use App\Models\TranslationMemory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class AnalysisResource extends JsonResource
{
    use AddsPageCounts;

    private const BAND_KEYS = [
        'ice', 'exact', 'repetitions', 'high_fuzzy',
        'medium_fuzzy', 'low_fuzzy', 'slight_fuzzy', 'no_match',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $jobAnalyses = $this->jobAnalyses;
        $completed = $jobAnalyses->filter(fn ($jobAnalysis) => $jobAnalysis->results !== null);

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'translation_memories' => TranslationMemoryResource::collection(
                TranslationMemory::whereIn('id', $this->translation_memory_ids ?? [])->get()
            ),
            'status' => $jobAnalyses->isNotEmpty() && $completed->count() === $jobAnalyses->count() ? 'done' : 'pending',
            'bands' => $this->aggregateBands($completed),
            'total' => $this->aggregateTotal($completed),
            'job_analyses' => JobAnalysisResource::collection($jobAnalyses),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function aggregateBands(Collection $completed): array
    {
        $bands = [];
        foreach (self::BAND_KEYS as $key) {
            $bands[$key] = $this->withPages([
                'segments' => $completed->sum(fn ($jobAnalysis) => $jobAnalysis->results['bands'][$key]['segments']),
                'words' => $completed->sum(fn ($jobAnalysis) => $jobAnalysis->results['bands'][$key]['words']),
                'chars' => $completed->sum(fn ($jobAnalysis) => $jobAnalysis->results['bands'][$key]['chars']),
            ]);
        }
        return $bands;
    }

    private function aggregateTotal(Collection $completed): array
    {
        return $this->withPages([
            'segments' => $completed->sum(fn ($jobAnalysis) => $jobAnalysis->results['total']['segments']),
            'words' => $completed->sum(fn ($jobAnalysis) => $jobAnalysis->results['total']['words']),
            'chars' => $completed->sum(fn ($jobAnalysis) => $jobAnalysis->results['total']['chars']),
        ]);
    }
}
