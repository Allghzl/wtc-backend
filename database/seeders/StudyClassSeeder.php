<?php

namespace Database\Seeders;

use App\Models\StudyClass;
use Illuminate\Database\Seeder;

class StudyClassSeeder extends Seeder
{
    public function run(): void
    {
        $classes = [
            ['name' => 'XII RPL 1'],
            ['name' => 'XII RPL 2'],
            ['name' => 'XII RPL 3'],
            ['name' => 'XI RPL 1'],
            ['name' => 'XI RPL 2'],
            ['name' => 'X RPL 1'],
            ['name' => 'X RPL 2'],
        ];

        foreach ($classes as $class) {
            StudyClass::create($class);
        }
    }
}
