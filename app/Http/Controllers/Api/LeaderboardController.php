<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaderboardResource;
use App\Models\PointLog;
use App\Models\Profile;
use App\Services\PointService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PointService $pointService
    ) {}

    /**
     * Get leaderboard rankings.
     *
     * Query Parameters:
     * - page: Page number (default: 1)
     * - per_page: Items per page (default: 50, max: 100)
     * - study_class_id: Filter by study class
     * - period: Time period filter (all-time, monthly, weekly) - default: all-time
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->input('per_page', 50), 100);
        $studyClassFilter = $request->input('study_class_id');
        $period = $request->input('period', 'all-time');

        // Validate period
        $allowedPeriods = ['all-time', 'monthly', 'weekly'];
        if (!in_array($period, $allowedPeriods)) {
            $period = 'all-time';
        }

        // Build base query — exclude admins and teachers (pure students only)
        $query = Profile::with(['user', 'studyClass'])
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['admin', 'teacher']));

        // Apply study class filter if provided
        if ($studyClassFilter) {
            $query->where('study_class_id', $studyClassFilter);
        }

        // Apply period filter by calculating points from logs within the period
        if ($period !== 'all-time') {
            $startDate = $this->getPeriodStartDate($period);

            // For time-based periods, we need to recalculate points from logs
            // Get profile IDs that have activity in the period
            $profileIdsWithActivity = PointLog::where('created_at', '>=', $startDate)
                ->distinct()
                ->pluck('profile_id');

            // Calculate points for each profile within the period
            $profilePointsInPeriod = PointLog::selectRaw('profile_id, SUM(points) as period_points')
                ->where('created_at', '>=', $startDate)
                ->groupBy('profile_id')
                ->pluck('period_points', 'profile_id');

            // Filter to only profiles with activity in this period
            $query->whereIn('id', $profileIdsWithActivity);

            // Get profiles
            $profiles = $query->get();

            // Add period points to each profile
            $profiles->each(function ($profile) use ($profilePointsInPeriod) {
                $profile->period_points = $profilePointsInPeriod->get($profile->id, 0);
            });

            // Sort by period points
            $profiles = $profiles->sortByDesc('period_points')->values();

            // Add rank
            $rank = 1;
            $profiles->each(function ($profile) use (&$rank) {
                $profile->rank = $rank++;
                // Temporarily replace points with period points for display
                $profile->original_points = $profile->points;
                $profile->points = $profile->period_points;
            });

            // Manual pagination
            $total = $profiles->count();
            $currentPage = $request->input('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            $paginatedProfiles = $profiles->slice($offset, $perPage)->values();

            return $this->success([
                'leaderboard' => LeaderboardResource::collection($paginatedProfiles),
                'period' => $period,
                'pagination' => [
                    'current_page' => (int) $currentPage,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => (int) ceil($total / $perPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total),
                ],
            ], 'Leaderboard retrieved successfully.');
        }

        // For all-time, use the points field directly
        $query->orderBy('points', 'desc');

        // Paginate results
        $profiles = $query->paginate($perPage);

        // Add rank to each profile
        $offset = ($profiles->currentPage() - 1) * $profiles->perPage();
        $rank = $offset + 1;
        $profiles->getCollection()->each(function ($profile) use (&$rank) {
            $profile->rank = $rank++;
        });

        return $this->success([
            'leaderboard' => LeaderboardResource::collection($profiles->items()),
            'period' => $period,
            'pagination' => [
                'current_page' => $profiles->currentPage(),
                'per_page' => $profiles->perPage(),
                'total' => $profiles->total(),
                'last_page' => $profiles->lastPage(),
                'from' => $profiles->firstItem(),
                'to' => $profiles->lastItem(),
            ],
        ], 'Leaderboard retrieved successfully.');
    }

    /**
     * Get point history for a specific profile.
     *
     * @param Profile $profile
     * @param Request $request
     * @return JsonResponse
     */
    public function pointsHistory(Profile $profile, Request $request): JsonResponse
    {
        $perPage = min($request->input('per_page', 20), 100);

        $logs = PointLog::where('profile_id', $profile->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $formattedLogs = $logs->getCollection()->map(function ($log) {
            return [
                'id' => $log->id,
                'points' => $log->points,
                'reason' => $log->reason,
                'metadata' => $log->metadata,
                'balance_after' => $log->balance_after,
                'created_at' => $log->created_at->toISOString(),
            ];
        });

        return $this->success([
            'profile' => [
                'id' => $profile->id,
                'display_name' => $profile->display_name,
                'current_points' => $profile->points,
            ],
            'history' => $formattedLogs,
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ],
        ], 'Point history retrieved successfully.');
    }

    /**
     * Get the start date for a given period.
     *
     * @param string $period
     * @return \Carbon\Carbon
     */
    private function getPeriodStartDate(string $period): \Carbon\Carbon
    {
        return match ($period) {
            'weekly' => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            default => now()->subYears(100), // Effectively all-time
        };
    }
}
