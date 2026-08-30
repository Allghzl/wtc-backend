<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\TrackEnrollment;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        protected ProgressService $progressService,
        protected LearningStateService $learningStateService
    ) {}

    /**
     * Get complete student dashboard data.
     */
    public function getStudentDashboard(Profile $profile): array
    {
        // Get active enrollments with tracks
        $activeEnrollments = $profile->trackEnrollments()
            ->where('status', 'active')
            ->with(['track.modules'])
            ->orderBy('enrolled_at', 'desc')
            ->get();

        // Get completed enrollments count
        $completedEnrollmentsCount = $profile->trackEnrollments()
            ->where('status', 'completed')
            ->count();

        // Calculate stats
        $stats = $this->getStats($profile, $activeEnrollments);

        // Get continue learning information
        $continueLearning = $this->learningStateService->getContinueLearning($profile);

        // Get tracks with progress
        $tracks = $this->getTracksWithProgress($activeEnrollments, $profile);

        return [
            'profile' => [
                'id' => $profile->id,
                'display_name' => $profile->display_name,
                'nickname' => $profile->nickname,
                'points' => $profile->points,
                'study_class' => $profile->studyClass ? [
                    'id' => $profile->studyClass->id,
                    'name' => $profile->studyClass->name,
                ] : null,
            ],
            'stats' => $stats,
            'tracks' => $tracks,
            'continue_learning' => $continueLearning ? [
                'track' => [
                    'id' => $continueLearning['track']->id,
                    'title' => $continueLearning['track']->title,
                    'slug' => $continueLearning['track']->slug,
                ],
                'module' => [
                    'id' => $continueLearning['module']->id,
                    'title' => $continueLearning['module']->title,
                    'slug' => $continueLearning['module']->slug,
                ],
                'lesson' => [
                    'id' => $continueLearning['lesson']->id,
                    'title' => $continueLearning['lesson']->title,
                    'slug' => $continueLearning['lesson']->slug,
                ],
            ] : null,
        ];
    }

    /**
     * Calculate summary statistics for the student.
     */
    protected function getStats(Profile $profile, Collection $activeEnrollments): array
    {
        $totalCompletedChallenges = 0;
        $totalChallenges = 0;
        $totalCompletedLessons = 0;
        $totalLessons = 0;

        foreach ($activeEnrollments as $enrollment) {
            $track = $enrollment->track;
            $progress = $this->progressService->getTrackProgress($track, $profile);

            $totalCompletedChallenges += $progress['completed_challenges'];
            $totalChallenges += $progress['total_challenges'];
            $totalCompletedLessons += $progress['completed_lessons'];
            $totalLessons += $progress['total_lessons'];
        }

        $completedTracksCount = $profile->trackEnrollments()
            ->where('status', 'completed')
            ->count();

        // Calculate overall progress percentage
        $totalItems = $totalChallenges + $totalLessons;
        $completedItems = $totalCompletedChallenges + $totalCompletedLessons;
        $overallProgress = $totalItems > 0
            ? round(($completedItems / $totalItems) * 100)
            : 0;

        return [
            'active_tracks' => $activeEnrollments->count(),
            'completed_tracks' => $completedTracksCount,
            'total_completed_challenges' => $totalCompletedChallenges,
            'total_challenges' => $totalChallenges,
            'total_completed_lessons' => $totalCompletedLessons,
            'total_lessons' => $totalLessons,
            'overall_progress' => $overallProgress,
        ];
    }

    /**
     * Get tracks with their progress information.
     */
    protected function getTracksWithProgress(Collection $enrollments, Profile $profile): array
    {
        $tracks = [];

        foreach ($enrollments as $enrollment) {
            $track = $enrollment->track;
            $progress = $this->progressService->getTrackProgress($track, $profile);

            $tracks[] = [
                'id' => $track->id,
                'title' => $track->title,
                'slug' => $track->slug,
                'description' => $track->description,
                'image_url' => $track->image_url,
                'enrollment' => [
                    'status' => $enrollment->status,
                    'enrolled_at' => $enrollment->enrolled_at->toISOString(),
                    'completed_at' => $enrollment->completed_at?->toISOString(),
                ],
                'progress' => [
                    'percent' => $progress['percent'],
                    'completed_modules' => $progress['completed_modules'],
                    'total_modules' => $progress['total_modules'],
                    'completed_challenges' => $progress['completed_challenges'],
                    'total_challenges' => $progress['total_challenges'],
                    'completed_lessons' => $progress['completed_lessons'],
                    'total_lessons' => $progress['total_lessons'],
                ],
                'modules_count' => $track->modules->count(),
            ];
        }

        return $tracks;
    }
}
