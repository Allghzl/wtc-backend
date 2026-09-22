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
        // Combine three submission COUNTs into one query
        $submissionStats = \Illuminate\Support\Facades\DB::selectOne('
            SELECT
                COUNT(*) AS total,
                SUM(status = ?) AS pending,
                SUM(status = ?) AS graded
            FROM submissions
        ', ['submitted', 'graded']);

        // Cache the four entity counts — they change infrequently
        $entityStats = \Illuminate\Support\Facades\Cache::remember('teacher_dashboard_entity_stats', 60, function () {
            return [
                'total_students'   => Profile::whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['admin', 'teacher']))->count(),
                'total_challenges' => Challenge::count(),
                'total_tracks'     => Track::count(),
                'total_lessons'    => Lesson::count(),
            ];
        });

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
        $leaderboard = Profile::with('user')
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['admin', 'teacher']))
            ->orderBy('points', 'desc')
            ->limit(5)
            ->get(['id', 'user_id', 'display_name', 'points']);

        return [
            'stats' => [
                'total_submissions'   => (int) $submissionStats->total,
                'pending_submissions' => (int) $submissionStats->pending,
                'graded_submissions'  => (int) $submissionStats->graded,
                'total_students'      => $entityStats['total_students'],
                'total_challenges'    => $entityStats['total_challenges'],
                'total_tracks'        => $entityStats['total_tracks'],
                'total_lessons'       => $entityStats['total_lessons'],
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

        if (!empty($filters['search'])) {
            $query->whereHas('profile', fn ($q) => $q->where('display_name', 'like', '%' . $filters['search'] . '%'));
        }

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
