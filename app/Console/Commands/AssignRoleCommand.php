<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class AssignRoleCommand extends Command
{
    /**
     * php artisan role:assign <identifier> <role>
     */
    protected $signature = 'role:assign
                            {identifier : UUID atau Email}
                            {role : Nama role}';

    protected $description = 'Assign role ke profile';

    public function handle(): int
    {
        $identifier = $this->argument('identifier');

        $user = User::query()
            ->Where('email', $identifier)
            ->first();
        $profile = $user->profile;
        if (! $profile) {
            $this->error("Profile '{$identifier}' tidak ditemukan.");
            return self::FAILURE;
        }

        $role = Role::where('name', $this->argument('role'))->first();

        if (! $role) {
            $this->error("Role '{$this->argument('role')}' tidak ditemukan.");
            return self::FAILURE;
        }

        $profile->roles()->syncWithoutDetaching([
            $role->id,
        ]);

        $this->info("✔ Role '{$role->name}' berhasil ditambahkan.");

        $this->line("User  : {$profile->display_name}");
        $this->line("Email : {$user->email}");

        return self::SUCCESS;
    }
}
