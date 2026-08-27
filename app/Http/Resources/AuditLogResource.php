<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
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
            'action' => $this->event,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'profile' => $this->when(
                $this->relationLoaded('user') && $this->user?->profile,
                function () {
                    return [
                        'id' => $this->user->profile->id,
                        'display_name' => $this->user->profile->display_name,
                    ];
                }
            ),
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
