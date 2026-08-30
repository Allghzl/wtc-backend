<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaderboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'rank' => $this->rank,
            'profile' => [
                'id' => $this->id,
                'display_name' => $this->display_name ?? $this->user?->name ?? 'Unknown',
                'avatar' => $this->user?->avatar
                    ? app(\App\Services\AvatarService::class)->generateAvatarUrl($this->user)
                    : null,
                'study_class' => $this->whenLoaded('studyClass', function () {
                    return [
                        'id' => $this->studyClass->id,
                        'name' => $this->studyClass->name,
                    ];
                }),
            ],
            'points' => $this->points,
        ];
    }
}
