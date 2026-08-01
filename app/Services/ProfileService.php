<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Role;

class ProfileService
{
    /**
     * Sinkronisasi profile dari JWT PinatAuth.
     */
    public function syncFromJwt(object $payload): Profile
    {
        $profile = Profile::firstOrNew([
            'puid' => $payload->sub,
        ]);

        $profile->display_name = $payload->name ?? null;
        $profile->nickname = $payload->name ?? null;
        $profile->email = $payload->email ?? null;
        $profile->avatar_key = $payload->avatar_key ?? null;
        $profile->last_login_at = now();
        $profile->last_synced_at = now();

        $profile->save();

        $student = Role::firstWhere("id", "1");

        $profile->roles()->syncWithoutDetaching([
            $student->id,
        ]);

        return $profile->fresh("roles");
    }

    /**
     * Cari profile berdasarkan PUID.
     */
    public function find(string $puid): ?Profile
    {
        return Profile::find($puid);
    }

    /**
     * Ambil profile atau gagal.
     */
    public function findOrFail(string $puid): Profile
    {
        return Profile::findOrFail($puid);
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
