<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->words(4, true);

        return [
            'module_id' => Module::factory(),
            'title' => ucfirst($title),
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(1, 9999),
            'content' => fake()->paragraphs(3, true),
            'video_url' => fake()->optional()->url(),
            'order' => null, // Will be auto-populated by model boot method
        ];
    }
}
