<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrackEnrollRequest;
use App\Http\Resources\MyTrackResource;
use App\Http\Resources\TrackEnrollmentResource;
use App\Http\Resources\TrackProgressResource;
use App\Models\Track;
use App\Services\EnrollmentService;
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
        protected ProgressService $progressService
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
}
