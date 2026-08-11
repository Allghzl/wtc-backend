<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\Module;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
{
    /**
     * Define the model's default state.
     * By default, creates a module-based challenge (not lesson-based).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->words(4, true);
        $types = ['code', 'quiz', 'project', 'exercise'];
        $difficulties = ['easy', 'medium', 'hard'];

        return [
            'module_id' => Module::factory(),
            'lesson_id' => null, // By default, challenge belongs to module, not lesson
            'title' => ucfirst($title),
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(1, 9999),
            'type' => fake()->randomElement($types),
            'difficulty' => fake()->randomElement($difficulties),
            'content' => fake()->paragraphs(3, true),
            'settings' => [
                'time_limit' => fake()->optional()->numberBetween(30, 180),
                'show_hints' => fake()->boolean(),
            ],
            'metadata' => [
                'tags' => fake()->words(3),
            ],
            'max_score' => fake()->numberBetween(50, 100),
            'allowed_attempts' => fake()->numberBetween(1, 5),
            'points' => fake()->numberBetween(10, 50),
            'order' => null, // Will be auto-populated by model boot method
        ];
    }

    /**
     * Create a challenge that belongs to a lesson instead of a module.
     */
    public function forLesson(?int $lessonId = null): static
    {
        return $this->state(function (array $attributes) use ($lessonId) {
            return [
                'module_id' => null,
                'lesson_id' => $lessonId ?? Lesson::factory(),
            ];
        });
    }

    /**
     * Create a challenge that belongs to a specific module.
     */
    public function forModule(int $moduleId): static
    {
        return $this->state(function (array $attributes) use ($moduleId) {
            return [
                'module_id' => $moduleId,
                'lesson_id' => null,
            ];
        });
    }
}
