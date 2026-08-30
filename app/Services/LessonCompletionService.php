<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

class LessonCompletionService
{
    /**
     * Mark a lesson as completed for the given profile.
     * This is idempotent - calling multiple times returns the same completion.
     */
    public function markAsComplete(Lesson $lesson, Profile $profile): LessonCompletion
    {
        // Check if already completed
        $existing = LessonCompletion::where('profile_id', $profile->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Create new completion
        return LessonCompletion::create([
            'profile_id' => $profile->id,
            'lesson_id' => $lesson->id,
            'completed_at' => now(),
        ]);
    }

    /**
     * Check if a lesson is completed by the profile.
     * For lessons with challenges, this checks challenge completion.
     * For lessons without challenges, this checks lesson_completions.
     */
    public function isLessonCompleted(Lesson $lesson, Profile $profile): bool
    {
        $challengeCount = $lesson->challenges()->count();

        // Lesson without challenges - check explicit completion
        if ($challengeCount === 0) {
            return LessonCompletion::where('profile_id', $profile->id)
                ->where('lesson_id', $lesson->id)
                ->exists();
        }

        // Lesson with challenges - check if all challenges are completed
        $challengeIds = $lesson->challenges()->pluck('id');

        $completedChallenges = DB::table('submissions')
            ->where('profile_id', $profile->id)
            ->whereIn('challenge_id', $challengeIds)
            ->whereIn('status', ['graded', 'reviewed'])
            ->distinct('challenge_id')
            ->count('challenge_id');

        return $completedChallenges === $challengeCount;
    }

    /**
     * Get the lesson completion record for a profile, if it exists.
     */
    public function getLessonCompletion(Lesson $lesson, Profile $profile): ?LessonCompletion
    {
        return LessonCompletion::where('profile_id', $profile->id)
            ->where('lesson_id', $lesson->id)
            ->first();
    }

    /**
     * Batch check lesson completions for multiple lessons.
     * Returns array keyed by lesson_id with boolean values.
     */
    public function areLessonsCompleted(array $lessonIds, Profile $profile): array
    {
        // Get all lessons with their challenge counts
        $lessons = Lesson::whereIn('id', $lessonIds)
            ->withCount('challenges')
            ->get()
            ->keyBy('id');

        // Get explicit completions for lessons without challenges
        $lessonsWithoutChallenges = $lessons->filter(fn($l) => $l->challenges_count === 0)
            ->pluck('id');

        $explicitCompletions = LessonCompletion::where('profile_id', $profile->id)
            ->whereIn('lesson_id', $lessonsWithoutChallenges)
            ->pluck('lesson_id')
            ->flip();

        // Get challenge-based completions for lessons with challenges
        $lessonsWithChallenges = $lessons->filter(fn($l) => $l->challenges_count > 0);

        $challengeCompletions = [];
        foreach ($lessonsWithChallenges as $lesson) {
            $challengeIds = $lesson->challenges()->pluck('id');

            $completedCount = DB::table('submissions')
                ->where('profile_id', $profile->id)
                ->whereIn('challenge_id', $challengeIds)
                ->whereIn('status', ['graded', 'reviewed'])
                ->distinct('challenge_id')
                ->count('challenge_id');

            $challengeCompletions[$lesson->id] = $completedCount === $lesson->challenges_count;
        }

        // Combine results
        $results = [];
        foreach ($lessonIds as $lessonId) {
            $lesson = $lessons->get($lessonId);
            if (!$lesson) {
                $results[$lessonId] = false;
                continue;
            }

            if ($lesson->challenges_count === 0) {
                $results[$lessonId] = isset($explicitCompletions[$lessonId]);
            } else {
                $results[$lessonId] = $challengeCompletions[$lessonId] ?? false;
            }
        }

        return $results;
    }
}
