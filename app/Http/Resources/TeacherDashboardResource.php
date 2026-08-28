<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the array returned by TeacherDashboardService::dashboard().
 *
 * $this->resource is the plain array, so we access keys directly.
 */
class TeacherDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pendingQueue = collect($this->resource['pending_submissions'])
            ->map(fn ($submission) => [
                'id'           => $submission->id,
                'status'       => $submission->status,
                'submitted_at' => $submission->submitted_at?->toISOString(),
                'profile'      => $submission->profile ? [
                    'id'           => $submission->profile->id,
                    'display_name' => $submission->profile->display_name,
                ] : null,
                'challenge'    => $submission->challenge ? [
                    'id'        => $submission->challenge->id,
                    'title'     => $submission->challenge->title,
                    'slug'      => $submission->challenge->slug,
                    'type'      => $submission->challenge->type,
                    'max_score' => $submission->challenge->max_score,
                ] : null,
            ]);

        $leaderboard = collect($this->resource['leaderboard'])
            ->map(fn ($profile) => [
                'id'           => $profile->id,
                'display_name' => $profile->display_name,
                'points'       => $profile->points,
            ]);

        return [
            'stats'               => $this->resource['stats'],
            'pending_submissions' => $pendingQueue,
            'leaderboard'         => $leaderboard,
        ];
    }
}
