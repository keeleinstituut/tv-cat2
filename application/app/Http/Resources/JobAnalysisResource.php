<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\AddsPageCounts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobAnalysisResource extends JsonResource
{
    use AddsPageCounts;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'job' => JobResource::make($this->whenLoaded('job')),
            'languages' => [
                'source' => $this->job->project->source_locale,
                'target' => $this->job->target_locale,
            ],
            'results' => $this->results ? [
                'bands' => array_map(fn ($stats) => $this->withPages($stats), $this->results['bands']),
                'total' => $this->withPages($this->results['total']),
            ] : null,
        ];
    }
}
