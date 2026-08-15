<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackProgressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'track' => new TrackResource($this->resource['track']),
            'enrollment' => new TrackEnrollmentResource($this->resource['enrollment']),
            'progress' => [
                'percent' => $this->resource['progress']['percent'],
                'completed_modules' => $this->resource['progress']['completed_modules'],
                'total_modules' => $this->resource['progress']['total_modules'],
                'completed_challenges' => $this->resource['progress']['completed_challenges'],
                'total_challenges' => $this->resource['progress']['total_challenges'],
            ],
            'modules' => $this->resource['modules'] ?? [],
        ];
    }
}
