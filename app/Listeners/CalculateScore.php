<?php

namespace App\Listeners;

use App\Events\SubmissionCreated;
use App\Models\ChallengeAttempt;

class CalculateScore
{
    public function handle(SubmissionCreated $event): void
    {
        $submission = $event->submission;
        $attemptNumber = ChallengeAttempt::where('user_id', $submission->user_id)
            ->where('challenge_id', $submission->challenge_id)
            ->count() + 1;

        ChallengeAttempt::create([
            'user_id' => $submission->user_id,
            'challenge_id' => $submission->challenge_id,
            'submission_id' => $submission->id,
            'attempt_number' => $attemptNumber,
            'score' => $submission->auto_score,
            'status' => $submission->status,
        ]);
    }
}