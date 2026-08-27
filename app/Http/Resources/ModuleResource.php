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
            'creator' => new ProfileResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
