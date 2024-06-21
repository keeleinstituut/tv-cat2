<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'target_locale' => $this->target_locale,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'project' => ProjectResource::make($this->whenLoaded('project')),
            'source_file' => MediaResource::make($this->whenLoaded('sourceFileCollection', function () {
                return $this->sourceFileCollection->first();
            })),
            'xliff_file' => MediaResource::make($this->whenLoaded('xliffFileCollection', function () {
                return $this->xliffFileCollection->first();
            })),
            'provider' => 'John Smith',
            'status' => 'New',
            'confirmed' => 6,
        ];
    }
}
