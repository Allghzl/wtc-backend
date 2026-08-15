<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Profile;
use App\Models\Submission;
use App\Models\Track;
use Illuminate\Support\Collection;

class ProgressService
{
    /**
     * Check if challenge is completed by profile
     */
    public function isChallengeCompleted(Challenge $challenge, Profile $profile): bool
    {
        return Submission::where('profile_id', $profile->id)
            ->where('challenge_id', $challenge->id)
            ->whereIn('status', ['graded', 'reviewed'])
            ->exists();
    }

    /**
     * Calculate track progress for a profile
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

        $moduleProgress = [];

        foreach ($modules as $module) {
            $progress = $this->getModuleProgress($module, $profile);
            $moduleProgress[] = $progress;

            $totalChallenges += $progress['total_challenges'];
            $completedChallenges += $progress['completed_challenges'];

            // Module is completed only if it has challenges AND all are completed
            if ($progress['total_challenges'] > 0 && $progress['percent'] >= 100) {
                $completedModules++;
            }
        }

        $overallPercent = $totalChallenges > 0
            ? round(($completedChallenges / $totalChallenges) * 100)
            : 0;

        return [
            'percent' => $overallPercent,
            'completed_modules' => $completedModules,
            'total_modules' => $totalModules,
            'completed_challenges' => $completedChallenges,
            'total_challenges' => $totalChallenges,
            'modules' => $moduleProgress,
        ];
    }

    /**
     * Calculate module progress for a profile
     */
    public function getModuleProgress(Module $module, Profile $profile): array
    {
        // Get all challenges for this module
        $allChallenges = $this->getModuleChallenges($module);

        $totalChallenges = $allChallenges->count();
        $completedChallenges = 0;

        // Check completion for each challenge
        foreach ($allChallenges as $challenge) {
            if ($this->isChallengeCompleted($challenge, $profile)) {
                $completedChallenges++;
            }
        }

        $percent = $totalChallenges > 0
            ? round(($completedChallenges / $totalChallenges) * 100)
            : 0;

        return [
            'module_id' => $module->id,
            'module_title' => $module->title,
            'percent' => $percent,
            'completed_challenges' => $completedChallenges,
            'total_challenges' => $totalChallenges,
        ];
    }

    /**
     * Get all challenges for a module (direct + lesson challenges)
     */
    private function getModuleChallenges(Module $module): Collection
    {
        // Direct module challenges (no lesson_id)
        $directChallenges = $module->challenges()
            ->whereNull('lesson_id')
            ->get();

        // Lesson challenges
        $lessonChallenges = Challenge::whereIn(
            'lesson_id',
            $module->lessons()->pluck('id')
        )->get();

        return $directChallenges->concat($lessonChallenges);
    }

    /**
     * Calculate lesson progress for a profile
     */
    public function getLessonProgress(Lesson $lesson, Profile $profile): array
    {
        $challenges = $lesson->challenges;
        $totalChallenges = $challenges->count();
        $completedChallenges = 0;

        foreach ($challenges as $challenge) {
            if ($this->isChallengeCompleted($challenge, $profile)) {
                $completedChallenges++;
            }
        }

        $percent = $totalChallenges > 0
            ? round(($completedChallenges / $totalChallenges) * 100)
            : 0;

        return [
            'lesson_id' => $lesson->id,
            'lesson_title' => $lesson->title,
            'percent' => $percent,
            'completed_challenges' => $completedChallenges,
            'total_challenges' => $totalChallenges,
        ];
    }
}
