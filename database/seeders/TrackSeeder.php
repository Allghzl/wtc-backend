<?php

namespace Database\Seeders;

use App\Models\Track;
use Illuminate\Database\Seeder;

class TrackSeeder extends Seeder
{
    public function run(): void
    {
        $tracks = [
            [
                'title' => 'Web Development Fundamentals',
                'slug' => 'web-development-fundamentals',
                'description' => 'Pelajari dasar-dasar pengembangan web dari HTML, CSS, hingga JavaScript',
                'order' => 1,
                'image_url' => 'tracks/web-fundamentals.jpg',
            ],
            [
                'title' => 'Backend Development with Laravel',
                'slug' => 'backend-development-laravel',
                'description' => 'Kuasai pembuatan aplikasi backend menggunakan framework Laravel',
                'order' => 2,
                'image_url' => 'tracks/laravel-backend.jpg',
            ],
            [
                'title' => 'Frontend Development with React',
                'slug' => 'frontend-development-react',
                'description' => 'Belajar membuat aplikasi web modern dengan React.js',
                'order' => 3,
                'image_url' => 'tracks/react-frontend.jpg',
            ],
            [
                'title' => 'Database Design & SQL',
                'slug' => 'database-design-sql',
                'description' => 'Pelajari perancangan database dan query SQL untuk aplikasi',
                'order' => 4,
                'image_url' => 'tracks/database-sql.jpg',
            ],
        ];

        foreach ($tracks as $track) {
            Track::create($track);
        }
    }
}
