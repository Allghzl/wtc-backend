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
            'title'      => $this->title,
            'slug'       => $this->slug,
            'type'       => $this->type,
            'content'    => $this->content,
            'metadata'   => $this->metadata,
            'max_score'  => $this->max_score,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
