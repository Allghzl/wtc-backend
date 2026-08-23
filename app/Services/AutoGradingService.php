<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\Submission;
use Illuminate\Support\Facades\Log;

class AutoGradingService
{
    /**
     * Auto-grade a submission based on challenge type.
     *
     * Returns array with:
     * - 'score' => int (calculated score)
     * - 'status' => string ('graded' or 'pending')
     * - 'feedback' => string|null (optional feedback)
     */
    public function autoGrade(Submission $submission): array
    {
        $challenge = $submission->challenge;

        return match ($challenge->type) {
            'multiple_choice' => $this->gradeMultipleChoice($submission, $challenge),
            'quiz_group' => $this->gradeQuizGroup($submission, $challenge),
            'fill_blank' => $this->gradeFillBlank($submission, $challenge),
            default => [
                'score' => null,
                'status' => 'pending',
                'feedback' => null,
            ],
        };
    }

    /**
     * Grade multiple choice submission.
     *
     * Expected submission content format:
     * {"answers": ["B", "A", "C", "D"]}
     *
     * Expected challenge metadata format:
     * {
     *   "questions": [
     *     {
     *       "question": "What is Laravel?",
     *       "options": [{"key": "A", "text": "..."}, ...],
     *       "answer": "B",
     *       "score": 25
     *     }
     *   ]
     * }
     */
    protected function gradeMultipleChoice(Submission $submission, Challenge $challenge): array
    {
        try {
            // Parse submitted content
            $submittedContent = is_string($submission->submitted_content)
                ? json_decode($submission->submitted_content, true)
                : $submission->submitted_content;

            if (!isset($submittedContent['answers']) || !is_array($submittedContent['answers'])) {
                Log::warning('Invalid submission content format for multiple choice', [
                    'submission_id' => $submission->id,
                    'content' => $submission->submitted_content,
                ]);

                return [
                    'score' => null,
                    'status' => 'pending',
                    'feedback' => 'Invalid submission format.',
                ];
            }

            $submittedAnswers = $submittedContent['answers'];

            // Get questions from challenge metadata
            $metadata = $challenge->metadata;

            if (!isset($metadata['questions']) || !is_array($metadata['questions'])) {
                Log::warning('Invalid challenge metadata format for multiple choice', [
                    'challenge_id' => $challenge->id,
                    'metadata' => $metadata,
                ]);

                return [
                    'score' => null,
                    'status' => 'pending',
                    'feedback' => 'Challenge configuration error.',
                ];
            }

            $questions = $metadata['questions'];

            // Calculate score
            $totalScore = 0;
            $correctCount = 0;
            $totalQuestions = count($questions);

            foreach ($questions as $index => $question) {
                if (!isset($submittedAnswers[$index])) {
                    continue; // Skip if answer not provided
                }

                $submittedAnswer = $submittedAnswers[$index];
                $correctAnswer = $question['answer'] ?? null;
                $questionScore = $question['score'] ?? 0;

                if ($submittedAnswer === $correctAnswer) {
                    $totalScore += $questionScore;
                    $correctCount++;
                }
            }

            // Generate feedback
            $percentage = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100) : 0;
            $feedback = "Jawaban benar: {$correctCount}/{$totalQuestions} ({$percentage}%)";

            // Check passing score
            $passingScore = $challenge->settings['passing_score'] ?? 70;
            if ($percentage >= $passingScore) {
                $feedback .= " - LULUS ✅";
            } else {
                $feedback .= " - BELUM LULUS ❌";
            }

            return [
                'score' => $totalScore,
                'status' => 'graded',
                'feedback' => $feedback,
            ];

        } catch (\Exception $e) {
            Log::error('Error grading multiple choice submission', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'score' => null,
                'status' => 'pending',
                'feedback' => 'Error during grading.',
            ];
        }
    }

    /**
     * Grade quiz group submission.
     *
     * Same format as multiple choice - multiple questions in one challenge.
     */
    protected function gradeQuizGroup(Submission $submission, Challenge $challenge): array
    {
        // Quiz group uses same grading logic as multiple choice
        return $this->gradeMultipleChoice($submission, $challenge);
    }

    /**
     * Grade fill blank submission.
     *
     * Expected submission content format:
     * {"answers": ["Laravel", "Eloquent", "Blade"]}
     *
     * Expected challenge metadata format:
     * {
     *   "blanks": [
     *     {"position": 1, "expected_answer": "Laravel", "score": 10},
     *     {"position": 2, "expected_answer": "Eloquent", "score": 10}
     *   ],
     *   "case_sensitive": false
     * }
     */
    protected function gradeFillBlank(Submission $submission, Challenge $challenge): array
    {
        try {
            // Parse submitted content
            $submittedContent = is_string($submission->submitted_content)
                ? json_decode($submission->submitted_content, true)
                : $submission->submitted_content;

            if (!isset($submittedContent['answers']) || !is_array($submittedContent['answers'])) {
                return [
                    'score' => null,
                    'status' => 'pending',
                    'feedback' => 'Invalid submission format.',
                ];
            }

            $submittedAnswers = $submittedContent['answers'];

            // Get blanks from challenge metadata
            $metadata = $challenge->metadata;

            if (!isset($metadata['blanks']) || !is_array($metadata['blanks'])) {
                return [
                    'score' => null,
                    'status' => 'pending',
                    'feedback' => 'Challenge configuration error.',
                ];
            }

            $blanks = $metadata['blanks'];
            $caseSensitive = $metadata['case_sensitive'] ?? false;

            // Calculate score
            $totalScore = 0;
            $correctCount = 0;
            $totalBlanks = count($blanks);

            foreach ($blanks as $index => $blank) {
                if (!isset($submittedAnswers[$index])) {
                    continue;
                }

                $submittedAnswer = trim($submittedAnswers[$index]);
                $expectedAnswer = trim($blank['expected_answer'] ?? '');
                $blankScore = $blank['score'] ?? 0;

                // Compare answers
                $isCorrect = $caseSensitive
                    ? ($submittedAnswer === $expectedAnswer)
                    : (strcasecmp($submittedAnswer, $expectedAnswer) === 0);

                if ($isCorrect) {
                    $totalScore += $blankScore;
                    $correctCount++;
                }
            }

            // Generate feedback
            $percentage = $totalBlanks > 0 ? round(($correctCount / $totalBlanks) * 100) : 0;
            $feedback = "Jawaban benar: {$correctCount}/{$totalBlanks} ({$percentage}%)";

            $passingScore = $challenge->settings['passing_score'] ?? 70;
            if ($percentage >= $passingScore) {
                $feedback .= " - LULUS ✅";
            } else {
                $feedback .= " - BELUM LULUS ❌";
            }

            return [
                'score' => $totalScore,
                'status' => 'graded',
                'feedback' => $feedback,
            ];

        } catch (\Exception $e) {
            Log::error('Error grading fill blank submission', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'score' => null,
                'status' => 'pending',
                'feedback' => 'Error during grading.',
            ];
        }
    }

    /**
     * Check if a challenge type supports auto-grading.
     */
    public function supportsAutoGrading(string $challengeType): bool
    {
        return in_array($challengeType, [
            'multiple_choice',
            'quiz_group',
            'fill_blank',
        ]);
    }
}
