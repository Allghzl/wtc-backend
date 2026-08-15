<?php

namespace App\Http\Controllers\Api;

use App\Actions\ProcessSubmissionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmissionStoreRequest;
use App\Http\Requests\SubmissionUpdateRequest;
use App\Http\Resources\SubmissionResource;
use App\Models\Challenge;
use App\Models\Submission;
use App\Services\SubmissionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubmissionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SubmissionService $submissionService
    ) {}

    /**
     * Get all student submissions for a challenge.
     *
     * Returns a lightweight summary for every student.
     */
    public function index(Challenge $challenge): JsonResponse
    {
        $data = $this->submissionService->getChallengeSubmissions($challenge);

        return response()->json([
            'success' => true,
            'message' => 'Challenge submissions retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Store a newly created submission.
     */
    public function store(
        SubmissionStoreRequest $request,
        ProcessSubmissionAction $action,
        Challenge $challenge
    ): JsonResponse {
        $submission = $action->execute(
            $request->user(),
            $challenge,
            $request->validated(),
            $request->file('file')
        );

        return $this->success(
            new SubmissionResource($submission),
            'submitted successfully.',
            201
        );
    }

    /**
     * Get authenticated user's submissions for a challenge.
     */
    public function mySubmissions(
        Challenge $challenge,
        Request $request
    ): JsonResponse {
        $submissions = $this->submissionService->getMySubmissions(
            $challenge,
            $request->user()
        );

        return $this->success(
            SubmissionResource::collection($submissions),
            'Your submissions retrieved successfully.'
        );
    }

    /**
     * Get a single submission in detail.
     */
    public function show(Submission $submission, Request $request): JsonResponse
    {
        $user = $request->user();

        // Authorization: Admin/Teacher can view all, students can only view their own
        $isAdmin = $user->hasRole('admin') || $user->hasRole('teacher');
        $isOwner = $user->profile && $user->profile->id === $submission->profile_id;

        if (!$isAdmin && !$isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to access this submission.',
            ], 403);
        }

        $submission->load([
            'profile.user',
            'challenge',
        ]);

        $totalScore =
            ($submission->auto_score ?? 0)
            + ($submission->manual_score ?? 0);

        $file = null;

        if ($submission->file_path) {
            $disk = Storage::disk('s3');

            $file = [
                'name' => basename($submission->file_path),
                'available' => $disk->exists($submission->file_path),
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Submission retrieved successfully.',

            'data' => [
                'submission' => [
                    'id' => $submission->id,
                    'attempt_number' => $submission->attempt_number,
                    'status' => $submission->status,
                    'submitted_at' => $submission->submitted_at,

                    'profile' => [
                        'id' => $submission->profile->id,
                        'display_name' => $submission->profile->display_name,
                        'email' => $submission->profile->user?->email,
                        'avatar' => $submission->profile->user?->avatar
                            ? app(\App\Services\AvatarService::class)->generateAvatarUrl($submission->profile->user)
                            : null,
                    ],

                    'challenge' => [
                        'id' => $submission->challenge->id,
                        'title' => $submission->challenge->title,
                        'slug' => $submission->challenge->slug,
                        'type' => $submission->challenge->type,
                        'max_score' => $submission->challenge->max_score,
                    ],

                    'submitted_content' => $submission->submitted_content,

                    'file' => $file,

                    'score' => [
                        'auto' => $submission->auto_score,
                        'manual' => $submission->manual_score,
                        'total' => $totalScore,
                    ],

                    'feedback' => $submission->feedback,
                ],
            ],
        ]);
    }

    /**
     * Update / grade a submission.
     */
    public function update(
        SubmissionUpdateRequest $request,
        Submission $submission
    ): JsonResponse {
        $user = $request->user();

        // Authorization: Only admins and teachers can grade submissions
        if (!($user->hasRole('admin') || $user->hasRole('teacher'))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this submission.',
            ], 403);
        }

        $submission = $this->submissionService->updateSubmission(
            $submission,
            $request->validated()
        );

        $totalScore =
            ($submission->auto_score ?? 0)
            + ($submission->manual_score ?? 0);

        return response()->json([
            'success' => true,
            'message' => 'Submission updated successfully.',

            'data' => [
                'submission' => [
                    'id' => $submission->id,
                    'attempt_number' => $submission->attempt_number,
                    'status' => $submission->status,
                    'submitted_at' => $submission->submitted_at,

                    'score' => [
                        'auto' => $submission->auto_score,
                        'manual' => $submission->manual_score,
                        'total' => $totalScore,
                    ],

                    'feedback' => $submission->feedback,
                ],
            ],
        ]);
    }

    /**
     * Generate a temporary download URL for a submission file.
     */
    public function file(Submission $submission, Request $request): JsonResponse
    {
        $user = $request->user();

        // Authorization: Admin/Teacher can download all, students can only download their own
        $isAdmin = $user->hasRole('admin') || $user->hasRole('teacher');
        $isOwner = $user->profile && $user->profile->id === $submission->profile_id;

        if (!$isAdmin && !$isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to access this file.',
            ], 403);
        }

        $fileData = $this->submissionService->generateFileUrl($submission);

        return response()->json([
            'success' => true,
            'message' => 'Submission file URL generated successfully.',
            'data' => [
                'file' => $fileData,
            ],
        ]);
    }
}
