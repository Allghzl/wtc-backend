<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyTrackResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'enrollment' => [
                'status' => $this->pivot->status,
                'enrolled_at' => $this->pivot->enrolled_at
                    ? Carbon::parse($this->pivot->enrolled_at)->toISOString()
                    : null,
                'completed_at' => $this->pivot->completed_at
                    ? Carbon::parse($this->pivot->completed_at)->toISOString()
                    : null,
            ],
            'modules_count' => $this->modules_count ?? 0,
        ];
    }
}
