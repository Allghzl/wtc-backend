<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\Profile;
use App\Models\Submission;
use App\Models\Track;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TeacherDashboardService
{
    /**
     * Aggregate teacher dashboard data: stats, pending queue preview, leaderboard preview.
     */
    public function dashboard(): array
    {
        $totalSubmissions = Submission::count();
        $pendingCount     = Submission::where('status', 'submitted')->count();
        $gradedCount      = Submission::where('status', 'graded')->count();
        $totalStudents    = Profile::whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['admin', 'teacher']))->count();
        $totalChallenges  = Challenge::count();
        $totalTracks      = Track::count();
        $totalLessons     = Lesson::count();

        // Latest pending submissions (oldest first — most urgent for grading)
        $pendingQueue = Submission::with([
            'challenge:id,title,slug,type,max_score',
            'profile:id,display_name',
        ])
            ->where('status', 'submitted')
            ->orderBy('submitted_at', 'asc')
            ->limit(10)
            ->get();

        // Top 5 students by points for leaderboard preview (exclude admins and teachers)
        $leaderboard = Profile::whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['admin', 'teacher']))
            ->orderBy('points', 'desc')
            ->limit(5)
            ->get(['id', 'display_name', 'points']);

        return [
            'stats' => [
                'total_submissions'   => $totalSubmissions,
                'pending_submissions' => $pendingCount,
                'graded_submissions'  => $gradedCount,
                'total_students'      => $totalStudents,
                'total_challenges'    => $totalChallenges,
                'total_tracks'        => $totalTracks,
                'total_lessons'       => $totalLessons,
            ],
            'pending_submissions' => $pendingQueue,
            'leaderboard'         => $leaderboard,
        ];
    }

    /**
     * Paginated submission list with optional filters.
     *
     * Accepted filters: status, challenge_id, profile_id, page, per_page.
     */
    public function submissions(array $filters): LengthAwarePaginator
    {
        $query = Submission::with([
            'challenge:id,title,slug,type,max_score',
            'profile:id,display_name',
        ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['challenge_id'])) {
            $query->where('challenge_id', $filters['challenge_id']);
        }

        if (!empty($filters['profile_id'])) {
            $query->where('profile_id', $filters['profile_id']);
        }

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $page    = max((int) ($filters['page'] ?? 1), 1);

        return $query
            ->orderBy('submitted_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
