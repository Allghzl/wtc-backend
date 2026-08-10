<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Track;

class TrackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates 10 tracks covering comprehensive web technology learning paths
     * suitable for Indonesian SMK RPL (Software Engineering) curriculum.
     */
    public function run(): void
    {
        $tracks = [
            [
                'title' => 'Web Development Fundamentals',
                'slug' => 'web-dev-fundamentals',
                'description' => 'Pelajari dasar-dasar HTML, CSS, dan JavaScript untuk membangun website modern.',
                'order' => 1,
                'image_url' => 'https://via.placeholder.com/400x300/4A90E2/FFFFFF?text=Web+Dev',
            ],
            [
                'title' => 'Frontend Development with React',
                'slug' => 'frontend-react',
                'description' => 'Bangun user interface yang interaktif dengan React dan JavaScript modern.',
                'order' => 2,
                'image_url' => 'https://via.placeholder.com/400x300/61DAFB/FFFFFF?text=React',
            ],
            [
                'title' => 'Backend Development with Laravel',
                'slug' => 'backend-laravel',
                'description' => 'Kuasai backend development menggunakan Laravel framework dan bangun REST API yang robust.',
                'order' => 3,
                'image_url' => 'https://via.placeholder.com/400x300/F55247/FFFFFF?text=Laravel',
            ],
            [
                'title' => 'Database Design & SQL',
                'slug' => 'database-sql',
                'description' => 'Pelajari prinsip database design dan kuasai SQL query untuk aplikasi web.',
                'order' => 4,
                'image_url' => 'https://via.placeholder.com/400x300/336791/FFFFFF?text=SQL',
            ],
            [
                'title' => 'API Development & Integration',
                'slug' => 'api-development',
                'description' => 'Belajar membangun dan mengintegrasikan REST API untuk aplikasi modern.',
                'order' => 5,
                'image_url' => 'https://via.placeholder.com/400x300/10B981/FFFFFF?text=API',
            ],
            [
                'title' => 'Modern JavaScript & TypeScript',
                'slug' => 'javascript-typescript',
                'description' => 'Kuasai JavaScript ES6+ dan TypeScript untuk development yang lebih solid.',
                'order' => 6,
                'image_url' => 'https://via.placeholder.com/400x300/F7DF1E/000000?text=JS+TS',
            ],
            [
                'title' => 'Web Security Fundamentals',
                'slug' => 'web-security',
                'description' => 'Pelajari konsep keamanan web: authentication, authorization, OWASP Top 10.',
                'order' => 7,
                'image_url' => 'https://via.placeholder.com/400x300/DC2626/FFFFFF?text=Security',
            ],
            [
                'title' => 'DevOps & Deployment',
                'slug' => 'devops-deployment',
                'description' => 'Pelajari CI/CD, Docker, dan strategi deployment untuk aplikasi modern.',
                'order' => 8,
                'image_url' => 'https://via.placeholder.com/400x300/2496ED/FFFFFF?text=DevOps',
            ],
            [
                'title' => 'UI/UX for Web Developers',
                'slug' => 'ui-ux-web',
                'description' => 'Pahami prinsip UI/UX design untuk membuat aplikasi yang user-friendly.',
                'order' => 9,
                'image_url' => 'https://via.placeholder.com/400x300/8B5CF6/FFFFFF?text=UI+UX',
            ],
            [
                'title' => 'Full Stack Project Development',
                'slug' => 'fullstack-project',
                'description' => 'Bangun aplikasi full stack lengkap dari planning hingga deployment.',
                'order' => 10,
                'image_url' => 'https://via.placeholder.com/400x300/F59E0B/FFFFFF?text=Full+Stack',
            ],
        ];

        foreach ($tracks as $track) {
            Track::updateOrCreate(
                ['slug' => $track['slug']],
                $track
            );
        }

        $this->command->info('✅ Tracks seeded: ' . count($tracks));
    }
}
