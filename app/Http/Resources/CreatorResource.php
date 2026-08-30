<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreatorResource extends JsonResource
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
            'display_name' => $this->display_name,
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('name')->toArray();
            }, []),
            'avatar' => $this->user?->avatar,
        ];
    }
}
