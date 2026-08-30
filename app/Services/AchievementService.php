<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AchievementService
{
    /**
     * Check all active achievements of a given trigger_type and award any
     * that the profile has not yet earned.
     *
     * @param  array<string, mixed>  $context  Extra data for future condition checks
     */
    public function checkAndAward(Profile $profile, string $triggerType, array $context = []): void
    {
        $achievements = Achievement::where('trigger_type', $triggerType)
            ->where('is_active', true)
            ->get();

        if ($achievements->isEmpty()) {
            return;
        }

        // IDs already earned by this profile
        $alreadyEarned = $profile->achievements()
            ->pluck('achievement_id')
            ->flip();

        foreach ($achievements as $achievement) {
            if ($alreadyEarned->has($achievement->id)) {
                continue;
            }

            if (!$this->conditionsMet($achievement, $profile, $context)) {
                continue;
            }

            DB::transaction(function () use ($profile, $achievement) {
                // Create the pivot record
                $profile->achievements()->attach($achievement->id, [
                    'is_pinned'  => false,
                    'awarded_at' => now(),
                ]);

                // Add points to profile
                if ($achievement->points_reward > 0) {
                    $profile->increment('points', $achievement->points_reward);
                }
            });

            Log::info('Achievement awarded', [
                'profile_id'     => $profile->id,
                'achievement_id' => $achievement->id,
                'trigger_type'   => $triggerType,
            ]);
        }
    }

    /**
     * Evaluate any trigger_config conditions against the context.
     * Currently a permissive pass-through — extend per trigger as needed.
     */
    private function conditionsMet(Achievement $achievement, Profile $profile, array $context): bool
    {
        $config = $achievement->trigger_config;

        if (empty($config)) {
            return true;
        }

        // Example: points_milestone trigger — require profile points >= threshold
        if ($achievement->trigger_type === 'points_milestone') {
            $threshold = $config['threshold'] ?? 0;
            return $profile->points >= $threshold;
        }

        // Example: track_complete with a specific track_id requirement
        if ($achievement->trigger_type === 'track_complete' && isset($config['track_id'])) {
            return ($context['track_id'] ?? null) == $config['track_id'];
        }

        return true;
    }
}
