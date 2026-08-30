<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\Profile;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileBadgeController extends Controller
{
    use ApiResponse;

    /**
     * GET /profiles/{profile}/badges
     * Return pinned badges for a profile (publicly visible to any auth user).
     */
    public function index(Profile $profile): JsonResponse
    {
        $badges = $profile->achievements()
            ->wherePivot('is_pinned', true)
            ->orderByPivot('awarded_at')
            ->get();

        return $this->success($badges, 'Pinned badges retrieved successfully.');
    }

    /**
     * POST /profiles/{profile}/badges
     * Pin an earned achievement badge. Max 5 pinned. Must own the achievement.
     */
    public function store(Request $request, Profile $profile): JsonResponse
    {
        $this->authorizeOwner($request, $profile);

        $request->validate([
            'achievement_id' => ['required', 'integer', 'exists:achievements,id'],
        ]);

        $achievementId = (int) $request->input('achievement_id');

        // Verify this profile has earned the achievement
        $pivot = $profile->achievements()
            ->wherePivot('achievement_id', $achievementId)
            ->first();

        if (!$pivot) {
            return $this->error('You have not earned this achievement.', 403);
        }

        // Enforce max 5 pinned badges
        $pinnedCount = $profile->achievements()
            ->wherePivot('is_pinned', true)
            ->count();

        if ($pinnedCount >= 5) {
            return $this->error('You can pin a maximum of 5 badges.', 422);
        }

        // Already pinned — idempotent
        if ($pivot->pivot->is_pinned) {
            return $this->success(null, 'Badge is already pinned.');
        }

        $profile->achievements()->updateExistingPivot($achievementId, ['is_pinned' => true]);

        return $this->success(null, 'Badge pinned successfully.');
    }

    /**
     * DELETE /profiles/{profile}/badges/{achievementId}
     * Unpin a badge. Must own the profile.
     */
    public function destroy(Request $request, Profile $profile, int $achievementId): JsonResponse
    {
        $this->authorizeOwner($request, $profile);

        $pivot = $profile->achievements()
            ->wherePivot('achievement_id', $achievementId)
            ->first();

        if (!$pivot) {
            return $this->error('Achievement not found on this profile.', 404);
        }

        $profile->achievements()->updateExistingPivot($achievementId, ['is_pinned' => false]);

        return $this->success(null, 'Badge unpinned successfully.');
    }

    /**
     * GET /profiles/{profile}/achievements
     * All earned achievements for a profile.
     */
    public function myAchievements(Profile $profile): JsonResponse
    {
        $achievements = $profile->achievements()
            ->withPivot(['is_pinned', 'awarded_at'])
            ->orderByPivot('awarded_at', 'desc')
            ->get()
            ->map(function ($achievement) {
                return array_merge($achievement->toArray(), [
                    'is_pinned'  => (bool) $achievement->pivot->is_pinned,
                    'awarded_at' => $achievement->pivot->awarded_at,
                ]);
            });

        return $this->success($achievements, 'Achievements retrieved successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Private helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Ensure the authenticated user owns this profile, or is admin/teacher.
     */
    private function authorizeOwner(Request $request, Profile $profile): void
    {
        $user        = $request->user();
        $userProfile = $user->profile;

        $isOwner    = $userProfile && $userProfile->id === $profile->id;
        $isElevated = $user->hasRole('admin') || $user->hasRole('teacher');

        if (!$isOwner && !$isElevated) {
            abort(403, 'Unauthorized.');
        }
    }
}
