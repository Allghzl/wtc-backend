<?php

namespace Database\Factories;

use App\Models\StudyClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyClass>
 */
class StudyClassFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true) . ' - ' . fake()->randomElement(['Morning', 'Afternoon', 'Evening']),
        ];
    }
}
