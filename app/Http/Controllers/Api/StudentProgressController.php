<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LessonCompletion;
use App\Models\Profile;
use App\Models\Track;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentProgressController extends Controller
{
    use ApiResponse;

    /**
     * List all student profiles with enrollment summary.
     * Supports: search, sort (progress_desc|progress_asc|name_asc|points_desc), page, per_page.
     */
    public function profiles(Request $request): JsonResponse
    {
        $search  = $request->get('search');
        $sort    = $request->get('sort', 'name_asc');
        $perPage = max(1, min((int) $request->get('per_page', 15), 100));

        $query = Profile::with(['user', 'trackEnrollments'])
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['admin', 'teacher']));

        if ($search) {
            $query->where('display_name', 'like', "%{$search}%");
        }

        $profiles = $query->get(['id', 'user_id', 'display_name', 'points', 'study_class_id']);

        // Calculate progress for each profile
        $profiles = $profiles->map(function (Profile $profile) {
            $enrollments       = $profile->trackEnrollments;
            $enrolledCount     = $enrollments->count();
            $completedCount    = $enrollments->where('status', 'completed')->count();
            $inProgressCount   = $enrollments->whereNotIn('status', ['completed', 'dropped'])->count();

            // Simple progress ratio (completed / enrolled)
            $progressPct = $enrolledCount > 0 ? round(($completedCount / $enrolledCount) * 100) : 0;

            $profile->enrolled_tracks_count   = $enrolledCount;
            $profile->completed_tracks_count  = $completedCount;
            $profile->in_progress_tracks_count = $inProgressCount;
            $profile->overall_progress        = $progressPct;

            return $profile;
        });

        // Sort in memory (to avoid complex DB joins)
        $sorted = match ($sort) {
            'progress_desc' => $profiles->sortByDesc('overall_progress'),
            'progress_asc'  => $profiles->sortBy('overall_progress'),
            'points_desc'   => $profiles->sortByDesc('points'),
            default         => $profiles->sortBy('display_name'),
        };
        $profiles = $sorted->values();

        // Manual pagination
        $page  = max(1, (int) $request->get('page', 1));
        $total = $profiles->count();
        $items = $profiles->forPage($page, $perPage)->values();

        $data = $items->map(fn ($p) => [
            'id'                    => $p->id,
            'display_name'          => $p->display_name ?? $p->user?->name ?? 'Unknown',
            'avatar'                => $p->user?->avatar,
            'points'                => $p->points,
            'enrolled_tracks_count' => $p->enrolled_tracks_count,
            'completed_tracks_count' => $p->completed_tracks_count,
            'in_progress_tracks_count' => $p->in_progress_tracks_count,
            'overall_progress'      => $p->overall_progress,
        ]);

        return $this->success($data, 'Student profiles retrieved successfully.', [
            'current_page' => $page,
            'per_page'     => $perPage,
            'total'        => $total,
            'last_page'    => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * Detail progress for a specific profile across their enrolled tracks.
     * Supports: status filter (in_progress|completed), sort (progress_desc|progress_asc|title_asc).
     */
    public function profileDetail(Request $request, Profile $profile): JsonResponse
    {
        $statusFilter = $request->get('status');
        $sort         = $request->get('sort', 'title_asc');

        $enrollments = $profile->trackEnrollments()
            ->with(['track.modules.lessons'])
            ->get();

        if ($statusFilter === 'completed') {
            $enrollments = $enrollments->where('status', 'completed');
        } elseif ($statusFilter === 'in_progress') {
            $enrollments = $enrollments->whereNotIn('status', ['completed', 'dropped']);
        }

        // Get all lesson IDs completed by this profile
        $completedLessonIds = LessonCompletion::where('profile_id', $profile->id)
            ->pluck('lesson_id')
            ->toArray();

        $tracks = $enrollments->map(function ($enrollment) use ($completedLessonIds) {
            $track        = $enrollment->track;
            $allLessons   = $track->modules->flatMap(fn ($m) => $m->lessons)->pluck('id');
            $totalLessons = $allLessons->count();
            $completed    = $allLessons->intersect($completedLessonIds)->count();
            $progressPct  = $totalLessons > 0 ? round(($completed / $totalLessons) * 100) : 0;

            return [
                'id'                 => $track->id,
                'title'              => $track->title,
                'slug'               => $track->slug,
                'modules_count'      => $track->modules->count(),
                'total_lessons'      => $totalLessons,
                'completed_lessons'  => $completed,
                'progress_percentage' => $progressPct,
                'status'             => $enrollment->status === 'completed' ? 'completed' : 'in_progress',
                'enrolled_at'        => $enrollment->enrolled_at,
            ];
        });

        $sorted = match ($sort) {
            'progress_desc' => $tracks->sortByDesc('progress_percentage'),
            'progress_asc'  => $tracks->sortBy('progress_percentage'),
            default         => $tracks->sortBy('title'),
        };
        $tracks = $sorted->values();

        return $this->success([
            'profile' => [
                'id'           => $profile->id,
                'display_name' => $profile->display_name ?? $profile->user?->name ?? 'Unknown',
                'avatar'       => $profile->user?->avatar,
                'points'       => $profile->points,
            ],
            'tracks' => $tracks,
        ], 'Profile progress retrieved successfully.');
    }

    /**
     * List all tracks that have enrollments.
     * Supports: search, sort (avg_progress_desc|avg_progress_asc|enrolled_desc|title_asc), page, per_page.
     */
    public function tracks(Request $request): JsonResponse
    {
        $search  = $request->get('search');
        $sort    = $request->get('sort', 'title_asc');
        $perPage = max(1, min((int) $request->get('per_page', 15), 100));

        $query = Track::withCount(['enrollments as enrolled_count'])
            ->with(['modules.lessons', 'enrollments'])
            ->has('enrollments');

        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        $tracks = $query->get();

        // Calculate avg progress per track
        $tracks = $tracks->map(function (Track $track) {
            $allLessonIds  = $track->modules->flatMap(fn ($m) => $m->lessons)->pluck('id');
            $totalLessons  = $allLessonIds->count();
            $enrolledCount = $track->enrolled_count;
            $completedCount = $track->enrollments->where('status', 'completed')->count();

            if ($enrolledCount > 0 && $totalLessons > 0) {
                // Avg progress: sum of individual progress / enrolled count
                $profileIds       = $track->enrollments->pluck('profile_id');
                $completedPerProfile = LessonCompletion::whereIn('profile_id', $profileIds)
                    ->whereIn('lesson_id', $allLessonIds)
                    ->selectRaw('profile_id, COUNT(*) as cnt')
                    ->groupBy('profile_id')
                    ->pluck('cnt', 'profile_id');

                $totalPct = $profileIds->sum(fn ($pid) => ($completedPerProfile[$pid] ?? 0) / $totalLessons * 100);
                $avgProgress = round($totalPct / $enrolledCount);
            } else {
                $avgProgress = 0;
            }

            $track->avg_progress    = $avgProgress;
            $track->completed_count = $completedCount;
            $track->total_lessons   = $totalLessons;

            return $track;
        });

        $sorted2 = match ($sort) {
            'avg_progress_desc' => $tracks->sortByDesc('avg_progress'),
            'avg_progress_asc'  => $tracks->sortBy('avg_progress'),
            'enrolled_desc'     => $tracks->sortByDesc('enrolled_count'),
            default             => $tracks->sortBy('title'),
        };
        $tracks = $sorted2->values();

        $page  = max(1, (int) $request->get('page', 1));
        $total = $tracks->count();
        $items = $tracks->forPage($page, $perPage)->values();

        $data = $items->map(fn ($t) => [
            'id'             => $t->id,
            'title'          => $t->title,
            'slug'           => $t->slug,
            'modules_count'  => $t->modules->count(),
            'total_lessons'  => $t->total_lessons,
            'enrolled_count' => $t->enrolled_count,
            'completed_count' => $t->completed_count,
            'avg_progress'   => $t->avg_progress,
        ]);

        return $this->success($data, 'Tracks retrieved successfully.', [
            'current_page' => $page,
            'per_page'     => $perPage,
            'total'        => $total,
            'last_page'    => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * Detail: list enrolled profiles with their progress in a specific track.
     * Supports: status filter (in_progress|completed), sort (progress_desc|progress_asc|name_asc).
     */
    public function trackDetail(Request $request, Track $track): JsonResponse
    {
        $statusFilter = $request->get('status');
        $sort         = $request->get('sort', 'progress_desc');

        $enrollments = $track->enrollments()->with('profile.user')->get();

        if ($statusFilter === 'completed') {
            $enrollments = $enrollments->where('status', 'completed');
        } elseif ($statusFilter === 'in_progress') {
            $enrollments = $enrollments->whereNotIn('status', ['completed', 'dropped']);
        }

        $allLessonIds = $track->modules()
            ->with('lessons')
            ->get()
            ->flatMap(fn ($m) => $m->lessons)
            ->pluck('id');

        $totalLessons = $allLessonIds->count();

        $profileIds = $enrollments->pluck('profile_id');

        $completedMap = LessonCompletion::whereIn('profile_id', $profileIds)
            ->whereIn('lesson_id', $allLessonIds)
            ->selectRaw('profile_id, COUNT(*) as cnt')
            ->groupBy('profile_id')
            ->pluck('cnt', 'profile_id');

        $profiles = $enrollments->map(function ($enrollment) use ($totalLessons, $completedMap) {
            $profile     = $enrollment->profile;
            $completed   = $completedMap[$profile->id] ?? 0;
            $progressPct = $totalLessons > 0 ? round(($completed / $totalLessons) * 100) : 0;

            return [
                'id'                  => $profile->id,
                'display_name'        => $profile->display_name ?? $profile->user?->name ?? 'Unknown',
                'avatar'              => $profile->user?->avatar,
                'points'              => $profile->points,
                'completed_lessons'   => $completed,
                'total_lessons'       => $totalLessons,
                'progress_percentage' => $progressPct,
                'status'              => $enrollment->status === 'completed' ? 'completed' : 'in_progress',
                'enrolled_at'         => $enrollment->enrolled_at,
            ];
        });

        $sorted3 = match ($sort) {
            'progress_asc' => $profiles->sortBy('progress_percentage'),
            'name_asc'     => $profiles->sortBy('display_name'),
            default        => $profiles->sortByDesc('progress_percentage'),
        };
        $profiles = $sorted3->values();

        return $this->success([
            'track' => [
                'id'            => $track->id,
                'title'         => $track->title,
                'slug'          => $track->slug,
                'modules_count' => $track->modules()->count(),
                'total_lessons' => $totalLessons,
            ],
            'profiles' => $profiles,
        ], 'Track progress retrieved successfully.');
    }
}
