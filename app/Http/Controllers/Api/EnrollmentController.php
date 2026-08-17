<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrackEnrollRequest;
use App\Http\Resources\MyTrackResource;
use App\Http\Resources\TrackEnrollmentResource;
use App\Http\Resources\TrackProgressResource;
use App\Models\Track;
use App\Services\DashboardService;
use App\Services\EnrollmentService;
use App\Services\LearningStateService;
use App\Services\ProgressService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected EnrollmentService $enrollmentService,
        protected ProgressService $progressService,
        protected DashboardService $dashboardService,
        protected LearningStateService $learningStateService
    ) {}

    /**
     * Enroll the authenticated user in a track.
     */
    public function enroll(TrackEnrollRequest $request, Track $track): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        if (!$profile) {
            throw ValidationException::withMessages([
                'profile' => ['User profile tidak ditemukan.'],
            ]);
        }

        try {
            $enrollment = $this->enrollmentService->enroll($profile, $track);

            return $this->success(
                new TrackEnrollmentResource($enrollment->load('track')),
                'Berhasil mendaftar ke track.',
                201
            );
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }
    }

    /**
     * Unenroll the authenticated user from a track.
     */
    public function unenroll(Request $request, Track $track): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        if (!$profile) {
            throw ValidationException::withMessages([
                'profile' => ['User profile tidak ditemukan.'],
            ]);
        }

        try {
            $this->enrollmentService->unenroll($profile, $track);

            return $this->success(
                null,
                'Berhasil keluar dari track.'
            );
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }
    }

    /**
     * Get all tracks the authenticated user is enrolled in.
     */
    public function myTracks(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        if (!$profile) {
            throw ValidationException::withMessages([
                'profile' => ['User profile tidak ditemukan.'],
            ]);
        }

        $tracks = $profile->enrolledTracks()
            ->where('track_enrollments.status', 'active')
            ->withCount('modules')
            ->orderBy('track_enrollments.enrolled_at', 'desc')
            ->get();

        if ($tracks->isEmpty()) {
            return $this->error('Anda belum mendaftar ke track manapun.', 404);
        }

        return $this->success(
            MyTrackResource::collection($tracks),
            'Daftar track yang Anda ikuti.'
        );
    }

    /**
     * Get the authenticated user's enrollment status for a specific track.
     */
    public function getEnrollment(Request $request, Track $track): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        if (!$profile) {
            throw ValidationException::withMessages([
                'profile' => ['User profile tidak ditemukan.'],
            ]);
        }

        $enrollment = $this->enrollmentService->getEnrollment($profile, $track);

        if (!$enrollment) {
            return $this->error('Anda belum mendaftar ke track ini.', 404);
        }

        return $this->success(
            new TrackEnrollmentResource($enrollment->load('track')),
            'Status enrollment Anda.'
        );
    }

    /**
     * Get learning progress for all tracks the authenticated user is enrolled in.
     */
    public function myProgress(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        if (!$profile) {
            throw ValidationException::withMessages([
                'profile' => ['User profile tidak ditemukan.'],
            ]);
        }

        $enrollments = $profile->trackEnrollments()
            ->where('status', 'active')
            ->with('track')
            ->get();

        if ($enrollments->isEmpty()) {
            return $this->error('Anda belum mendaftar ke track manapun.', 404);
        }

        $progressData = $enrollments->map(function ($enrollment) use ($profile) {
            $track = $enrollment->track;
            $progress = $this->progressService->getTrackProgress($track, $profile);

            return [
                'track' => $track,
                'enrollment' => $enrollment,
                'progress' => $progress,
                'modules' => $progress['modules'],
            ];
        });

        return $this->success(
            TrackProgressResource::collection($progressData),
            'Progress belajar Anda.'
        );
    }

    /**
     * Get detailed learning progress for a specific enrolled track.
     */
    public function trackProgress(Request $request, Track $track): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        if (!$profile) {
            throw ValidationException::withMessages([
                'profile' => ['User profile tidak ditemukan.'],
            ]);
        }

        // Verify enrollment
        $enrollment = $this->enrollmentService->getEnrollment($profile, $track);

        if (!$enrollment) {
            return $this->error(
                'Anda belum mendaftar ke track ini.',
                403
            );
        }

        // Calculate progress
        $progress = $this->progressService->getTrackProgress($track, $profile);

        $progressData = [
            'track' => $track,
            'enrollment' => $enrollment,
            'progress' => $progress,
            'modules' => $progress['modules'],
        ];

        return $this->success(
            new TrackProgressResource($progressData),
            'Progress track Anda.'
        );
    }

    /**
     * Get comprehensive track overview for enrolled student.
     * Returns track info, enrollment, progress, modules with lessons and learning states.
     * Replaces multiple API calls with one aggregated response.
     */
    public function trackOverview(Request $request, Track $track): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        if (!$profile) {
            throw ValidationException::withMessages([
                'profile' => ['User profile tidak ditemukan.'],
            ]);
        }

        // Verify active enrollment
        $enrollment = $this->enrollmentService->getEnrollment($profile, $track);

        if (!$enrollment) {
            return $this->error(
                'Anda belum mendaftar ke track ini.',
                403
            );
        }

        if ($enrollment->status !== 'active') {
            return $this->error(
                'Enrollment Anda tidak aktif.',
                403
            );
        }

        // Eager load all data to avoid N+1 queries
        $track->load([
            'modules' => function ($query) {
                $query->orderBy('order')
                    ->with([
                        'lessons' => function ($q) {
                            $q->orderBy('order')->with('challenges');
                        },
                        'challenges' => function ($q) {
                            $q->whereNull('lesson_id')->orderBy('order');
                        }
                    ]);
            }
        ]);

        // Batch load lesson completions for this profile
        $allLessonIds = $track->modules->flatMap(fn($m) => $m->lessons->pluck('id'));
        $lessonCompletions = \App\Models\LessonCompletion::query()
            ->where('profile_id', $profile->id)
            ->whereIn('lesson_id', $allLessonIds)
            ->pluck('lesson_id')
            ->flip();

        // Batch load challenge completions for this profile
        $allChallengeIds = $track->modules->flatMap(function($m) {
            return $m->lessons->flatMap(fn($l) => $l->challenges->pluck('id'))
                ->concat($m->challenges->pluck('id'));
        });

        $challengeCompletions = \App\Models\Submission::query()
            ->where('profile_id', $profile->id)
            ->whereIn('challenge_id', $allChallengeIds)
            ->whereIn('status', ['graded', 'reviewed'])
            ->distinct()
            ->pluck('challenge_id')
            ->flip();

        // Calculate track-level progress
        $trackProgress = $this->progressService->getTrackProgress($track, $profile);

        // Build modules array with lessons and learning states
        $modulesData = [];

        foreach ($track->modules as $module) {
            // Calculate module progress
            $moduleProgress = $this->progressService->getModuleProgress($module, $profile);

            // Build lessons array with learning states
            $lessonsData = [];
            foreach ($module->lessons as $lesson) {
                $lessonState = $this->learningStateService->getLessonState($lesson, $profile);

                // Calculate completion using batch-loaded data
                $challengeCount = $lesson->challenges->count();
                if ($challengeCount === 0) {
                    // Lesson without challenges - check explicit completion
                    $isCompleted = $lessonCompletions->has($lesson->id);
                } else {
                    // Lesson with challenges - check if ALL challenges are complete
                    $allChallengesComplete = true;
                    foreach ($lesson->challenges as $challenge) {
                        if (!$challengeCompletions->has($challenge->id)) {
                            $allChallengesComplete = false;
                            break;
                        }
                    }
                    $isCompleted = $allChallengesComplete;
                }

                $lessonsData[] = [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'slug' => $lesson->slug,
                    'description' => $lesson->description,
                    'order' => $lesson->order,
                    'duration' => $lesson->duration,
                    'video_url' => $lesson->video_url,
                    'state' => $lessonState,
                    'completed' => $isCompleted,
                    'challenges_count' => $lesson->challenges->count(),
                ];
            }

            // Build direct challenges array
            $directChallengesData = [];
            foreach ($module->challenges as $challenge) {
                $isCompleted = $challengeCompletions->has($challenge->id);

                $directChallengesData[] = [
                    'id' => $challenge->id,
                    'title' => $challenge->title,
                    'slug' => $challenge->slug,
                    'type' => $challenge->type,
                    'difficulty' => $challenge->difficulty,
                    'order' => $challenge->order,
                    'completed' => $isCompleted,
                ];
            }

            $modulesData[] = [
                'id' => $module->id,
                'title' => $module->title,
                'slug' => $module->slug,
                'description' => $module->description,
                'order' => $module->order,
                'progress' => [
                    'percent' => $moduleProgress['percent'],
                    'completed_lessons' => $moduleProgress['completed_lessons'],
                    'total_lessons' => $moduleProgress['total_lessons'],
                    'completed_challenges' => $moduleProgress['completed_challenges'],
                    'total_challenges' => $moduleProgress['total_challenges'],
                ],
                'lessons' => $lessonsData,
                'direct_challenges' => $directChallengesData,
            ];
        }

        // Build final response
        $data = [
            'track' => [
                'id' => $track->id,
                'title' => $track->title,
                'slug' => $track->slug,
                'description' => $track->description,
                'image_url' => $track->image_url,
            ],
            'enrollment' => [
                'status' => $enrollment->status,
                'enrolled_at' => $enrollment->enrolled_at->toISOString(),
                'completed_at' => $enrollment->completed_at?->toISOString(),
            ],
            'progress' => [
                'percent' => $trackProgress['percent'],
                'completed_lessons' => $trackProgress['completed_lessons'],
                'total_lessons' => $trackProgress['total_lessons'],
                'completed_challenges' => $trackProgress['completed_challenges'],
                'total_challenges' => $trackProgress['total_challenges'],
            ],
            'modules' => $modulesData,
        ];

        return $this->success($data, 'Track overview retrieved successfully.');
    }

    /**
     * Get student dashboard with aggregated learning data.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        if (!$profile) {
            throw ValidationException::withMessages([
                'profile' => ['User profile tidak ditemukan.'],
            ]);
        }

        $dashboardData = $this->dashboardService->getStudentDashboard($profile);

        return $this->success(
            $dashboardData,
            'Dashboard data retrieved successfully.'
        );
    }
}
