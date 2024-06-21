<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalysisResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'type' => 'Default',
            'provider' => 'John Smith',
            'languages' => [
                'source' => $this->job->project->source_locale,
                'target' => $this->job->target_locale,
            ]
        ];
    }
}
