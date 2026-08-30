<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    use ApiResponse;

    /**
     * GET /admin/achievements
     * All achievements — admin only.
     */
    public function index(): JsonResponse
    {
        $achievements = Achievement::orderBy('name')->get();

        return $this->success($achievements, 'Achievements retrieved successfully.');
    }

    /**
     * POST /admin/achievements
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'description'    => ['required', 'string'],
            'trigger_type'   => ['required', 'string', 'max:255'],
            'trigger_config' => ['nullable', 'array'],
            'points_reward'  => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['nullable', 'boolean'],
            'badge_emoji'    => ['nullable', 'string', 'max:16'],
        ]);

        $achievement = Achievement::create($validated);

        return $this->success($achievement, 'Achievement created successfully.', 201);
    }

    /**
     * PUT /admin/achievements/{achievement}
     */
    public function update(Request $request, Achievement $achievement): JsonResponse
    {
        $validated = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'description'    => ['sometimes', 'string'],
            'trigger_type'   => ['sometimes', 'string', 'max:255'],
            'trigger_config' => ['nullable', 'array'],
            'points_reward'  => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['nullable', 'boolean'],
            'badge_emoji'    => ['nullable', 'string', 'max:16'],
        ]);

        $achievement->update($validated);

        return $this->success($achievement->fresh(), 'Achievement updated successfully.');
    }

    /**
     * DELETE /admin/achievements/{achievement}
     */
    public function destroy(Achievement $achievement): JsonResponse
    {
        $achievement->delete();

        return $this->success(null, 'Achievement deleted successfully.');
    }

    /**
     * PATCH /admin/achievements/{achievement}/toggle
     * Flip the is_active flag.
     */
    public function toggle(Achievement $achievement): JsonResponse
    {
        $achievement->update(['is_active' => !$achievement->is_active]);

        $status = $achievement->is_active ? 'activated' : 'deactivated';

        return $this->success($achievement->fresh(), "Achievement {$status} successfully.");
    }

    /**
     * GET /achievements
     * Active achievements visible to any authenticated user.
     */
    public function publicIndex(): JsonResponse
    {
        $achievements = Achievement::where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->success($achievements, 'Achievements retrieved successfully.');
    }
}
