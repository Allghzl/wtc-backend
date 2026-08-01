<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['id' => 1, 'name' => 'student'],
            ['id' => 2, 'name' => 'teacher'],
            ['id' => 3, 'name' => 'admin'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['id' => $role['id']],
                ['name' => $role['name']]
            );
        }

        $this->command->info('✅ Roles seeded: ' . count($roles));
    }
}
