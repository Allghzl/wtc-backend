<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoleService
{
    /**
     * Assign a role to a profile.
     *
     * @param Profile $profile The profile to assign the role to
     * @param int $roleId The ID of the role to assign
     * @return array{success: bool, message: string, role?: Role}
     */
    public function assignRole(Profile $profile, int $roleId): array
    {
        try {
            $role = Role::find($roleId);

            if (!$role) {
                return [
                    'success' => false,
                    'message' => 'Role not found.',
                ];
            }

            // Check if profile already has this role
            if ($profile->roles()->where('role_id', $roleId)->exists()) {
                return [
                    'success' => false,
                    'message' => 'Profile already has this role.',
                ];
            }

            DB::beginTransaction();

            try {
                // Attach role to profile
                $profile->roles()->attach($roleId);

                // Log activity (optional, if you want to track role assignments)
                Log::info('Role assigned to profile', [
                    'profile_id' => $profile->id,
                    'role_id' => $roleId,
                    'role_name' => $role->name,
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Role assigned successfully.',
                    'role' => $role,
                ];
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Failed to assign role', [
                'profile_id' => $profile->id,
                'role_id' => $roleId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to assign role: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Remove a role from a profile.
     *
     * @param Profile $profile The profile to remove the role from
     * @param int $roleId The ID of the role to remove
     * @return array{success: bool, message: string}
     */
    public function removeRole(Profile $profile, int $roleId): array
    {
        try {
            $role = Role::find($roleId);

            if (!$role) {
                return [
                    'success' => false,
                    'message' => 'Role not found.',
                ];
            }

            // Check if profile has this role
            if (!$profile->roles()->where('role_id', $roleId)->exists()) {
                return [
                    'success' => false,
                    'message' => 'Profile does not have this role.',
                ];
            }

            // Enforce minimum one role
            if ($profile->roles()->count() === 1) {
                return [
                    'success' => false,
                    'message' => 'Profile must have at least one role.',
                    'status'  => 422,
                ];
            }

            DB::beginTransaction();

            try {
                // Detach role from profile
                $profile->roles()->detach($roleId);

                // Log activity
                Log::info('Role removed from profile', [
                    'profile_id' => $profile->id,
                    'role_id' => $roleId,
                    'role_name' => $role->name,
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Role removed successfully.',
                ];
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Failed to remove role', [
                'profile_id' => $profile->id,
                'role_id' => $roleId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to remove role: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get all roles assigned to a profile.
     *
     * @param Profile $profile
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProfileRoles(Profile $profile)
    {
        return $profile->roles;
    }

    /**
     * Get all available roles.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllRoles()
    {
        return Role::all();
    }
}
