<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StudyClass;

class StudyClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classes = [
            'Class A - Morning',
            'Class B - Afternoon',
            'Class C - Evening',
            'Class D - Weekend',
            'Advanced Track',
            'Beginner Track',
        ];

        foreach ($classes as $className) {
            StudyClass::create(['name' => $className]);
        }

        $this->command->info('✅ Study classes seeded: ' . count($classes));
    }
}
