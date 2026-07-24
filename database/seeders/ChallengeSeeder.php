<?php

namespace Database\Seeders;

use App\Models\Challenge;
use Illuminate\Database\Seeder;

class ChallengeSeeder extends Seeder
{
    public function run(): void
    {
        $challenges = [
            // HTML Basics challenges (module_id: 1)
            [
                'module_id' => 1,
                'title' => 'Buat Halaman HTML Pertama',
                'slug' => 'buat-halaman-html-pertama',
                'description' => 'Buat file HTML sederhana dengan struktur dasar',
                'type' => 'coding',
                'content' => 'Buatlah sebuah halaman HTML lengkap dengan DOCTYPE, html, head, dan body tags.',
                'instructions' => 'Buat file HTML dengan struktur: <!DOCTYPE html>, <html>, <head>, <body>. Pastikan semua tag ditutup dengan benar.',
                'difficulty' => 'easy',
                'order' => 1,
                'metadata' => json_encode([
                    'required_tags' => ['DOCTYPE', 'html', 'head', 'body'],
                    'time_limit' => 30,
                ]),
                'max_score' => 100,
                'points' => 10,
            ],
            [
                'module_id' => 1,
                'title' => 'HTML Form Challenge',
                'slug' => 'html-form-challenge',
                'description' => 'Buat form dengan berbagai input',
                'type' => 'coding',
                'content' => 'Buatlah sebuah form HTML yang memiliki berbagai tipe input field.',
                'instructions' => 'Buat form dengan input: text, email, password, dan submit button. Gunakan tag <form> dan input type yang sesuai.',
                'difficulty' => 'medium',
                'order' => 2,
                'metadata' => json_encode([
                    'required_inputs' => ['text', 'email', 'password', 'submit'],
                    'time_limit' => 45,
                ]),
                'max_score' => 100,
                'points' => 20,
            ],

            // CSS challenges (module_id: 2)
            [
                'module_id' => 2,
                'title' => 'Style Text dengan CSS',
                'slug' => 'style-text-css',
                'description' => 'Gunakan CSS untuk styling text',
                'type' => 'coding',
                'content' => 'Buatlah CSS yang membuat text berwarna biru dengan ukuran font 16px.',
                'instructions' => 'Buat paragraf dengan warna biru, font-size 16px, dan font-family Arial menggunakan CSS.',
                'difficulty' => 'easy',
                'order' => 1,
                'metadata' => json_encode([
                    'required_properties' => ['color', 'font-size', 'font-family'],
                    'expected_values' => ['blue', '16px', 'Arial'],
                ]),
                'max_score' => 100,
                'points' => 10,
            ],
            [
                'module_id' => 2,
                'title' => 'CSS Selector Challenge',
                'slug' => 'css-selector-challenge',
                'description' => 'Gunakan berbagai CSS selector',
                'type' => 'coding',
                'content' => 'Tunjukkan pemahaman Anda tentang berbagai jenis CSS selector.',
                'instructions' => 'Gunakan class selector, id selector, dan element selector untuk styling berbagai elemen HTML.',
                'difficulty' => 'medium',
                'order' => 2,
                'metadata' => json_encode([
                    'required_selectors' => ['class', 'id', 'element'],
                    'time_limit' => 40,
                ]),
                'max_score' => 100,
                'points' => 20,
            ],

            // JavaScript challenges (module_id: 3)
            [
                'module_id' => 3,
                'title' => 'Deklarasi Variabel',
                'slug' => 'deklarasi-variabel',
                'description' => 'Buat variabel dengan berbagai tipe data',
                'type' => 'coding',
                'content' => 'Buatlah variabel JavaScript dengan berbagai tipe data yang berbeda.',
                'instructions' => 'Buat variabel: string, number, boolean, array, dan object. Gunakan let atau const untuk deklarasi.',
                'difficulty' => 'easy',
                'order' => 1,
                'metadata' => json_encode([
                    'required_types' => ['string', 'number', 'boolean', 'array', 'object'],
                    'time_limit' => 30,
                ]),
                'max_score' => 100,
                'points' => 15,
            ],
            [
                'module_id' => 3,
                'title' => 'Buat Function Calculator',
                'slug' => 'buat-function-calculator',
                'description' => 'Buat function untuk operasi matematika',
                'type' => 'coding',
                'content' => 'Buatlah function JavaScript untuk melakukan operasi matematika dasar.',
                'instructions' => 'Buat function add, subtract, multiply, divide yang menerima 2 parameter dan return hasil operasi.',
                'difficulty' => 'medium',
                'order' => 2,
                'metadata' => json_encode([
                    'required_functions' => ['add', 'subtract', 'multiply', 'divide'],
                    'test_cases' => [
                        ['add(2, 3)', 5],
                        ['subtract(10, 4)', 6],
                        ['multiply(3, 7)', 21],
                        ['divide(20, 4)', 5],
                    ],
                ]),
                'max_score' => 100,
                'points' => 25,
            ],

            // Laravel challenges (module_id: 5)
            [
                'module_id' => 5,
                'title' => 'Buat Route Sederhana',
                'slug' => 'buat-route-sederhana',
                'description' => 'Buat route GET yang return view',
                'type' => 'coding',
                'content' => 'Buatlah sebuah route Laravel sederhana.',
                'instructions' => 'Buat route /about yang return view about. Gunakan Route::get() di file routes/web.php.',
                'difficulty' => 'easy',
                'order' => 1,
                'metadata' => json_encode([
                    'required_method' => 'GET',
                    'required_path' => '/about',
                    'time_limit' => 20,
                ]),
                'max_score' => 100,
                'points' => 15,
            ],
            [
                'module_id' => 5,
                'title' => 'Buat Controller & Route',
                'slug' => 'buat-controller-route',
                'description' => 'Buat controller dan hubungkan dengan route',
                'type' => 'coding',
                'content' => 'Buatlah controller Laravel dan hubungkan dengan route.',
                'instructions' => 'Buat UserController dengan method index, buat route yang mengarah ke controller tersebut. Gunakan php artisan make:controller untuk generate.',
                'difficulty' => 'medium',
                'order' => 2,
                'metadata' => json_encode([
                    'required_controller' => 'UserController',
                    'required_method' => 'index',
                    'time_limit' => 45,
                ]),
                'max_score' => 100,
                'points' => 25,
            ],
        ];

        foreach ($challenges as $challenge) {
            Challenge::create($challenge);
        }
    }
}
