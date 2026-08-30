<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeacherDashboardResource;
use App\Http\Resources\TeacherSubmissionResource;
use App\Services\TeacherDashboardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TeacherDashboardService $service
    ) {}

    /**
     * GET /api/teacher/dashboard
     *
     * Returns stats, pending submission queue preview, and leaderboard preview.
     */
    public function dashboard(): JsonResponse
    {
        $data = $this->service->dashboard();

        return $this->success(
            new TeacherDashboardResource($data),
            'Teacher dashboard retrieved successfully.'
        );
    }

    /**
     * GET /api/teacher/submissions
     *
     * Returns a paginated list of all submissions, with optional filters:
     *   status, challenge_id, profile_id, page, per_page.
     */
    public function submissions(Request $request): JsonResponse
    {
        $filters = $request->only([
            'status',
            'challenge_id',
            'profile_id',
            'page',
            'per_page',
        ]);

        $paginator = $this->service->submissions($filters);

        return $this->successWithPagination(
            TeacherSubmissionResource::collection($paginator->items()),
            'Submissions retrieved successfully.',
            $paginator
        );
    }
}
