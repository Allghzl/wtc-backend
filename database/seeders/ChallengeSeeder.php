<?php

namespace Database\Seeders;

use App\Models\Challenge;
use Illuminate\Database\Seeder;

class ChallengeSeeder extends Seeder
{
    public function run(): void
    {
        $challenges = [
            // HTML Basics challenges (lesson_id: 1, 2)
            [
                'lesson_id' => 1,
                'title' => 'Buat Halaman HTML Pertama',
                'description' => 'Buat file HTML sederhana dengan struktur dasar',
                'slug' => 'buat-halaman-html-pertama',
                'instructions' => 'Buat file HTML dengan struktur: <!DOCTYPE html>, <html>, <head>, <body>',
                'difficulty' => 'easy',
                'points' => 10,
                'order' => 1,
            ],
            [
                'lesson_id' => 2,
                'title' => 'HTML Form Challenge',
                'description' => 'Buat form dengan berbagai input',
                'slug' => 'html-form-challenge',
                'instructions' => 'Buat form dengan input: text, email, password, dan submit button',
                'difficulty' => 'medium',
                'points' => 20,
                'order' => 1,
            ],

            // CSS challenges (lesson_id: 4, 5)
            [
                'lesson_id' => 4,
                'title' => 'Style Text dengan CSS',
                'description' => 'Gunakan CSS untuk styling text',
                'slug' => 'style-text-css',
                'instructions' => 'Buat paragraf dengan warna biru, font-size 16px, dan font-family Arial',
                'difficulty' => 'easy',
                'points' => 10,
                'order' => 1,
            ],
            [
                'lesson_id' => 5,
                'title' => 'CSS Selector Challenge',
                'description' => 'Gunakan berbagai CSS selector',
                'slug' => 'css-selector-challenge',
                'instructions' => 'Gunakan class selector, id selector, dan element selector untuk styling',
                'difficulty' => 'medium',
                'points' => 20,
                'order' => 1,
            ],

            // JavaScript challenges (lesson_id: 6, 7)
            [
                'lesson_id' => 6,
                'title' => 'Deklarasi Variabel',
                'description' => 'Buat variabel dengan berbagai tipe data',
                'slug' => 'deklarasi-variabel',
                'instructions' => 'Buat variabel: string, number, boolean, array, dan object',
                'difficulty' => 'easy',
                'points' => 15,
                'order' => 1,
            ],
            [
                'lesson_id' => 7,
                'title' => 'Buat Function Calculator',
                'description' => 'Buat function untuk operasi matematika',
                'slug' => 'buat-function-calculator',
                'instructions' => 'Buat function add, subtract, multiply, divide yang menerima 2 parameter',
                'difficulty' => 'medium',
                'points' => 25,
                'order' => 1,
            ],

            // Laravel challenges (lesson_id: 8, 9, 10)
            [
                'lesson_id' => 9,
                'title' => 'Buat Route Sederhana',
                'description' => 'Buat route GET yang return view',
                'slug' => 'buat-route-sederhana',
                'instructions' => 'Buat route /about yang return view about',
                'difficulty' => 'easy',
                'points' => 15,
                'order' => 1,
            ],
            [
                'lesson_id' => 10,
                'title' => 'Buat Controller & Route',
                'description' => 'Buat controller dan hubungkan dengan route',
                'slug' => 'buat-controller-route',
                'instructions' => 'Buat UserController dengan method index, buat route yang mengarah ke controller tersebut',
                'difficulty' => 'medium',
                'points' => 25,
                'order' => 1,
            ],
        ];

        foreach ($challenges as $challenge) {
            Challenge::create($challenge);
        }
    }
}
