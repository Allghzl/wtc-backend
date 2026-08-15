<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of all roles.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $roles = Role::withCount('profiles')
            ->orderBy('id')
            ->get();

        return $this->success(
            RoleResource::collection($roles),
            'Roles retrieved successfully.'
        );
    }

    /**
     * Store a newly created role in storage.
     *
     * @param StoreRoleRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRoleRequest $request)
    {
        $role = Role::create($request->validated());

        return $this->success(
            new RoleResource($role),
            'Role created successfully.',
            201
        );
    }

    /**
     * Display the specified role.
     *
     * @param Role $role
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Role $role)
    {
        $role->loadCount('profiles');

        return $this->success(
            new RoleResource($role),
            'Role retrieved successfully.'
        );
    }

    /**
     * Update the specified role in storage.
     *
     * @param UpdateRoleRequest $request
     * @param Role $role
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        // Prevent updating default/system roles
        if (in_array($role->name, ['student', 'teacher', 'admin'])) {
            return $this->error(
                'Cannot update default system roles.',
                403
            );
        }

        $role->update($request->validated());

        return $this->success(
            new RoleResource($role),
            'Role updated successfully.'
        );
    }

    /**
     * Remove the specified role from storage.
     *
     * @param Role $role
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Role $role)
    {
        // Prevent deleting default/system roles
        if (in_array($role->name, ['student', 'teacher', 'admin'])) {
            return $this->error(
                'Cannot delete default system roles.',
                403
            );
        }

        // Check if role is assigned to any profiles
        $profilesCount = $role->profiles()->count();

        if ($profilesCount > 0) {
            return $this->error(
                "Cannot delete role. It is currently assigned to {$profilesCount} user(s). Please remove the role from all users first.",
                409
            );
        }

        $role->delete();

        return $this->success(
            null,
            'Role deleted successfully.'
        );
    }
}
