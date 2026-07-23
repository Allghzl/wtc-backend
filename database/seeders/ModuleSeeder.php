<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            // Web Development Fundamentals (track_id: 1)
            [
                'track_id' => 1,
                'title' => 'HTML Basics',
                'slug' => 'html-basics',
                'description' => 'Pelajari struktur dasar HTML dan element-element penting',
                'order' => 1,
            ],
            [
                'track_id' => 1,
                'title' => 'CSS Styling',
                'slug' => 'css-styling',
                'description' => 'Belajar styling web dengan CSS',
                'order' => 2,
            ],
            [
                'track_id' => 1,
                'title' => 'JavaScript Fundamentals',
                'slug' => 'javascript-fundamentals',
                'description' => 'Dasar-dasar pemrograman JavaScript',
                'order' => 3,
            ],

            // Backend Development with Laravel (track_id: 2)
            [
                'track_id' => 2,
                'title' => 'Laravel Installation & Setup',
                'slug' => 'laravel-installation-setup',
                'description' => 'Instalasi Laravel dan setup environment',
                'order' => 1,
            ],
            [
                'track_id' => 2,
                'title' => 'Routing & Controllers',
                'slug' => 'routing-controllers',
                'description' => 'Pelajari routing dan controller di Laravel',
                'order' => 2,
            ],
            [
                'track_id' => 2,
                'title' => 'Eloquent ORM',
                'slug' => 'eloquent-orm',
                'description' => 'Database operations dengan Eloquent',
                'order' => 3,
            ],

            // Frontend Development with React (track_id: 3)
            [
                'track_id' => 3,
                'title' => 'React Components',
                'slug' => 'react-components',
                'description' => 'Memahami komponen di React',
                'order' => 1,
            ],
            [
                'track_id' => 3,
                'title' => 'State & Props',
                'slug' => 'state-props',
                'description' => 'Mengelola state dan props di React',
                'order' => 2,
            ],

            // Database Design & SQL (track_id: 4)
            [
                'track_id' => 4,
                'title' => 'Database Normalization',
                'slug' => 'database-normalization',
                'description' => 'Prinsip normalisasi database',
                'order' => 1,
            ],
            [
                'track_id' => 4,
                'title' => 'SQL Queries',
                'slug' => 'sql-queries',
                'description' => 'Menulis query SQL untuk manipulasi data',
                'order' => 2,
            ],
        ];

        foreach ($modules as $module) {
            Module::create($module);
        }
    }
}
