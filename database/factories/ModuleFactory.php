<?php

namespace Database\Factories;

use App\Models\Module;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->words(3, true);

        return [
            'track_id' => Track::factory(),
            'title' => ucfirst($title),
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(1, 9999),
            'order' => null, // Will be auto-populated by model boot method
        ];
    }
}
