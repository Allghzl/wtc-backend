<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherSubmissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totalScore = ($this->auto_score ?? 0) + ($this->manual_score ?? 0);

        return [
            'id'             => $this->id,
            'attempt_number' => $this->attempt_number,
            'status'         => $this->status,
            'submitted_at'   => $this->submitted_at?->toISOString(),
            'score'          => [
                'auto'   => $this->auto_score,
                'manual' => $this->manual_score,
                'total'  => $totalScore,
            ],
            'feedback'  => $this->feedback,
            'profile'   => $this->whenLoaded('profile', fn () => [
                'id'           => $this->profile->id,
                'display_name' => $this->profile->display_name,
            ]),
            'challenge' => $this->whenLoaded('challenge', fn () => [
                'id'        => $this->challenge->id,
                'title'     => $this->challenge->title,
                'slug'      => $this->challenge->slug,
                'type'      => $this->challenge->type,
                'max_score' => $this->challenge->max_score,
            ]),
        ];
    }
}
