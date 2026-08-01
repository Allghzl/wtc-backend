<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Track;

class TrackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tracks = [
            [
                'title' => 'Web Development Fundamentals',
                'slug' => 'web-dev-fundamentals',
                'description' => 'Learn the basics of HTML, CSS, and JavaScript to build modern websites.',
                'order' => 1,
                'image_url' => 'https://via.placeholder.com/400x300/4A90E2/FFFFFF?text=Web+Dev',
            ],
            [
                'title' => 'Backend Development with Laravel',
                'slug' => 'backend-laravel',
                'description' => 'Master backend development using Laravel framework and build robust APIs.',
                'order' => 2,
                'image_url' => 'https://via.placeholder.com/400x300/F55247/FFFFFF?text=Laravel',
            ],
            [
                'title' => 'Frontend Development with React',
                'slug' => 'frontend-react',
                'description' => 'Build interactive user interfaces with React and modern JavaScript.',
                'order' => 3,
                'image_url' => 'https://via.placeholder.com/400x300/61DAFB/FFFFFF?text=React',
            ],
            [
                'title' => 'Database Design & SQL',
                'slug' => 'database-sql',
                'description' => 'Learn database design principles and master SQL queries.',
                'order' => 4,
                'image_url' => 'https://via.placeholder.com/400x300/336791/FFFFFF?text=SQL',
            ],
            [
                'title' => 'DevOps Essentials',
                'slug' => 'devops-essentials',
                'description' => 'Learn CI/CD, Docker, and deployment strategies for modern applications.',
                'order' => 5,
                'image_url' => 'https://via.placeholder.com/400x300/2496ED/FFFFFF?text=DevOps',
            ],
        ];

        foreach ($tracks as $track) {
            Track::create($track);
        }

        $this->command->info('✅ Tracks seeded: ' . count($tracks));
    }
}
