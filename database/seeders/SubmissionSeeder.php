<?php

namespace Database\Seeders;

use App\Models\Submission;
use Illuminate\Database\Seeder;

class SubmissionSeeder extends Seeder
{
    public function run(): void
    {
        $submissions = [
            // Ahmad Rizki (user_id: 4) submissions
            [
                'user_id' => 4,
                'challenge_id' => 1,
                'code' => '<!DOCTYPE html>\n<html>\n<head>\n    <title>My First Page</title>\n</head>\n<body>\n    <h1>Hello World</h1>\n</body>\n</html>',
                'status' => 'passed',
                'score' => 10,
                'feedback' => 'Bagus! Struktur HTML sudah benar.',
                'submitted_at' => now()->subDays(5),
            ],
            [
                'user_id' => 4,
                'challenge_id' => 2,
                'code' => '<form>\n    <input type="text" name="name">\n    <input type="email" name="email">\n    <input type="password" name="password">\n    <button type="submit">Submit</button>\n</form>',
                'status' => 'passed',
                'score' => 20,
                'feedback' => 'Sempurna! Semua input sudah lengkap.',
                'submitted_at' => now()->subDays(4),
            ],
            [
                'user_id' => 4,
                'challenge_id' => 3,
                'code' => 'p { color: blue; font-size: 16px; font-family: Arial; }',
                'status' => 'passed',
                'score' => 10,
                'feedback' => 'Good job!',
                'submitted_at' => now()->subDays(3),
            ],

            // Siti Aisyah (user_id: 5) submissions
            [
                'user_id' => 5,
                'challenge_id' => 1,
                'code' => '<!DOCTYPE html>\n<html>\n<head></head>\n<body>\n    <p>Hello</p>\n</body>\n</html>',
                'status' => 'passed',
                'score' => 10,
                'feedback' => 'Bagus, tapi bisa ditambahkan title.',
                'submitted_at' => now()->subDays(5),
            ],
            [
                'user_id' => 5,
                'challenge_id' => 2,
                'code' => '<form>\n    <input type="text">\n    <input type="email">\n    <button>Submit</button>\n</form>',
                'status' => 'failed',
                'score' => 0,
                'feedback' => 'Input password belum ada. Tambahkan input type password.',
                'submitted_at' => now()->subDays(4),
            ],
            [
                'user_id' => 5,
                'challenge_id' => 2,
                'code' => '<form>\n    <input type="text" name="name">\n    <input type="email" name="email">\n    <input type="password" name="password">\n    <button type="submit">Submit</button>\n</form>',
                'status' => 'passed',
                'score' => 20,
                'feedback' => 'Bagus! Sudah diperbaiki dengan sempurna.',
                'submitted_at' => now()->subDays(3),
            ],

            // Budi Prasetyo (user_id: 6) submissions
            [
                'user_id' => 6,
                'challenge_id' => 1,
                'code' => '<html><body><h1>Test</h1></body></html>',
                'status' => 'failed',
                'score' => 0,
                'feedback' => 'DOCTYPE dan head tag belum ada.',
                'submitted_at' => now()->subDays(6),
            ],
            [
                'user_id' => 6,
                'challenge_id' => 1,
                'code' => '<!DOCTYPE html>\n<html>\n<head>\n    <title>My Page</title>\n</head>\n<body>\n    <h1>Hello</h1>\n</body>\n</html>',
                'status' => 'passed',
                'score' => 10,
                'feedback' => 'Bagus, sudah diperbaiki!',
                'submitted_at' => now()->subDays(5),
            ],

            // Dewi Lestari (user_id: 7) submissions
            [
                'user_id' => 7,
                'challenge_id' => 5,
                'code' => 'let name = "Dewi";\nlet age = 17;\nlet isStudent = true;\nlet hobbies = ["reading", "coding"];\nlet profile = {name: "Dewi", grade: 12};',
                'status' => 'passed',
                'score' => 15,
                'feedback' => 'Excellent! Semua tipe data sudah benar.',
                'submitted_at' => now()->subDays(2),
            ],
            [
                'user_id' => 7,
                'challenge_id' => 6,
                'code' => 'function add(a, b) { return a + b; }\nfunction subtract(a, b) { return a - b; }\nfunction multiply(a, b) { return a * b; }\nfunction divide(a, b) { return a / b; }',
                'status' => 'passed',
                'score' => 25,
                'feedback' => 'Perfect! All functions work correctly.',
                'submitted_at' => now()->subDays(1),
            ],

            // Eko Purnomo (user_id: 8) submissions
            [
                'user_id' => 8,
                'challenge_id' => 3,
                'code' => 'p { color: red; }',
                'status' => 'failed',
                'score' => 0,
                'feedback' => 'Font-size dan font-family belum ditambahkan.',
                'submitted_at' => now()->subDays(3),
            ],

            // Fitri Handayani (user_id: 9) submissions
            [
                'user_id' => 9,
                'challenge_id' => 4,
                'code' => '.myclass { color: red; }\n#myid { background: blue; }\np { margin: 10px; }',
                'status' => 'passed',
                'score' => 20,
                'feedback' => 'Great work! Semua selector sudah digunakan dengan benar.',
                'submitted_at' => now()->subHours(12),
            ],
        ];

        foreach ($submissions as $submission) {
            Submission::create($submission);
        }
    }
}
