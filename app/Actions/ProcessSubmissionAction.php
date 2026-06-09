<?php

namespace App\Actions;

use App\Models\Submission;
use App\Models\Challenge;
use App\Models\User;

class ProcessSubmissionAction
{
    public function execute(User $user, Challenge $challenge, array $data, $file = null): Submission
    {
        $filePath = null;

        if ($file) {
            $filePath = $file->store('submissions', 'public');
        }

        $submission = Submission::create([
            'challenge_id'      => $challenge->id,
            'user_id'           => $user->id,
            'status'            => 'pending',
            'submitted_content' => $data['submitted_content'] ?? null,
            'file_path'         => $filePath,
        ]);

        // Next for auto_scoring
        // if ($challenge->type === 'multiple_choice') {
        //     $this->calculateMultipleChoice($submission, $challenge);
        // }

        return $submission;
    }
}
