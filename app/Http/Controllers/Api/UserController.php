<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Role;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
}
