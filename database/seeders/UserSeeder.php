<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::create([
            'name' => 'Admin',
            'email' => 'admin@wtc',
            'password' => Hash::make('webtech4'),
            'role' => 'admin',
            'study_class_id' => null,
            'email_verified_at' => now(),
        ]);

        // Teachers
        $teachers = [
            ['name' => 'Fikri Santoso', 'email' => 'aziz.teacher@example.com'],
            ['name' => 'Dafa Rachman', 'email' => 'abdul.teacher@example.com'],
        ];

        foreach ($teachers as $teacher) {
            User::create([
                'name' => $teacher['name'],
                'email' => $teacher['email'],
                'password' => Hash::make('password'),
                'role' => 'teacher',
                'study_class_id' => null,
                'email_verified_at' => now(),
            ]);
        }

        // Students
        $students = [
            ['name' => 'Ahmad Rizki', 'email' => 'ahmad@example.com', 'class' => 1],
            ['name' => 'Siti Aisyah', 'email' => 'siti@example.com', 'class' => 1],
            ['name' => 'Budi Prasetyo', 'email' => 'budi@example.com', 'class' => 1],
            ['name' => 'Dewi Lestari', 'email' => 'dewi@example.com', 'class' => 2],
            ['name' => 'Eko Purnomo', 'email' => 'eko@example.com', 'class' => 2],
            ['name' => 'Fitri Handayani', 'email' => 'fitri@example.com', 'class' => 2],
            ['name' => 'Gilang Ramadhan', 'email' => 'gilang@example.com', 'class' => 3],
            ['name' => 'Hani Oktavia', 'email' => 'hani@example.com', 'class' => 3],
            ['name' => 'Irfan Hakim', 'email' => 'irfan@example.com', 'class' => 4],
            ['name' => 'Jasmine Putri', 'email' => 'jasmine@example.com', 'class' => 4],
            ['name' => 'Rohman', 'email' => 'Rohman@example.com', 'class' => 4],
        ];

        foreach ($students as $student) {
            User::create([
                'name' => $student['name'],
                'email' => $student['email'],
                'password' => Hash::make('password'),
                'role' => 'student',
                'study_class_id' => $student['class'],
                'email_verified_at' => now(),
            ]);
        }
    }
}
