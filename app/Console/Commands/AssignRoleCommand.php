<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Models\Role;
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

        $profile = Profile::query()
            ->Where('email', $identifier)
            ->first();

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
        $this->line("Email : {$profile->email}");

        return self::SUCCESS;
    }
}
