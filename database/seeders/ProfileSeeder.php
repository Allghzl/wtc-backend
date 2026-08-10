<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates one profile for each user (1:1 relationship).
     * study_class_id will be assigned later in ProfileRoleSeeder when roles are assigned.
     */
    public function run(): void
    {
        $users = User::all();

        $profiles = [];

        foreach ($users as $user) {
            // Generate display name variations for realism
            $displayName = $this->generateDisplayName($user->name);

            $profiles[] = [
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'study_class_id' => null, // Will be set in ProfileRoleSeeder for students
                'display_name' => $displayName,
                'points' => 0,
                'last_login_at' => now()->subDays(rand(0, 30)),
                'last_synced_at' => now()->subDays(rand(0, 7)),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];
        }

        // Bulk insert for performance
        foreach (array_chunk($profiles, 100) as $chunk) {
            Profile::insert($chunk);
        }

        $this->command->info('✅ Profiles seeded: ' . count($profiles));
        $this->command->info('   👤 1:1 relationship with users');
        $this->command->info('   📚 study_class_id will be assigned in ProfileRoleSeeder');
    }

    /**
     * Generate display name variations for realism
     */
    private function generateDisplayName(string $fullName): string
    {
        // 70% keep full name, 30% use variations
        if (rand(1, 10) <= 7) {
            return $fullName;
        }

        // Use first name only for some variety
        $parts = explode(' ', $fullName);
        $firstName = $parts[0];

        // Possible variations
        $variations = [
            $firstName,
            $firstName . rand(10, 99), // e.g., "Ahmad23"
            strtoupper($firstName), // e.g., "AHMAD"
            $fullName, // Keep full name anyway
        ];

        return $variations[array_rand($variations)];
    }
}
