<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\LessonCompletion;
use App\Models\Profile;
use App\Models\Track;
use App\Services\AvatarService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentProgressController extends Controller
{
    use ApiResponse;

    public function __construct(private AvatarService $avatarService) {}

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

        $profileIds = $profiles->pluck('id');

        // Total lessons per profile via raw DB join — avoids the broken Eloquent
        // collection chain ($e->track?->modules?->sum(...) always returns 0).
        $lessonCountsPerProfile = DB::table('lessons')
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->join('track_enrollments', 'modules.track_id', '=', 'track_enrollments.track_id')
            ->whereIn('track_enrollments.profile_id', $profileIds)
            ->whereNull('lessons.deleted_at')
            ->whereNull('modules.deleted_at')
            ->selectRaw('track_enrollments.profile_id, COUNT(DISTINCT lessons.id) as cnt')
            ->groupBy('track_enrollments.profile_id')
            ->pluck('cnt', 'profile_id');

        // Completed lessons per profile scoped to lessons that belong to enrolled tracks.
        // Using DISTINCT lesson_id prevents double-counting when multiple enrolled tracks
        // share the same lesson.
        $completionCounts = DB::table('lesson_completions')
            ->join('lessons', 'lesson_completions.lesson_id', '=', 'lessons.id')
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->join('track_enrollments', 'modules.track_id', '=', 'track_enrollments.track_id')
            ->whereColumn('lesson_completions.profile_id', 'track_enrollments.profile_id')
            ->whereIn('lesson_completions.profile_id', $profileIds)
            ->whereNull('lessons.deleted_at')
            ->whereNull('modules.deleted_at')
            ->selectRaw('lesson_completions.profile_id, COUNT(DISTINCT lessons.id) as cnt')
            ->groupBy('lesson_completions.profile_id')
            ->pluck('cnt', 'profile_id');

        $profiles = $profiles->map(function (Profile $profile) use ($lessonCountsPerProfile, $completionCounts) {
            $enrollments      = $profile->trackEnrollments;
            $enrolledCount    = $enrollments->count();
            $completedCount   = $enrollments->where('status', 'completed')->count();
            $inProgressCount  = $enrollments->whereNotIn('status', ['completed', 'dropped'])->count();

            $totalLessons     = (int) ($lessonCountsPerProfile[$profile->id] ?? 0);
            $completedLessons = (int) ($completionCounts[$profile->id] ?? 0);
            $progressPct      = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

            $profile->enrolled_tracks_count    = $enrolledCount;
            $profile->completed_tracks_count   = $completedCount;
            $profile->in_progress_tracks_count = $inProgressCount;
            $profile->overall_progress         = $progressPct;

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
            'avatar'                => $p->user?->avatar
                ? $this->avatarService->generateAvatarUrl($p->user)
                : null,
            'points'                => $p->points,
            'enrolled_tracks_count' => $p->enrolled_tracks_count,
            'completed_tracks_count' => $p->completed_tracks_count,
            'in_progress_tracks_count' => $p->in_progress_tracks_count,
            'overall_progress'      => $p->overall_progress,
        ]);

        return $this->success([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => (int) ceil($total / $perPage),
            ],
        ], 'Student profiles retrieved successfully.');
    }

    /**
     * Detail progress for a specific profile across their enrolled tracks.
     * Supports: status filter (in_progress|completed), sort (progress_desc|progress_asc|title_asc).
     */
    public function profileDetail(Request $request, Profile $profile): JsonResponse
    {
        $statusFilter = $request->get('status');
        $sort         = $request->get('sort', 'title_asc');

        $profile->loadMissing('user');

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
                'avatar'       => $profile->user?->avatar
                    ? $this->avatarService->generateAvatarUrl($profile->user)
                    : null,
                'points'       => $profile->points,
            ],
            'tracks' => $tracks,
        ], 'Profile progress retrieved successfully.');
    }

    /**
     * List tracks filtered by enrollment status.
     * Supports: enrolled (true|false), search, sort (avg_progress_desc|avg_progress_asc|enrolled_desc|title_asc), page, per_page.
     */
    public function tracks(Request $request): JsonResponse
    {
        $search   = $request->get('search');
        $sort     = $request->get('sort', 'title_asc');
        $perPage  = max(1, min((int) $request->get('per_page', 15), 100));
        $enrolled = $request->get('enrolled');

        $query = Track::withCount(['enrollments as enrolled_count'])
            ->with(['modules.lessons', 'enrollments']);

        if ($enrolled === 'false') {
            $query->doesntHave('enrollments');
            $responseMessage = 'Unenrolled tracks retrieved successfully.';
        } else {
            $query->has('enrollments');
            $responseMessage = 'Tracks retrieved successfully.';
        }

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

        return $this->success([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => (int) ceil($total / $perPage),
            ],
        ], $responseMessage);
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

        // Batch-load certificates for this track so we avoid N+1 queries
        $certificateMap = Certificate::where('track_id', $track->id)
            ->whereIn('profile_id', $profileIds)
            ->get()
            ->keyBy('profile_id');

        $profiles = $enrollments->map(function ($enrollment) use ($totalLessons, $completedMap, $certificateMap) {
            $profile     = $enrollment->profile;
            $completed   = $completedMap[$profile->id] ?? 0;
            $progressPct = $totalLessons > 0 ? round(($completed / $totalLessons) * 100) : 0;

            $cert = $certificateMap->get($profile->id);
            $certificateStatus = $cert ? [
                'grade'              => $cert->grade,
                'grade_score'        => $cert->grade_score,
                'status'             => $cert->status,
                'issued_at'          => $cert->issued_at,
                'certificate_number' => $cert->certificate_number,
            ] : null;

            return [
                'id'                  => $profile->id,
                'display_name'        => $profile->display_name ?? $profile->user?->name ?? 'Unknown',
                'avatar'              => $profile->user?->avatar
                    ? $this->avatarService->generateAvatarUrl($profile->user)
                    : null,
                'points'              => $profile->points,
                'completed_lessons'   => $completed,
                'total_lessons'       => $totalLessons,
                'progress_percentage' => $progressPct,
                'status'              => $enrollment->status === 'completed' ? 'completed' : 'in_progress',
                'enrolled_at'         => $enrollment->enrolled_at,
                'certificate_status'  => $certificateStatus,
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
