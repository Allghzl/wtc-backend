<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Profile;
use App\Models\Submission;
use App\Models\Track;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProgressService
{
    public function __construct(
        protected LessonCompletionService $lessonCompletionService
    ) {}

    /**
     * Check if challenge is completed by profile.
     */
    public function isChallengeCompleted(Challenge $challenge, Profile $profile): bool
    {
        return Submission::where('profile_id', $profile->id)
            ->where('challenge_id', $challenge->id)
            ->whereIn('status', ['graded', 'reviewed'])
            ->exists();
    }

    /**
     * Batch check challenge completions for multiple challenges.
     * Returns array keyed by challenge_id with boolean values.
     */
    protected function areChallengesCompleted(Collection $challenges, Profile $profile): array
    {
        if ($challenges->isEmpty()) {
            return [];
        }

        $challengeIds = $challenges->pluck('id');

        // Batch query all completed submissions
        $completedChallengeIds = Submission::where('profile_id', $profile->id)
            ->whereIn('challenge_id', $challengeIds)
            ->whereIn('status', ['graded', 'reviewed'])
            ->distinct()
            ->pluck('challenge_id');

        // Convert to array keyed by challenge_id
        $results = [];
        foreach ($challengeIds as $challengeId) {
            $results[$challengeId] = $completedChallengeIds->contains($challengeId);
        }

        return $results;
    }

    /**
     * Calculate track progress for a profile.
     */
    public function getTrackProgress(Track $track, Profile $profile): array
    {
        $modules = $track->modules()
            ->with(['lessons.challenges', 'challenges'])
            ->orderBy('order')
            ->get();

        $totalModules = $modules->count();
        $completedModules = 0;
        $totalChallenges = 0;
        $completedChallenges = 0;
        $totalLessons = 0;
        $completedLessons = 0;

        $moduleProgress = [];

        // Collect all lessons and challenges for batch checking
        $allLessons = collect();
        $allChallenges = collect();

        foreach ($modules as $module) {
            $moduleLessons = $module->lessons;
            $allLessons = $allLessons->concat($moduleLessons);

            foreach ($moduleLessons as $lesson) {
                $allChallenges = $allChallenges->concat($lesson->challenges);
            }

            // Direct module challenges
            $directChallenges = $module->challenges()->whereNull('lesson_id')->get();
            $allChallenges = $allChallenges->concat($directChallenges);
        }

        // Batch check all lesson completions
        $lessonCompletions = $this->lessonCompletionService->areLessonsCompleted(
            $allLessons->pluck('id')->toArray(),
            $profile
        );

        // Batch check all challenge completions
        $challengeCompletions = $this->areChallengesCompleted($allChallenges, $profile);

        // Initialize accumulator variables for aggregation
        $totalChallenges = 0;
        $completedChallenges = 0;
        $totalLessons = 0;
        $completedLessons = 0;
        $totalDirectChallenges = 0;
        $completedDirectChallenges = 0;
        $completedModules = 0;

        // Calculate progress per module
        foreach ($modules as $module) {
            $progress = $this->getModuleProgress($module, $profile, $lessonCompletions, $challengeCompletions);
            $moduleProgress[] = $progress;

            $totalChallenges += $progress['total_challenges'];
            $completedChallenges += $progress['completed_challenges'];
            $totalLessons += $progress['total_lessons'];
            $completedLessons += $progress['completed_lessons'];
            $totalDirectChallenges += $progress['total_direct_challenges'] ?? 0;
            $completedDirectChallenges += $progress['completed_direct_challenges'] ?? 0;

            // Module is completed if it has content AND all content is completed
            if ($progress['has_content'] && $progress['percent'] >= 100) {
                $completedModules++;
            }
        }

        // Calculate overall percentage using CORRECTED FORMULA
        // Progress items = lessons + direct module challenges (NOT challenges inside lessons)
        $totalItems = $totalLessons + $totalDirectChallenges;
        $completedItems = $completedLessons + $completedDirectChallenges;

        $overallPercent = $totalItems > 0
            ? round(($completedItems / $totalItems) * 100)
            : 0;

        return [
            'percent' => $overallPercent,
            'completed_modules' => $completedModules,
            'total_modules' => $totalModules,
            'completed_challenges' => $completedChallenges,
            'total_challenges' => $totalChallenges,
            'completed_lessons' => $completedLessons,
            'total_lessons' => $totalLessons,
            'modules' => $moduleProgress,
        ];
    }

    /**
     * Calculate module progress for a profile.
     */
    public function getModuleProgress(
        Module $module,
        Profile $profile,
        ?array $lessonCompletions = null,
        ?array $challengeCompletions = null
    ): array {
        $lessons = $module->lessons;
        $directChallenges = $module->challenges()->whereNull('lesson_id')->get();

        // If batch data not provided, calculate individually
        if ($lessonCompletions === null) {
            $lessonCompletions = $this->lessonCompletionService->areLessonsCompleted(
                $lessons->pluck('id')->toArray(),
                $profile
            );
        }

        if ($challengeCompletions === null) {
            $allChallenges = collect();
            foreach ($lessons as $lesson) {
                $allChallenges = $allChallenges->concat($lesson->challenges);
            }
            $allChallenges = $allChallenges->concat($directChallenges);
            $challengeCompletions = $this->areChallengesCompleted($allChallenges, $profile);
        }

        $totalLessons = 0;
        $completedLessons = 0;
        $totalChallenges = 0;
        $completedChallenges = 0;
        $totalDirectChallenges = 0;
        $completedDirectChallenges = 0;

        // Count lessons
        foreach ($lessons as $lesson) {
            $lessonChallenges = $lesson->challenges;
            $challengeCount = $lessonChallenges->count();

            // Every lesson counts as ONE progress item (regardless of challenge count)
            $totalLessons++;

            if ($challengeCount === 0) {
                // Lesson without challenges - check explicit completion
                if ($lessonCompletions[$lesson->id] ?? false) {
                    $completedLessons++;
                }
            } else {
                // Lesson with challenges - check if ALL challenges are complete
                $allChallengesComplete = true;
                foreach ($lessonChallenges as $challenge) {
                    // Track challenge metrics (informational only)
                    $totalChallenges++;
                    if ($challengeCompletions[$challenge->id] ?? false) {
                        $completedChallenges++;
                    } else {
                        $allChallengesComplete = false;
                    }
                }

                // Lesson is complete only when ALL its challenges are complete
                if ($allChallengesComplete) {
                    $completedLessons++;
                }
            }
        }

        // Count direct module challenges (these are separate progress items)
        foreach ($directChallenges as $challenge) {
            $totalDirectChallenges++;
            $totalChallenges++; // Also track in total for metrics
            if ($challengeCompletions[$challenge->id] ?? false) {
                $completedDirectChallenges++;
                $completedChallenges++; // Also track in completed for metrics
            }
        }

        // Calculate percentage - CORRECTED FORMULA
        // Progress items = lessons + direct module challenges (NOT challenges inside lessons)
        $totalItems = $totalLessons + $totalDirectChallenges;
        $completedItems = $completedLessons + $completedDirectChallenges;

        $percent = $totalItems > 0
            ? round(($completedItems / $totalItems) * 100)
            : 0;

        return [
            'module_id' => $module->id,
            'module_title' => $module->title,
            'percent' => $percent,
            'completed_challenges' => $completedChallenges,
            'total_challenges' => $totalChallenges,
            'completed_lessons' => $completedLessons,
            'total_lessons' => $totalLessons,
            'completed_direct_challenges' => $completedDirectChallenges,
            'total_direct_challenges' => $totalDirectChallenges,
            'has_content' => $totalItems > 0,
        ];
    }

    /**
     * Calculate lesson progress for a profile.
     */
    public function getLessonProgress(Lesson $lesson, Profile $profile): array
    {
        $challenges = $lesson->challenges;
        $totalChallenges = $challenges->count();

        if ($totalChallenges === 0) {
            // Lesson without challenges - check explicit completion
            $isCompleted = $this->lessonCompletionService->isLessonCompleted($lesson, $profile);

            return [
                'lesson_id' => $lesson->id,
                'lesson_title' => $lesson->title,
                'percent' => $isCompleted ? 100 : 0,
                'completed_challenges' => 0,
                'total_challenges' => 0,
                'is_completed' => $isCompleted,
                'completion_type' => 'explicit',
            ];
        }

        // Lesson with challenges
        $challengeCompletions = $this->areChallengesCompleted($challenges, $profile);
        $completedChallenges = count(array_filter($challengeCompletions));

        $percent = round(($completedChallenges / $totalChallenges) * 100);

        return [
            'lesson_id' => $lesson->id,
            'lesson_title' => $lesson->title,
            'percent' => $percent,
            'completed_challenges' => $completedChallenges,
            'total_challenges' => $totalChallenges,
            'is_completed' => $percent >= 100,
            'completion_type' => 'challenge_based',
        ];
    }
}
