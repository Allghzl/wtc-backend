<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Achievement;

class AchievementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $achievements = [
            [
                'name' => 'First Steps',
                'description' => 'Complete your first lesson',
            ],
            [
                'name' => 'Challenge Accepted',
                'description' => 'Submit your first challenge',
            ],
            [
                'name' => 'Perfect Score',
                'description' => 'Get 100% on any challenge',
            ],
            [
                'name' => 'Fast Learner',
                'description' => 'Complete 5 lessons in one day',
            ],
            [
                'name' => 'Module Master',
                'description' => 'Complete all lessons in a module',
            ],
            [
                'name' => 'Track Champion',
                'description' => 'Complete an entire track',
            ],
            [
                'name' => 'Night Owl',
                'description' => 'Submit a challenge between 12 AM - 6 AM',
            ],
            [
                'name' => 'Early Bird',
                'description' => 'Complete a lesson before 7 AM',
            ],
            [
                'name' => 'Streak Master',
                'description' => 'Study 7 days in a row',
            ],
            [
                'name' => 'Challenge Crusher',
                'description' => 'Complete 10 challenges',
            ],
            [
                'name' => 'Point Collector',
                'description' => 'Earn 1000 points',
            ],
            [
                'name' => 'Helping Hand',
                'description' => 'Help 5 other students',
            ],
        ];

        foreach ($achievements as $achievement) {
            Achievement::create($achievement);
        }

        $this->command->info('✅ Achievements seeded: ' . count($achievements));
    }
}
