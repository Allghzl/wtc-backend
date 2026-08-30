<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Submission;
use App\Models\Track;

class GradeCalculatorService
{
    /**
     * Calculate the overall grade percentage for a profile on a track.
     *
     * Strategy: collect all challenges across every module in the track,
     * find the best graded/reviewed submission per challenge for this profile,
     * then return the average score as a percentage of max_score.
     *
     * Returns a float between 0 and 100. Returns 0 when no graded submissions exist.
     */
    public function calculateTrackGrade(Profile $profile, Track $track): float
    {
        // Load all modules with their challenges eagerly
        $track->loadMissing('modules.challenges');

        $challenges = $track->modules->flatMap(fn ($module) => $module->challenges);

        if ($challenges->isEmpty()) {
            return 0.0;
        }

        $challengeIds = $challenges->pluck('id');

        // Get best submission (highest total score) per challenge for this profile
        // Only consider graded or reviewed submissions
        $bestSubmissions = Submission::where('profile_id', $profile->id)
            ->whereIn('challenge_id', $challengeIds)
            ->whereIn('status', ['graded', 'reviewed'])
            ->get()
            ->groupBy('challenge_id')
            ->map(function ($submissions) {
                // Pick the submission with the highest combined score
                return $submissions->sortByDesc(function ($s) {
                    return ($s->auto_score ?? 0) + ($s->manual_score ?? 0);
                })->first();
            });

        if ($bestSubmissions->isEmpty()) {
            return 0.0;
        }

        // Build a keyed map of challenge max_scores
        $maxScores = $challenges->keyBy('id')->map(fn ($c) => $c->max_score ?: 100);

        $totalPercentage = 0.0;
        $count           = 0;

        foreach ($bestSubmissions as $challengeId => $submission) {
            $maxScore = $maxScores->get($challengeId, 100);
            $score    = ($submission->auto_score ?? 0) + ($submission->manual_score ?? 0);

            // Clamp to 0-100 range
            $percentage       = $maxScore > 0 ? min(100, ($score / $maxScore) * 100) : 0;
            $totalPercentage += $percentage;
            $count++;
        }

        return $count > 0 ? round($totalPercentage / $count, 2) : 0.0;
    }
}
