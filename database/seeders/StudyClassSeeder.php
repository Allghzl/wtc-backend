<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StudyClass;

class StudyClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates Indonesian SMK RPL (Rekayasa Perangkat Lunak) study classes
     * following the realistic structure: X/XI/XII RPL with parallel classes (rombel).
     */
    public function run(): void
    {
        // SMK RPL class structure: Grade X, XI, XII with 4 parallel classes each
        $classes = [
            // Grade X (Grade 10) - 4 parallel classes
            'X RPL 1',
            'X RPL 2',
            'X RPL 3',
            'X RPL 4',

            // Grade XI (Grade 11) - 4 parallel classes
            'XI RPL 1',
            'XI RPL 2',
            'XI RPL 3',
            'XI RPL 4',

            // Grade XII (Grade 12) - 4 parallel classes
            'XII RPL 1',
            'XII RPL 2',
            'XII RPL 3',
            'XII RPL 4',
        ];

        foreach ($classes as $className) {
            StudyClass::create(['name' => $className]);
        }

        $this->command->info('✅ Study classes seeded: ' . count($classes) . ' (SMK RPL X/XI/XII with 4 parallel classes each)');
    }
}
