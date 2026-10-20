<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
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
            'module_id' => $this->module_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'content' => $this->content,
            'video_url' => $this->video_url,
            'duration' => $this->duration,
            'order' => $this->order,
            'created_by' => $this->created_by,
            'creator' => new CreatorResource($this->whenLoaded('creator')),
            'module' => $this->whenLoaded('module', fn () => $this->module ? ['id' => $this->module->id, 'title' => $this->module->title] : null),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'challenges_count' => $this->when($this->challenges_count !== null, fn () => (int) $this->challenges_count),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
