<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
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
            'track_id' => $this->track_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'order' => $this->order,
            'created_by' => $this->created_by,
            'creator' => new CreatorResource($this->whenLoaded('creator')),
            'track' => $this->whenLoaded('track', fn () => $this->track ? ['id' => $this->track->id, 'title' => $this->track->title] : null),
            'lessons_count' => $this->when($this->lessons_count !== null, fn () => (int) $this->lessons_count),
            'challenges_count' => $this->when($this->challenges_count !== null, fn () => (int) $this->challenges_count),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
