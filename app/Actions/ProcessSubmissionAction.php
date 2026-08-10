<?php

namespace App\Actions;

use App\Models\Challenge;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProcessSubmissionAction
{
    public function execute(
        User $user,
        Challenge $challenge,
        array $data,
        ?UploadedFile $file = null,
    ): Submission {
        $profile = $user->profile;

        if (!$profile) {
            throw ValidationException::withMessages([
                'profile' => ['User profile tidak ditemukan.'],
            ]);
        }

        return DB::transaction(function () use (
            $profile,
            $challenge,
            $data,
            $file
        ) {
            /*
            |--------------------------------------------------------------------------
            | Check Attempt Limit
            |--------------------------------------------------------------------------
            */

            $attemptCount = Submission::query()
                ->where('profile_id', $profile->id)
                ->where('challenge_id', $challenge->id)
                ->count();

            $allowedAttempts = $challenge->allowed_attempts ?? 1;

            if ($attemptCount >= $allowedAttempts) {
                throw ValidationException::withMessages([
                    'submission' => [
                        "Kamu sudah mencapai batas maksimal percobaan ({$allowedAttempts}).",
                    ],
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Determine Attempt Number
            |--------------------------------------------------------------------------
            */

            $attemptNumber = $attemptCount + 1;

            /*
            |--------------------------------------------------------------------------
            | Upload File
            |--------------------------------------------------------------------------
            */

            $filePath = null;

            if ($file) {
                $filename =
                    File::name($file->getClientOriginalName())
                    . '_' . time()
                    . '.' . $file->getClientOriginalExtension();

                $filePath = Storage::disk('s3')->putFileAs(
                    "{$profile->id}/submissions",
                    $file,
                    $filename
                );

                if (!$filePath) {
                    throw ValidationException::withMessages([
                        'file' => ['File gagal di-upload.'],
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Create Submission
            |--------------------------------------------------------------------------
            */

            return Submission::create([
                'challenge_id' => $challenge->id,
                'profile_id' => $profile->id,
                'attempt_number' => $attemptNumber,
                'status' => 'pending',
                'submitted_at' => now(),
                'submitted_content' => $data['submitted_content'] ?? null,
                'file_path' => $filePath,
            ]);
        });
    }
}
