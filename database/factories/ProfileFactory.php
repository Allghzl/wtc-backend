<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // user_id must be provided when creating a profile
            'study_class_id' => null,
            'display_name' => fake()->name(),
            'points' => 0,
            'last_login_at' => now(),
            'last_synced_at' => now(),
        ];
    }
}
