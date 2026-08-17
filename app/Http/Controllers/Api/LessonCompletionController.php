<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonCompletionResource;
use App\Models\Lesson;
use App\Services\LessonCompletionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LessonCompletionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LessonCompletionService $lessonCompletionService
    ) {}

    /**
     * Mark a lesson as completed for the authenticated user.
     */
    public function complete(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        if (!$profile) {
            throw ValidationException::withMessages([
                'profile' => ['User profile tidak ditemukan.'],
            ]);
        }

        // Mark lesson as complete (idempotent)
        $completion = $this->lessonCompletionService->markAsComplete($lesson, $profile);

        return $this->success(
            new LessonCompletionResource($completion),
            'Lesson marked as completed.',
            200
        );
    }
}
