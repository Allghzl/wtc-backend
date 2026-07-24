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
            StudyClassSeeder::class,
            UserSeeder::class,
            TrackSeeder::class,
            ModuleSeeder::class,
            LessonSeeder::class,
            ChallengeSeeder::class,
            SubmissionSeeder::class,
        ]);
    }
}
