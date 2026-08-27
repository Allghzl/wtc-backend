<?php

namespace App\Services;

use App\Models\PointLog;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

class PointService
{
    /**
     * Add points to a profile.
     *
     * @param Profile $profile
     * @param int $points
     * @param string $reason
     * @param array $metadata Additional context (e.g., lesson_id, submission_id)
     * @return PointLog
     */
    public function addPoints(Profile $profile, int $points, string $reason, array $metadata = []): PointLog
    {
        return DB::transaction(function () use ($profile, $points, $reason, $metadata) {
            // Update profile points
            $profile->increment('points', $points);
            $profile->refresh();

            // Log the point change
            return PointLog::create([
                'profile_id' => $profile->id,
                'points' => $points,
                'reason' => $reason,
                'metadata' => $metadata,
                'balance_after' => $profile->points,
            ]);
        });
    }

    /**
     * Subtract points from a profile (won't go below 0).
     *
     * @param Profile $profile
     * @param int $points
     * @param string $reason
     * @param array $metadata
     * @return PointLog
     */
    public function subtractPoints(Profile $profile, int $points, string $reason, array $metadata = []): PointLog
    {
        return DB::transaction(function () use ($profile, $points, $reason, $metadata) {
            // Calculate new points (don't go below 0)
            $currentPoints = $profile->points;
            $pointsToSubtract = min($points, $currentPoints);
            $newPoints = max(0, $currentPoints - $points);

            // Update profile points
            $profile->update(['points' => $newPoints]);
            $profile->refresh();

            // Log the point change (negative value)
            return PointLog::create([
                'profile_id' => $profile->id,
                'points' => -$pointsToSubtract,
                'reason' => $reason,
                'metadata' => $metadata,
                'balance_after' => $profile->points,
            ]);
        });
    }

    /**
     * Get current points for a profile.
     *
     * @param Profile $profile
     * @return int
     */
    public function getPoints(Profile $profile): int
    {
        return $profile->points;
    }

    /**
     * Get point history for a profile.
     *
     * @param Profile $profile
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPointHistory(Profile $profile, int $limit = 50)
    {
        return PointLog::where('profile_id', $profile->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Award points for lesson completion.
     *
     * @param Profile $profile
     * @param string $lessonId
     * @param string $lessonTitle
     * @return PointLog|null
     */
    public function awardLessonCompletionPoints(Profile $profile, string $lessonId, string $lessonTitle): ?PointLog
    {
        // Check if points were already awarded for this lesson
        $existingLog = PointLog::where('profile_id', $profile->id)
            ->where('reason', 'lesson_completion')
            ->whereJsonContains('metadata->lesson_id', $lessonId)
            ->first();

        if ($existingLog) {
            // Points already awarded, don't award again
            return null;
        }

        // Award points
        return $this->addPoints(
            $profile,
            10, // 10 points per lesson
            'lesson_completion',
            [
                'lesson_id' => $lessonId,
                'lesson_title' => $lessonTitle,
            ]
        );
    }

    /**
     * Award points for submission grading.
     *
     * @param Profile $profile
     * @param int $submissionId
     * @param int $score Total score (auto + manual)
     * @param int $maxScore Maximum possible score
     * @param string $challengeTitle
     * @return PointLog|null
     */
    public function awardSubmissionPoints(
        Profile $profile,
        int $submissionId,
        int $score,
        int $maxScore,
        string $challengeTitle
    ): ?PointLog {
        // Check if points were already awarded for this submission
        $existingLog = PointLog::where('profile_id', $profile->id)
            ->where('reason', 'submission_graded')
            ->whereJsonContains('metadata->submission_id', $submissionId)
            ->first();

        if ($existingLog) {
            // Points already awarded, don't award again
            return null;
        }

        // Calculate points based on score percentage
        // Award between 5-20 points based on performance
        $percentage = $maxScore > 0 ? ($score / $maxScore) : 0;
        $points = max(5, min(20, (int) round($percentage * 20)));

        // Award points
        return $this->addPoints(
            $profile,
            $points,
            'submission_graded',
            [
                'submission_id' => $submissionId,
                'challenge_title' => $challengeTitle,
                'score' => $score,
                'max_score' => $maxScore,
                'percentage' => round($percentage * 100, 2),
            ]
        );
    }
}
