<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Track;
use App\Models\Module;
use Illuminate\Support\Str;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modulesData = [
            'web-dev-fundamentals' => [
                ['title' => 'HTML Basics', 'order' => 1],
                ['title' => 'CSS Styling', 'order' => 2],
                ['title' => 'JavaScript Fundamentals', 'order' => 3],
                ['title' => 'Responsive Design', 'order' => 4],
            ],
            'backend-laravel' => [
                ['title' => 'Laravel Installation & Setup', 'order' => 1],
                ['title' => 'Routing & Controllers', 'order' => 2],
                ['title' => 'Eloquent ORM', 'order' => 3],
                ['title' => 'Authentication & Authorization', 'order' => 4],
                ['title' => 'RESTful API Development', 'order' => 5],
            ],
            'frontend-react' => [
                ['title' => 'React Basics', 'order' => 1],
                ['title' => 'Components & Props', 'order' => 2],
                ['title' => 'State Management', 'order' => 3],
                ['title' => 'Hooks & Side Effects', 'order' => 4],
            ],
            'database-sql' => [
                ['title' => 'Database Fundamentals', 'order' => 1],
                ['title' => 'SQL Queries', 'order' => 2],
                ['title' => 'Joins & Relationships', 'order' => 3],
                ['title' => 'Optimization & Indexing', 'order' => 4],
            ],
            'devops-essentials' => [
                ['title' => 'Version Control with Git', 'order' => 1],
                ['title' => 'Docker Basics', 'order' => 2],
                ['title' => 'CI/CD Pipelines', 'order' => 3],
                ['title' => 'Cloud Deployment', 'order' => 4],
            ],
        ];

        $totalModules = 0;

        foreach ($modulesData as $trackSlug => $modules) {
            $track = Track::where('slug', $trackSlug)->first();

            if (!$track) {
                $this->command->warn("⚠️  Track not found: {$trackSlug}");
                continue;
            }

            foreach ($modules as $moduleData) {
                Module::create([
                    'track_id' => $track->id,
                    'title' => $moduleData['title'],
                    'slug' => Str::slug($moduleData['title']),
                    'order' => $moduleData['order'],
                ]);
                $totalModules++;
            }
        }

        $this->command->info("✅ Modules seeded: {$totalModules}");
    }
}
