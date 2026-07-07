<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
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
            'name' => $this->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'source_locale' => $this->source_locale,
            'translation_memories' => $this->whenLoaded('translationMemories', fn() =>
                $this->translationMemories->map(fn($tm) => [
                    'id'            => $tm->id,
                    'name'          => $tm->name,
                    'source_locale' => $tm->source_locale,
                    'target_locale' => $tm->target_locale,
                    'read'          => (bool) $tm->pivot->read,
                    'write'         => (bool) $tm->pivot->write,
                ])
            ),
//            'media' => $this->whenLoaded('media'),
//            'media2' => $this->getMedia('*'),
        ];
    }
}
