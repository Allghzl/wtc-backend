<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\TrackResource;
use App\Http\Resources\ModuleResource;
use App\Http\Resources\LessonResource;
use App\Http\Resources\ChallengeResource;
use App\Models\Track;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Challenge;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use OwenIt\Auditing\Models\Audit;

class AuditLogController extends Controller
{
    use ApiResponse;

    /**
     * Get audit logs for a specific track.
     *
     * @param Track $track
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function trackAuditLog(Track $track, Request $request)
    {
        $perPage = min($request->input('per_page', 15), 100);

        $audits = Audit::where('auditable_type', Track::class)
            ->where('auditable_id', $track->id)
            ->with('user.profile')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->success([
            'audit_logs' => AuditLogResource::collection($audits->items()),
            'pagination' => [
                'current_page' => $audits->currentPage(),
                'per_page' => $audits->perPage(),
                'total' => $audits->total(),
                'last_page' => $audits->lastPage(),
            ],
        ], 'Track audit logs retrieved successfully.');
    }

    /**
     * Get audit logs for a specific module.
     *
     * @param Module $module
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function moduleAuditLog(Module $module, Request $request)
    {
        $perPage = min($request->input('per_page', 15), 100);

        $audits = Audit::where('auditable_type', Module::class)
            ->where('auditable_id', $module->id)
            ->with('user.profile')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->success([
            'audit_logs' => AuditLogResource::collection($audits->items()),
            'pagination' => [
                'current_page' => $audits->currentPage(),
                'per_page' => $audits->perPage(),
                'total' => $audits->total(),
                'last_page' => $audits->lastPage(),
            ],
        ], 'Module audit logs retrieved successfully.');
    }

    /**
     * Get audit logs for a specific lesson.
     *
     * @param Lesson $lesson
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function lessonAuditLog(Lesson $lesson, Request $request)
    {
        $perPage = min($request->input('per_page', 15), 100);

        $audits = Audit::where('auditable_type', Lesson::class)
            ->where('auditable_id', $lesson->id)
            ->with('user.profile')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->success([
            'audit_logs' => AuditLogResource::collection($audits->items()),
            'pagination' => [
                'current_page' => $audits->currentPage(),
                'per_page' => $audits->perPage(),
                'total' => $audits->total(),
                'last_page' => $audits->lastPage(),
            ],
        ], 'Lesson audit logs retrieved successfully.');
    }

    /**
     * Get audit logs for a specific challenge.
     *
     * @param Challenge $challenge
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function challengeAuditLog(Challenge $challenge, Request $request)
    {
        $perPage = min($request->input('per_page', 15), 100);

        $audits = Audit::where('auditable_type', Challenge::class)
            ->where('auditable_id', $challenge->id)
            ->with('user.profile')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->success([
            'audit_logs' => AuditLogResource::collection($audits->items()),
            'pagination' => [
                'current_page' => $audits->currentPage(),
                'per_page' => $audits->perPage(),
                'total' => $audits->total(),
                'last_page' => $audits->lastPage(),
            ],
        ], 'Challenge audit logs retrieved successfully.');
    }

    /**
     * Get all audit logs with filters.
     *
     * Query Parameters:
     * - auditable_type: Filter by model type (e.g., App\Models\Track)
     * - auditable_id: Filter by specific model ID
     * - profile_id: Filter by profile who performed the action
     * - action: Filter by event type (created, updated, deleted, restored)
     * - date_from: Filter by start date
     * - date_to: Filter by end date
     * - per_page: Items per page (default: 15, max: 100)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $request->validate([
            'auditable_type' => ['nullable', 'in:' . implode(',', [
                Track::class,
                Module::class,
                Lesson::class,
                Challenge::class,
            ])],
        ]);

        $perPage = min($request->input('per_page', 15), 100);

        $query = Audit::with('user.profile.roles')
            ->whereIn('auditable_type', [
                Track::class,
                Module::class,
                Lesson::class,
                Challenge::class,
            ]);

        // Filter by auditable type
        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->input('auditable_type'));
        }

        // Filter by auditable ID
        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', $request->input('auditable_id'));
        }

        // Filter by profile (user who performed the action)
        if ($request->filled('profile_id')) {
            $query->whereHas('user.profile', function ($q) use ($request) {
                $q->where('id', $request->input('profile_id'));
            });
        }

        // Filter by action/event
        if ($request->filled('action')) {
            $query->where('event', $request->input('action'));
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        // Order by most recent first
        $query->orderBy('created_at', 'desc');

        $audits = $query->paginate($perPage);

        return $this->success([
            'audit_logs' => AuditLogResource::collection($audits->items()),
            'pagination' => [
                'current_page' => $audits->currentPage(),
                'per_page' => $audits->perPage(),
                'total' => $audits->total(),
                'last_page' => $audits->lastPage(),
            ],
        ], 'Audit logs retrieved successfully.');
    }

    /**
     * Restore a soft-deleted track (admin only).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restoreTrack(int $id)
    {
        $track = Track::onlyTrashed()->find($id);

        if (!$track) {
            return $this->error('Track not found or not deleted.', 404);
        }

        $track->restore();

        return $this->success(
            new TrackResource($track->fresh()),
            'Track restored successfully.'
        );
    }

    /**
     * Restore a soft-deleted module (admin only).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restoreModule(int $id)
    {
        $module = Module::onlyTrashed()->find($id);

        if (!$module) {
            return $this->error('Module not found or not deleted.', 404);
        }

        $module->restore();

        return $this->success(
            new ModuleResource($module->fresh()),
            'Module restored successfully.'
        );
    }

    /**
     * Restore a soft-deleted lesson (admin only).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restoreLesson(int $id)
    {
        $lesson = Lesson::onlyTrashed()->find($id);

        if (!$lesson) {
            return $this->error('Lesson not found or not deleted.', 404);
        }

        $lesson->restore();

        return $this->success(
            new LessonResource($lesson->fresh()),
            'Lesson restored successfully.'
        );
    }

    /**
     * Restore a soft-deleted challenge (admin only).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restoreChallenge(int $id)
    {
        $challenge = Challenge::onlyTrashed()->find($id);

        if (!$challenge) {
            return $this->error('Challenge not found or not deleted.', 404);
        }

        $challenge->restore();

        return $this->success(
            new ChallengeResource($challenge->fresh()),
            'Challenge restored successfully.'
        );
    }

    /**
     * List soft-deleted tracks (admin only).
     */
    public function trashedTracks(Request $request): JsonResponse
    {
        $items = Track::onlyTrashed()->orderByDesc('deleted_at')->paginate(20);
        return $this->successWithPagination(
            TrackResource::collection($items),
            'Trashed tracks retrieved successfully.',
            $items
        );
    }

    /**
     * List soft-deleted modules (admin only).
     */
    public function trashedModules(Request $request): JsonResponse
    {
        $items = Module::onlyTrashed()->orderByDesc('deleted_at')->paginate(20);
        return $this->successWithPagination(
            ModuleResource::collection($items),
            'Trashed modules retrieved successfully.',
            $items
        );
    }

    /**
     * List soft-deleted lessons (admin only).
     */
    public function trashedLessons(Request $request): JsonResponse
    {
        $items = Lesson::onlyTrashed()->orderByDesc('deleted_at')->paginate(20);
        return $this->successWithPagination(
            LessonResource::collection($items),
            'Trashed lessons retrieved successfully.',
            $items
        );
    }

    /**
     * List soft-deleted challenges (admin only).
     */
    public function trashedChallenges(Request $request): JsonResponse
    {
        $items = Challenge::onlyTrashed()->orderByDesc('deleted_at')->paginate(20);
        return $this->successWithPagination(
            ChallengeResource::collection($items),
            'Trashed challenges retrieved successfully.',
            $items
        );
    }
}
