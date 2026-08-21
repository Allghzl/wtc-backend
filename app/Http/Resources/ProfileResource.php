<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
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
            'user_id' => $this->user_id,
            'study_class_id' => $this->study_class_id,
            'display_name' => $this->display_name,
            'points' => $this->points,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'last_synced_at' => $this->last_synced_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'user' => new UserResource($this->whenLoaded('user')),
            'study_class' => $this->whenLoaded('studyClass'),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'achievements' => $this->whenLoaded('achievements'),
        ];
    }
}
