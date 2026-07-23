<?php

namespace Database\Seeders;

use App\Models\Lesson;
use Illuminate\Database\Seeder;

class LessonSeeder extends Seeder
{
    public function run(): void
    {
        $lessons = [
            // HTML Basics (module_id: 1)
            [
                'module_id' => 1,
                'title' => 'Pengenalan HTML',
                'slug' => 'pengenalan-html',
                'description' => 'Apa itu HTML dan mengapa penting',
                'content' => 'HTML (HyperText Markup Language) adalah bahasa markup standar untuk membuat halaman web. HTML mendeskripsikan struktur dari halaman web menggunakan markup.',
                'video_url' => null,
                'duration' => 15,
                'order' => 1,
            ],
            [
                'module_id' => 1,
                'title' => 'HTML Elements & Tags',
                'slug' => 'html-elements-tags',
                'description' => 'Mempelajari element dan tag HTML',
                'content' => 'HTML element terdiri dari tag pembuka, konten, dan tag penutup. Contoh: <p>Ini adalah paragraf</p>',
                'video_url' => 'https://youtube.com/watch?v=example1',
                'duration' => 25,
                'order' => 2,
            ],
            [
                'module_id' => 1,
                'title' => 'Quiz: HTML Basics',
                'slug' => 'quiz-html-basics',
                'description' => 'Uji pemahaman HTML dasar',
                'content' => 'Kuis untuk menguji pemahaman tentang HTML basics',
                'video_url' => null,
                'duration' => 10,
                'order' => 3,
            ],

            // CSS Styling (module_id: 2)
            [
                'module_id' => 2,
                'title' => 'CSS Syntax',
                'slug' => 'css-syntax',
                'description' => 'Memahami sintaks CSS',
                'content' => 'CSS terdiri dari selector dan declaration block. Contoh: h1 { color: blue; font-size: 12px; }',
                'video_url' => null,
                'duration' => 20,
                'order' => 1,
            ],
            [
                'module_id' => 2,
                'title' => 'CSS Selectors',
                'slug' => 'css-selectors',
                'description' => 'Berbagai jenis selector di CSS',
                'content' => 'CSS selector digunakan untuk memilih element HTML yang ingin di-styling.',
                'video_url' => 'https://youtube.com/watch?v=example2',
                'duration' => 30,
                'order' => 2,
            ],

            // JavaScript Fundamentals (module_id: 3)
            [
                'module_id' => 3,
                'title' => 'Variables & Data Types',
                'slug' => 'variables-data-types',
                'description' => 'Variabel dan tipe data di JavaScript',
                'content' => 'JavaScript memiliki berbagai tipe data: string, number, boolean, object, array, dll.',
                'video_url' => null,
                'duration' => 25,
                'order' => 1,
            ],
            [
                'module_id' => 3,
                'title' => 'Functions',
                'slug' => 'functions',
                'description' => 'Cara membuat dan menggunakan function',
                'content' => 'Function adalah blok kode yang dapat digunakan berulang kali.',
                'video_url' => 'https://youtube.com/watch?v=example3',
                'duration' => 35,
                'order' => 2,
            ],

            // Laravel Installation & Setup (module_id: 4)
            [
                'module_id' => 4,
                'title' => 'Install Laravel',
                'slug' => 'install-laravel',
                'description' => 'Cara install Laravel via Composer',
                'content' => 'Gunakan command: composer create-project laravel/laravel nama-project',
                'video_url' => 'https://youtube.com/watch?v=example4',
                'duration' => 40,
                'order' => 1,
            ],

            // Routing & Controllers (module_id: 5)
            [
                'module_id' => 5,
                'title' => 'Basic Routing',
                'slug' => 'basic-routing',
                'description' => 'Routing dasar di Laravel',
                'content' => 'Route mendefinisikan URL dan response. Contoh: Route::get(' / ', function() { return view("welcome"); });',
                'video_url' => null,
                'duration' => 20,
                'order' => 1,
            ],
            [
                'module_id' => 5,
                'title' => 'Controllers',
                'slug' => 'controllers',
                'description' => 'Membuat dan menggunakan controller',
                'content' => 'Controller mengelompokkan logic handling request. Gunakan php artisan make:controller untuk membuat controller.',
                'video_url' => 'https://youtube.com/watch?v=example5',
                'duration' => 30,
                'order' => 2,
            ],
        ];

        foreach ($lessons as $lesson) {
            Lesson::create($lesson);
        }
    }
}
