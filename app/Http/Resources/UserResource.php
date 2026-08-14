<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Generate avatar URL dari storage path
        $avatarUrl = null;
        if ($this->avatar) {
            $avatarService = app(\App\Services\AvatarService::class);
            $avatarUrl = $avatarService->generateAvatarUrl($this->resource);
        }

        return [
            'id' => $this->id,
            'puid' => $this->puid,
            'name' => $this->name,
            'email' => $this->email,
            'provider' => $this->provider,
            'avatar' => $avatarUrl,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
