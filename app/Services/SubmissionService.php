<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\Profile;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubmissionService
{
    /**
     * Get all student submissions for a challenge.
     *
     * Returns a structured list of students with their submission attempts.
     */
    public function getChallengeSubmissions(Challenge $challenge): array
    {
        $students = Profile::query()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'student');
            })
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'admin');
            })
            ->with([
                'user',
                'submissions' => function ($query) use ($challenge) {
                    $query
                        ->where('challenge_id', $challenge->id)
                        ->orderByDesc('attempt_number');
                },
            ])
            ->get()
            ->sort(function (Profile $a, Profile $b) {
                $aHasSubmission = $a->submissions->isNotEmpty();
                $bHasSubmission = $b->submissions->isNotEmpty();

                if ($aHasSubmission !== $bHasSubmission) {
                    return $aHasSubmission ? -1 : 1;
                }

                return strcasecmp(
                    $a->display_name,
                    $b->display_name
                );
            })
            ->values()
            ->map(function (Profile $profile) {
                $submissions = $profile->submissions;

                return [
                    'profile' => [
                        'id' => $profile->id,
                        'display_name' => $profile->display_name,
                        'email' => $profile->user?->email,
                        'avatar_key' => $profile->user?->avatar_key,
                    ],

                    'submission_count' => $submissions->count(),

                    'status' => $submissions->isEmpty()
                        ? 'not_submitted'
                        : 'submitted',

                    'attempts' => $submissions
                        ->map(function (Submission $submission) {
                            $totalScore =
                                ($submission->auto_score ?? 0)
                                + ($submission->manual_score ?? 0);

                            return [
                                'id' => $submission->id,
                                'attempt_number' => $submission->attempt_number,
                                'status' => $submission->status,

                                'score' => (
                                    $submission->auto_score !== null
                                    || $submission->manual_score !== null
                                )
                                    ? $totalScore
                                    : null,

                                'submitted_at' => $submission->submitted_at,
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();

        return [
            'challenge' => [
                'id' => $challenge->id,
                'title' => $challenge->title,
                'slug' => $challenge->slug,
                'type' => $challenge->type,
                'max_score' => $challenge->max_score,
                'allowed_attempts' => $challenge->allowed_attempts,
            ],

            'students' => $students,
        ];
    }

    /**
     * Get submissions for the authenticated user for a specific challenge.
     */
    public function getMySubmissions(Challenge $challenge, User $user): Collection
    {
        $profile = $user->profile;

        if (!$profile) {
            throw ValidationException::withMessages([
                'profile' => ['User profile tidak ditemukan.'],
            ]);
        }

        return Submission::query()
            ->where('challenge_id', $challenge->id)
            ->where('profile_id', $profile->id)
            ->orderByDesc('attempt_number')
            ->get();
    }

    /**
     * Update a submission with grading information.
     */
    public function updateSubmission(Submission $submission, array $data): Submission
    {
        $submission->update([
            'manual_score' => $data['manual_score'] ?? $submission->manual_score,
            'feedback' => $data['feedback'] ?? $submission->feedback,
            'status' => $data['status'] ?? $submission->status,
        ]);

        return $submission->fresh();
    }

    /**
     * Generate a temporary download URL for a submission file.
     */
    public function generateFileUrl(Submission $submission): array
    {
        if (!$submission->file_path) {
            throw ValidationException::withMessages([
                'file' => [
                    'Submission ini tidak memiliki file.',
                ],
            ]);
        }

        $disk = Storage::disk('s3');

        if (!$disk->exists($submission->file_path)) {
            throw ValidationException::withMessages([
                'file' => [
                    'File submission tidak ditemukan.',
                ],
            ]);
        }

        $expiresAt = now()->addMinutes(10);

        $url = $disk->temporaryUrl(
            $submission->file_path,
            $expiresAt
        );

        return [
            'name' => basename($submission->file_path),
            'url' => $url,
            'expires_at' => $expiresAt,
        ];
    }
}
