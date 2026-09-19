<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Role;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    use ApiResponse;

    /**
     * Display a paginated listing of users with search, filter, and sort capabilities.
     *
     * Query Parameters:
     * - page: Page number (default: 1)
     * - per_page: Items per page (default: 15, max: 100)
     * - search: Search by name or email
     * - role: Filter by role name or role ID
     * - study_class_id: Filter by study class
     * - sort_by: Sort field (name, email, created_at, last_login_at)
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
        $allowedSortFields = ['name', 'email', 'created_at'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }

        // Validate sort order
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        $query = User::with(['profile.roles', 'profile.studyClass']);

        // Search by name or email
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($roleFilter) {
            $query->whereHas('profile.roles', function ($q) use ($roleFilter) {
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
            $query->whereHas('profile', function ($q) use ($studyClassFilter) {
                $q->where('study_class_id', $studyClassFilter);
            });
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);

        // Paginate results
        $users = $query->paginate($perPage);

        return $this->success([
            'users' => UserResource::collection($users->items()),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
        ], 'Users retrieved successfully.');
    }

    /**
     * Display the specified user with all relationships.
     *
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(User $user)
    {
        $user->load([
            'profile.roles',
            'profile.achievements',
            'profile.studyClass',
        ]);

        return $this->success(
            new UserResource($user),
            'User retrieved successfully.'
        );
    }

    /**
     * Get user statistics for dashboard/analytics.
     *
     * Returns:
     * - total_users: Total number of users
     * - users_by_role: Count of users per role
     * - new_users_this_month: Users created this month
     * - active_users: Users who logged in within the last 30 days
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        // Total users
        $totalUsers = User::count();

        // Users by role
        $roles = Role::withCount([
            'profiles as user_count' => function ($query) {
                $query->whereHas('user');
            }
        ])->get();

        $usersByRole = $roles->map(function ($role) {
            return [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'count' => $role->user_count,
            ];
        });

        // New users this month
        $startOfMonth = now()->startOfMonth();
        $newUsersThisMonth = User::where('created_at', '>=', $startOfMonth)->count();

        // Active users (logged in within last 30 days)
        $thirtyDaysAgo = now()->subDays(30);
        $activeUsers = User::whereHas('profile', function ($query) use ($thirtyDaysAgo) {
            $query->where('last_login_at', '>=', $thirtyDaysAgo);
        })->count();

        // Users without roles
        $usersWithoutRoles = User::whereHas('profile', function ($query) {
            $query->doesntHave('roles');
        })->count();

        return $this->success([
            'total_users' => $totalUsers,
            'users_by_role' => $usersByRole,
            'new_users_this_month' => $newUsersThisMonth,
            'active_users' => $activeUsers,
            'users_without_roles' => $usersWithoutRoles,
        ], 'User statistics retrieved successfully.');
    }

    /**
     * Permanently delete a user along with their profile and all associated data.
     *
     * This performs a hard delete. The following will be removed:
     * - Avatar from object storage
     * - Submission files from object storage
     * - Certificate PDFs from object storage
     * - Profile and all its relations (roles, achievements, point logs,
     *   track enrollments, lesson completions, submissions, certificates)
     * - Auth tokens and sessions
     * - User account
     *
     * DB cascade handles child records automatically once the user is deleted.
     * S3 files are cleaned up manually before the DB delete.
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, User $user)
    {
        // Prevent admin from deleting their own account
        if ($request->user()->id === $user->id) {
            return $this->error('You cannot delete your own account.', 403);
        }

        // Eager-load everything we need to clean up from object storage.
        // withTrashed() on submissions so soft-deleted files are also removed.
        $user->load([
            'profile.submissions' => fn ($q) => $q->withTrashed()->whereNotNull('file_path'),
            'profile.certificates'  => fn ($q) => $q->whereNotNull('pdf_path'),
        ]);

        $deletedName = $user->name;

        DB::transaction(function () use ($user) {
            $s3 = Storage::disk('s3');
            $pathsToDelete = [];

            if ($user->profile) {
                $profile = $user->profile;

                // 1. Avatar
                if ($user->avatar) {
                    $pathsToDelete[] = $user->avatar;
                }

                // 2. Submission files
                foreach ($profile->submissions as $submission) {
                    if ($submission->file_path) {
                        $pathsToDelete[] = $submission->file_path;
                    }
                }

                // 3. Certificate PDFs
                foreach ($profile->certificates as $certificate) {
                    if ($certificate->pdf_path) {
                        $pathsToDelete[] = $certificate->pdf_path;
                    }
                }
            }

            // Delete all collected S3 files in one batch (graceful — same pattern as AvatarService)
            if (!empty($pathsToDelete)) {
                try {
                    $s3->delete($pathsToDelete);
                } catch (\Exception $e) {
                    // S3 not configured or error — log and continue.
                    // DB records must still be removed.
                    report($e);
                }
            }

            // Revoke all Sanctum tokens
            $user->tokens()->delete();

            // Delete user — DB cascade handles:
            // profiles, profile_roles, profile_achievements, profile_achievements,
            // submissions, certificates, track_enrollments, lesson_completions,
            // point_logs, sessions
            $user->delete();
        });

        return $this->success(
            ['deleted_user' => $deletedName],
            "User \"{$deletedName}\" and all associated data have been permanently deleted."
        );
    }
}
