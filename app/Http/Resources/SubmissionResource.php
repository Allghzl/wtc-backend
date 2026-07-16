<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'challenge_id'      => $this->challenge_id,
            'user_id'           => $this->user_id,
            'status'            => $this->status,
            'submitted_content' => $this->submitted_content,
            'file_url'          => $this->file_path ? asset('storage/' . $this->file_path) : null,
            'auto_score'        => $this->auto_score,
            'manual_score'      => $this->manual_score,
            'total_score'       => ($this->auto_score ?? 0) + ($this->manual_score ?? 0),
            'feedback'          => $this->feedback,
            'submitted_at'      => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
