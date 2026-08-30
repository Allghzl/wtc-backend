<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonCompletionResource;
use App\Models\Certificate;
use App\Models\Lesson;
use App\Services\AchievementService;
use App\Services\CertificateService;
use App\Services\LessonCompletionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LessonCompletionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LessonCompletionService $lessonCompletionService,
        protected \App\Services\PointService $pointService,
        protected CertificateService $certificateService,
        protected AchievementService $achievementService
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

        // Award points for lesson completion (if not already awarded)
        $pointLog = $this->pointService->awardLessonCompletionPoints(
            $profile,
            $lesson->id,
            $lesson->title
        );

        // Certificate + achievement hooks
        $this->handleTrackCompletion($profile, $lesson);

        return $this->success(
            new LessonCompletionResource($completion),
            'Lesson marked as completed.',
            200
        );
    }

    /**
     * After marking a lesson complete, check whether the entire track is now
     * finished for this profile. Issue or upgrade the certificate accordingly,
     * and fire the track_complete achievement trigger.
     */
    private function handleTrackCompletion(\App\Models\Profile $profile, Lesson $lesson): void
    {
        try {
            // Walk up: lesson -> module -> track
            $lesson->loadMissing('module.track');
            $track = $lesson->module?->track;

            if (!$track) {
                return;
            }

            // Collect every lesson ID belonging to this track
            $track->loadMissing('modules.lessons');
            $allLessonIds = $track->modules
                ->flatMap(fn ($m) => $m->lessons->pluck('id'))
                ->unique();

            if ($allLessonIds->isEmpty()) {
                return;
            }

            // Count how many of those lessons this profile has completed
            $completedCount = $profile->lessonCompletions()
                ->whereIn('lesson_id', $allLessonIds)
                ->count();

            if ($completedCount < $allLessonIds->count()) {
                // Track not yet fully complete
                return;
            }

            // All lessons done — issue or upgrade certificate
            $existingCert = Certificate::where('profile_id', $profile->id)
                ->where('track_id', $track->id)
                ->first();

            if (!$existingCert) {
                $this->certificateService->issue($profile, $track);
            } else {
                $this->certificateService->checkForUpgrade($profile, $track);
            }

            // Fire achievement trigger
            $this->achievementService->checkAndAward(
                $profile,
                'track_complete',
                ['track_id' => $track->id]
            );
        } catch (\Throwable $e) {
            // Never let certificate logic break lesson completion
            Log::error('Certificate/achievement hook failed in LessonCompletionController', [
                'error'      => $e->getMessage(),
                'profile_id' => $profile->id,
                'lesson_id'  => $lesson->id,
            ]);
        }
    }
}
