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
                'order' => 1,
            ],
            [
                'track_id' => 1,
                'title' => 'CSS Styling',
                'slug' => 'css-styling',
                'order' => 2,
            ],
            [
                'track_id' => 1,
                'title' => 'JavaScript Fundamentals',
                'slug' => 'javascript-fundamentals',
                'order' => 3,
            ],

            // Backend Development with Laravel (track_id: 2)
            [
                'track_id' => 2,
                'title' => 'Laravel Installation & Setup',
                'slug' => 'laravel-installation-setup',
                'order' => 4,
            ],
            [
                'track_id' => 2,
                'title' => 'Routing & Controllers',
                'slug' => 'routing-controllers',
                'order' => 5,
            ],
            [
                'track_id' => 2,
                'title' => 'Eloquent ORM',
                'slug' => 'eloquent-orm',
                'order' => 6,
            ],

            // Frontend Development with React (track_id: 3)
            [
                'track_id' => 3,
                'title' => 'React Components',
                'slug' => 'react-components',
                'order' => 7,
            ],
            [
                'track_id' => 3,
                'title' => 'State & Props',
                'slug' => 'state-props',
                'order' => 8,
            ],

            // Database Design & SQL (track_id: 4)
            [
                'track_id' => 4,
                'title' => 'Database Normalization',
                'slug' => 'database-normalization',
                'order' => 9,
            ],
            [
                'track_id' => 4,
                'title' => 'SQL Queries',
                'slug' => 'sql-queries',
                'order' => 10,
            ],
        ];

        foreach ($modules as $module) {
            Module::create($module);
        }
    }
}
