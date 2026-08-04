<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;

class ProfileService
{
    /**
     * Membuat profile pertama kali.
     */
    public function create(User $user, ?object $payload = null): Profile
    {
        $profile = Profile::create([
            'user_id'        => $user->id,
            'study_class_id' => null,
            'display_name'   => $user->name,
            'last_login_at'  => now(),
            'last_synced_at' => $payload ? now() : null,
        ]);

        $this->assignDefaultRole($profile);

        return $profile->fresh('roles');
    }

    /**
     * Sinkronisasi profile dari PinatAuth.
     */
    public function sync(Profile $profile, object $payload): Profile
    {
        $profile->update([
            'display_name'   => $payload->name ?? $profile->display_name,
            'last_login_at'  => now(),
            'last_synced_at' => now(),
        ]);

        return $profile->fresh('roles');
    }

    /**
     * Assign role bawaan.
     */
    public function assignDefaultRole(Profile $profile): void
    {
        $student = Role::firstOrCreate(['name' => 'student']);

        if ($student) {
            $profile->roles()->syncWithoutDetaching([
                $student->id,
            ]);
        }
    }

    /**
     * Cari profile berdasarkan ID.
     */
    public function find(string $id): ?Profile
    {
        return Profile::find($id);
    }

    /**
     * Cari profile berdasarkan User.
     */
    public function findByUser(User $user): ?Profile
    {
        return $user->profile;
    }

    /**
     * Ambil profile atau gagal.
     */
    public function findOrFail(string $id): Profile
    {
        return Profile::findOrFail($id);
    }

    /**
     * Update profile lokal.
     */
    public function update(Profile $profile, array $data): Profile
    {
        $profile->update($data);

        return $profile->fresh();
    }
}
