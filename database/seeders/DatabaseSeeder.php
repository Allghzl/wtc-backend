<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * SEEDING ORDER (dependency-aware):
     * 1. Core/Reference Data (no dependencies)
     * 2. User/Profile/Role Data
     * 3. Learning Content Hierarchy
     * 4. Submission Data
     */
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('🌱 Starting database seeding...');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->newLine();

        $this->call([
            // ==========================================
            // PHASE 1: Core/Reference Data
            // ==========================================
            RoleSeeder::class,          // 3 roles: admin, teacher, student
            AchievementSeeder::class,   // 12 achievements
            StudyClassSeeder::class,    // 12 SMK RPL classes (X/XI/XII RPL 1-4)

            // ==========================================
            // PHASE 2: User & Profile Data
            // ==========================================
            UserSeeder::class,          // ~180 users with Indonesian names
            ProfileSeeder::class,       // 1:1 profiles for all users
            ProfileRoleSeeder::class,   // Assign roles + study classes to profiles

            // ==========================================
            // PHASE 3: Learning Content Hierarchy
            // ==========================================
            TrackSeeder::class,         // 10 tracks (web tech focus)
            ModuleSeeder::class,        // ~70 modules (6-8 per track)
            LessonSeeder::class,        // ~250-350 lessons (3-5 per module, dynamic)
            ChallengeSeeder::class,     // ~500-750 challenges (2-3 per lesson)

            // ==========================================
            // PHASE 4: Submission Data
            // ==========================================
            SubmissionSeeder::class,    // ~2000-4000 submissions (varied participation)
        ]);

        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->newLine();
        $this->command->info('📊 FINAL SUMMARY:');
        $this->command->info('   👥 Users: ~180 (3 admins, 12 teachers, ~165 students)');
        $this->command->info('   📚 Study Classes: 12 (X/XI/XII RPL with 4 parallel classes each)');
        $this->command->info('   🎯 Tracks: 10');
        $this->command->info('   📖 Modules: ~70');
        $this->command->info('   📝 Lessons: ~250-350');
        $this->command->info('   🏆 Challenges: ~500-750');
        $this->command->info('   ✍️  Submissions: ~2000-4000');
        $this->command->newLine();
        $this->command->info('🚀 Ready for development and testing!');
        $this->command->info('   Run: php artisan migrate:fresh --seed');
        $this->command->info('');
    }
}
