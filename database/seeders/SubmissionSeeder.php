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
                'status' => 'passed',
                'submitted_content' => json_encode([
                    'code' => '<!DOCTYPE html>\n<html>\n<head>\n    <title>My First Page</title>\n</head>\n<body>\n    <h1>Hello World</h1>\n</body>\n</html>',
                    'language' => 'html',
                ]),
                'file_path' => null,
                'auto_score' => 100,
                'manual_score' => null,
                'feedback' => 'Bagus! Struktur HTML sudah benar.',
                'submitted_at' => now()->subDays(5),
            ],
            [
                'user_id' => 4,
                'challenge_id' => 2,
                'status' => 'passed',
                'submitted_content' => json_encode([
                    'code' => '<form>\n    <input type="text" name="name">\n    <input type="email" name="email">\n    <input type="password" name="password">\n    <button type="submit">Submit</button>\n</form>',
                    'language' => 'html',
                ]),
                'file_path' => null,
                'auto_score' => 95,
                'manual_score' => 100,
                'feedback' => 'Sempurna! Semua input sudah lengkap.',
                'submitted_at' => now()->subDays(4),
            ],
            [
                'user_id' => 4,
                'challenge_id' => 3,
                'status' => 'passed',
                'submitted_content' => json_encode([
                    'code' => 'p { color: blue; font-size: 16px; font-family: Arial; }',
                    'language' => 'css',
                ]),
                'file_path' => null,
                'auto_score' => 100,
                'manual_score' => null,
                'feedback' => 'Good job!',
                'submitted_at' => now()->subDays(3),
            ],

            // Siti Aisyah (user_id: 5) submissions
            [
                'user_id' => 5,
                'challenge_id' => 1,
                'status' => 'passed',
                'submitted_content' => json_encode([
                    'code' => '<!DOCTYPE html>\n<html>\n<head></head>\n<body>\n    <p>Hello</p>\n</body>\n</html>',
                    'language' => 'html',
                ]),
                'file_path' => null,
                'auto_score' => 85,
                'manual_score' => 90,
                'feedback' => 'Bagus, tapi bisa ditambahkan title.',
                'submitted_at' => now()->subDays(5),
            ],
            [
                'user_id' => 5,
                'challenge_id' => 2,
                'status' => 'failed',
                'submitted_content' => json_encode([
                    'code' => '<form>\n    <input type="text">\n    <input type="email">\n    <button>Submit</button>\n</form>',
                    'language' => 'html',
                ]),
                'file_path' => null,
                'auto_score' => 50,
                'manual_score' => null,
                'feedback' => 'Input password belum ada. Tambahkan input type password.',
                'submitted_at' => now()->subDays(4),
            ],
            [
                'user_id' => 5,
                'challenge_id' => 2,
                'status' => 'passed',
                'submitted_content' => json_encode([
                    'code' => '<form>\n    <input type="text" name="name">\n    <input type="email" name="email">\n    <input type="password" name="password">\n    <button type="submit">Submit</button>\n</form>',
                    'language' => 'html',
                ]),
                'file_path' => null,
                'auto_score' => 100,
                'manual_score' => null,
                'feedback' => 'Bagus! Sudah diperbaiki dengan sempurna.',
                'submitted_at' => now()->subDays(3),
            ],

            // Budi Prasetyo (user_id: 6) submissions
            [
                'user_id' => 6,
                'challenge_id' => 1,
                'status' => 'failed',
                'submitted_content' => json_encode([
                    'code' => '<html><body><h1>Test</h1></body></html>',
                    'language' => 'html',
                ]),
                'file_path' => null,
                'auto_score' => 40,
                'manual_score' => null,
                'feedback' => 'DOCTYPE dan head tag belum ada.',
                'submitted_at' => now()->subDays(6),
            ],
            [
                'user_id' => 6,
                'challenge_id' => 1,
                'status' => 'passed',
                'submitted_content' => json_encode([
                    'code' => '<!DOCTYPE html>\n<html>\n<head>\n    <title>My Page</title>\n</head>\n<body>\n    <h1>Hello</h1>\n</body>\n</html>',
                    'language' => 'html',
                ]),
                'file_path' => null,
                'auto_score' => 100,
                'manual_score' => null,
                'feedback' => 'Bagus, sudah diperbaiki!',
                'submitted_at' => now()->subDays(5),
            ],

            // Dewi Lestari (user_id: 7) submissions
            [
                'user_id' => 7,
                'challenge_id' => 5,
                'status' => 'passed',
                'submitted_content' => json_encode([
                    'code' => 'let name = "Dewi";\nlet age = 17;\nlet isStudent = true;\nlet hobbies = ["reading", "coding"];\nlet profile = {name: "Dewi", grade: 12};',
                    'language' => 'javascript',
                ]),
                'file_path' => null,
                'auto_score' => 100,
                'manual_score' => null,
                'feedback' => 'Excellent! Semua tipe data sudah benar.',
                'submitted_at' => now()->subDays(2),
            ],
            [
                'user_id' => 7,
                'challenge_id' => 6,
                'status' => 'passed',
                'submitted_content' => json_encode([
                    'code' => 'function add(a, b) { return a + b; }\nfunction subtract(a, b) { return a - b; }\nfunction multiply(a, b) { return a * b; }\nfunction divide(a, b) { return a / b; }',
                    'language' => 'javascript',
                ]),
                'file_path' => null,
                'auto_score' => 100,
                'manual_score' => null,
                'feedback' => 'Perfect! All functions work correctly.',
                'submitted_at' => now()->subDays(1),
            ],

            // Eko Purnomo (user_id: 8) submissions
            [
                'user_id' => 8,
                'challenge_id' => 3,
                'status' => 'failed',
                'submitted_content' => json_encode([
                    'code' => 'p { color: red; }',
                    'language' => 'css',
                ]),
                'file_path' => null,
                'auto_score' => 33,
                'manual_score' => null,
                'feedback' => 'Font-size dan font-family belum ditambahkan.',
                'submitted_at' => now()->subDays(3),
            ],

            // Fitri Handayani (user_id: 9) submissions
            [
                'user_id' => 9,
                'challenge_id' => 4,
                'status' => 'passed',
                'submitted_content' => json_encode([
                    'code' => '.myclass { color: red; }\n#myid { background: blue; }\np { margin: 10px; }',
                    'language' => 'css',
                ]),
                'file_path' => null,
                'auto_score' => 100,
                'manual_score' => null,
                'feedback' => 'Great work! Semua selector sudah digunakan dengan benar.',
                'submitted_at' => now()->subHours(12),
            ],
        ];

        foreach ($submissions as $submission) {
            Submission::create($submission);
        }
    }
}
