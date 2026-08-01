<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Core data (no dependencies)
            RoleSeeder::class,
            AchievementSeeder::class,
            StudyClassSeeder::class,

            // Learning content (has dependencies)
            TrackSeeder::class,
            ModuleSeeder::class,
            LessonSeeder::class,
            ChallengeSeeder::class,
        ]);

        $this->command->info('🎉 LMS content seeded successfully!');
        $this->command->info('');
        $this->command->info('📊 Database Summary:');
        $this->command->info('  ✅ Roles, Achievements, Study Classes');
        $this->command->info('  ✅ Tracks, Modules, Lessons');
        $this->command->info('  ✅ Challenges (module & lesson level)');
        $this->command->info('');
        $this->command->info('🚀 Ready to use!');
    }
}
