<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignRoleRequest;
use App\Http\Requests\AvatarUploadRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\Profile;
use App\Services\AvatarService;
use App\Services\RoleService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected RoleService $roleService,
        protected AvatarService $avatarService,
    ) {}

    /**
     * Display a paginated listing of profiles with search, filter, and sort capabilities.
     *
     * Query Parameters:
     * - page: Page number (default: 1)
     * - per_page: Items per page (default: 15, max: 100)
     * - search: Search by user name or email
     * - role: Filter by role name or role ID
     * - study_class_id: Filter by study class
     * - sort_by: Sort field (display_name, created_at, last_login_at)
     * - sort_order: Sort direction (asc, desc)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = min($request->input('per_page', 15), 100);
        $search = $request->input('search');
        $roleFilter = $request->input('role');
        $studyClassFilter = $request->input('study_class_id');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        // Validate sort field
        $allowedSortFields = ['display_name', 'created_at', 'last_login_at', 'points'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }

        // Validate sort order
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        $query = Profile::with(['user', 'roles', 'studyClass']);

        // Search by user name or email
        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($roleFilter) {
            $query->whereHas('roles', function ($q) use ($roleFilter) {
                // Support both role ID and role name
                if (is_numeric($roleFilter)) {
                    $q->where('roles.id', $roleFilter);
                } else {
                    $q->where('roles.name', $roleFilter);
                }
            });
        }

        // Filter by study class
        if ($studyClassFilter) {
            $query->where('study_class_id', $studyClassFilter);
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);

        // Paginate results
        $profiles = $query->paginate($perPage);

        return $this->success([
            'profiles' => ProfileResource::collection($profiles->items()),
            'pagination' => [
                'current_page' => $profiles->currentPage(),
                'per_page' => $profiles->perPage(),
                'total' => $profiles->total(),
                'last_page' => $profiles->lastPage(),
                'from' => $profiles->firstItem(),
                'to' => $profiles->lastItem(),
            ],
        ], 'Profiles retrieved successfully.');
    }

    /**
     * Display the specified profile with relationships.
     *
     * @param Profile $profile
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Profile $profile)
    {
        $profile->load(['roles', 'achievements', 'studyClass', 'user']);

        return $this->success(
            new ProfileResource($profile),
            'Profile retrieved successfully.'
        );
    }

    /**
     * Update the specified profile.
     *
     * @param UpdateProfileRequest $request
     * @param Profile $profile
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateProfileRequest $request, Profile $profile)
    {
        $profile->update($request->validated());

        // Reload profile with relationships
        $profile->load(['roles', 'achievements', 'studyClass', 'user']);

        return $this->success(
            new ProfileResource($profile),
            'Profile updated successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Avatar Management
    |--------------------------------------------------------------------------
    */

    /**
     * Upload avatar for a profile.
     *
     * @param AvatarUploadRequest $request
     * @param Profile $profile
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadAvatar(AvatarUploadRequest $request, Profile $profile)
    {
        try {
            $user = $profile->user;

            if (!$user) {
                return $this->error(
                    'User not found for this profile.',
                    404
                );
            }

            $this->avatarService->uploadAvatar(
                $user,
                $request->file('avatar')
            );

            // Reload user to get the new avatar
            $user->refresh();

            return $this->success(
                new UserResource($user),
                'Avatar uploaded successfully.'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error(
                $e->getMessage(),
                422
            );
        }
    }

    /**
     * Delete avatar for a profile.
     *
     * @param Profile $profile
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAvatar(Profile $profile)
    {
        $user = $profile->user;

        if (!$user) {
            return $this->error(
                'User not found for this profile.',
                404
            );
        }

        $deleted = $this->avatarService->deleteAvatar($user);

        if (!$deleted) {
            return $this->error(
                'Avatar not found or already deleted.',
                404
            );
        }

        // Reload user
        $user->refresh();

        return $this->success(
            new UserResource($user),
            'Avatar deleted successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Role Management
    |--------------------------------------------------------------------------
    */

    /**
     * Get all roles assigned to a profile.
     *
     * @param Profile $profile
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRoles(Profile $profile)
    {
        $roles = $this->roleService->getProfileRoles($profile);

        return $this->success(
            RoleResource::collection($roles),
            'Profile roles retrieved successfully.'
        );
    }

    /**
     * Assign a role to a profile.
     *
     * @param AssignRoleRequest $request
     * @param Profile $profile
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignRole(AssignRoleRequest $request, Profile $profile)
    {
        $result = $this->roleService->assignRole(
            $profile,
            $request->validated()['role_id']
        );

        if (!$result['success']) {
            return $this->error(
                $result['message'],
                400
            );
        }

        return $this->success(
            [
                'profile' => new ProfileResource($profile->load('roles')),
                'role' => new RoleResource($result['role']),
            ],
            $result['message']
        );
    }

    /**
     * Remove a role from a profile.
     *
     * @param Profile $profile
     * @param int $roleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeRole(Profile $profile, int $roleId)
    {
        $result = $this->roleService->removeRole($profile, $roleId);

        if (!$result['success']) {
            return $this->error(
                $result['message'],
                400
            );
        }

        return $this->success(
            new ProfileResource($profile->load('roles')),
            $result['message']
        );
    }
}
