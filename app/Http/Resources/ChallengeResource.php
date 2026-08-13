<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChallengeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'module_id'  => $this->module_id,
            'lesson_id'  => $this->lesson_id,
            'title'      => $this->title,
            'slug'       => $this->slug,
            'type'       => $this->type,
            'difficulty' => $this->difficulty,
            'order'      => $this->order,
            'content'    => $this->content,
            'settings'   => $this->settings,
            'metadata'   => $this->metadata,
            'max_score'  => $this->max_score,
            'points'     => $this->points,
            'allowed_attempts' => $this->allowed_attempts,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
