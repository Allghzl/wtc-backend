<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\Profile;
use App\Models\Submission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubmissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates 2000-4000 submissions with varied participation.
     * Distribution:
     * - 10-20% students: no submission for a given challenge
     * - 30-40%: one submission
     * - 30-40%: two submissions (where allowed)
     * - Remaining: varied participation
     *
     * CRITICAL CONSTRAINTS:
     * - Respects challenge.allowed_attempts
     * - Unique (challenge_id, profile_id, attempt_number)
     * - No file_path values (to avoid fake S3 paths)
     */
    public function run(): void
    {
        // Get all students (profiles with 'student' role)
        $students = Profile::whereHas('roles', function ($query) {
            $query->where('name', 'student');
        })->get();

        $challenges = Challenge::all();

        $totalSubmissions = 0;
        $statusCounts = ['pending' => 0, 'graded' => 0, 'reviewed' => 0, 'rejected' => 0];

        $this->command->info('🚀 Starting submission seeding...');
        $this->command->info("   👥 Students: {$students->count()}");
        $this->command->info("   📝 Challenges: {$challenges->count()}");
        $this->command->newLine();

        foreach ($challenges as $challengeIndex => $challenge) {
            $submissionsForThisChallenge = 0;

            // Randomly select 2-4% of students to submit this challenge
            $participationRate = rand(2, 4) / 100;
            $participatingStudents = $students->random((int)($students->count() * $participationRate));

            foreach ($participatingStudents as $student) {
                // Determine how many attempts this student will make
                // Distribution: 60% make 1 attempt, 30% make 2, 10% make max allowed
                $rand = rand(1, 10);
                if ($rand <= 6) {
                    $numAttempts = 1;
                } elseif ($rand <= 9) {
                    $numAttempts = min(2, $challenge->allowed_attempts);
                } else {
                    $numAttempts = $challenge->allowed_attempts;
                }

                // Create submissions for each attempt
                for ($attemptNum = 1; $attemptNum <= $numAttempts; $attemptNum++) {
                    $status = $this->generateStatus($attemptNum, $numAttempts);
                    $scores = $this->generateScores($status, $challenge->max_score);

                    // Generate timestamps with logical ordering: created_at <= submitted_at <= updated_at
                    $daysAgo = rand(0, 60);
                    $createdAt = now()->subDays($daysAgo)->subHours(rand(0, 23));
                    $submittedAt = $createdAt->copy()->addMinutes(rand(1, 120));
                    $updatedAt = $submittedAt->copy()->addMinutes(rand(1, 60));

                    $submission = [
                        'challenge_id' => $challenge->id,
                        'profile_id' => $student->id,
                        'attempt_number' => $attemptNum,
                        'status' => $status,
                        'submitted_at' => $submittedAt,
                        'submitted_content' => json_encode($this->generateSubmittedContent($challenge->type)),
                        'file_path' => null, // Important: no fake S3 paths
                        'auto_score' => $scores['auto_score'],
                        'manual_score' => $scores['manual_score'],
                        'feedback' => $scores['feedback'],
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                    ];

                    DB::table('submissions')->insert($submission);

                    $totalSubmissions++;
                    $submissionsForThisChallenge++;
                    $statusCounts[$status]++;
                }
            }

            // Progress indicator every 50 challenges
            if (($challengeIndex + 1) % 50 === 0) {
                $this->command->info("   ✓ Processed " . ($challengeIndex + 1) . "/" . $challenges->count() . " challenges ($totalSubmissions submissions so far)");
            }
        }

        $this->command->newLine();
        $this->command->info("✅ Submissions seeded: {$totalSubmissions}");
        $this->command->info("   📊 Status distribution:");
        $this->command->info("      • Pending: {$statusCounts['pending']} (" . round($statusCounts['pending'] / $totalSubmissions * 100, 1) . "%)");
        $this->command->info("      • Graded: {$statusCounts['graded']} (" . round($statusCounts['graded'] / $totalSubmissions * 100, 1) . "%)");
        $this->command->info("      • Reviewed: {$statusCounts['reviewed']} (" . round($statusCounts['reviewed'] / $totalSubmissions * 100, 1) . "%)");
        $this->command->info("      • Rejected: {$statusCounts['rejected']} (" . round($statusCounts['rejected'] / $totalSubmissions * 100, 1) . "%)");
    }

    /**
     * Generate submission status based on attempt number
     */
    private function generateStatus(int $attemptNum, int $totalAttempts): string
    {
        // Latest attempt is more likely to be pending
        if ($attemptNum === $totalAttempts) {
            $rand = rand(1, 10);
            if ($rand <= 4) {
                return 'pending'; // 40%
            } elseif ($rand <= 7) {
                return 'graded'; // 30%
            } elseif ($rand <= 9) {
                return 'reviewed'; // 20%
            } else {
                return 'rejected'; // 10%
            }
        }

        // Earlier attempts are more likely to be graded/reviewed
        $rand = rand(1, 10);
        if ($rand <= 1) {
            return 'pending'; // 10%
        } elseif ($rand <= 5) {
            return 'graded'; // 40%
        } elseif ($rand <= 8) {
            return 'reviewed'; // 30%
        } else {
            return 'rejected'; // 20%
        }
    }

    /**
     * Generate scores based on status
     */
    private function generateScores(string $status, int $maxScore): array
    {
        if ($status === 'pending') {
            return [
                'auto_score' => null,
                'manual_score' => null,
                'feedback' => null,
            ];
        }

        if ($status === 'rejected') {
            return [
                'auto_score' => null,
                'manual_score' => null,
                'feedback' => 'Submission ditolak. Silakan perbaiki dan submit kembali.',
            ];
        }

        // For graded/reviewed, generate scores
        // 40% auto only, 30% manual only, 30% both
        $scoreType = rand(1, 10);

        if ($scoreType <= 4) {
            // Auto score only
            $autoScore = rand((int)($maxScore * 0.5), $maxScore);
            return [
                'auto_score' => $autoScore,
                'manual_score' => null,
                'feedback' => $this->generateFeedback($autoScore, $maxScore),
            ];
        } elseif ($scoreType <= 7) {
            // Manual score only
            $manualScore = rand((int)($maxScore * 0.5), $maxScore);
            return [
                'auto_score' => null,
                'manual_score' => $manualScore,
                'feedback' => $this->generateFeedback($manualScore, $maxScore),
            ];
        } else {
            // Both scores
            $autoScore = rand((int)($maxScore * 0.4), (int)($maxScore * 0.6));
            $manualScore = rand((int)($maxScore * 0.3), (int)($maxScore * 0.5));
            $totalScore = $autoScore + $manualScore;
            return [
                'auto_score' => $autoScore,
                'manual_score' => $manualScore,
                'feedback' => $this->generateFeedback($totalScore, $maxScore),
            ];
        }
    }

    /**
     * Generate feedback based on score
     */
    private function generateFeedback(int $score, int $maxScore): string
    {
        $percentage = ($score / $maxScore) * 100;

        if ($percentage >= 90) {
            $feedbacks = [
                'Excellent! Pekerjaan yang sangat baik.',
                'Outstanding work! Keep it up!',
                'Sempurna! Pemahaman konsep sangat bagus.',
                'Great job! Implementasi sudah tepat.',
            ];
        } elseif ($percentage >= 75) {
            $feedbacks = [
                'Good work! Ada beberapa hal yang bisa diperbaiki.',
                'Bagus! Hampir sempurna, perhatikan detail.',
                'Well done! Konsep sudah dipahami dengan baik.',
                'Nice! Implementasi sudah cukup baik.',
            ];
        } elseif ($percentage >= 60) {
            $feedbacks = [
                'Cukup baik. Masih ada ruang untuk improvement.',
                'Decent work. Pelajari kembali beberapa konsep.',
                'Okay. Perlu lebih memahami materi.',
                'Fair attempt. Perhatikan detail implementasi.',
            ];
        } else {
            $feedbacks = [
                'Perlu banyak perbaikan. Pelajari kembali materi dengan teliti.',
                'Kurang tepat. Silakan review materi dan coba lagi.',
                'Needs improvement. Konsultasi dengan instruktur jika perlu.',
                'Below expectations. Pelajari contoh-contoh yang diberikan.',
            ];
        }

        return $feedbacks[array_rand($feedbacks)];
    }

    /**
     * Generate submitted_content based on challenge type
     */
    private function generateSubmittedContent(string $challengeType): array
    {
        return match ($challengeType) {
            'multiple_choice' => $this->generateMultipleChoiceContent(),
            'fill_blank' => $this->generateFillBlankContent(),
            'code_editor' => $this->generateCodeEditorContent(),
            'file_upload' => $this->generateFileUploadContent(),
            'github_submission' => $this->generateGithubContent(),
            'docker_project' => $this->generateDockerProjectContent(),
            'timed_exam' => $this->generateTimedExamContent(),
            'quiz_group' => $this->generateQuizGroupContent(),
            default => ['submit_type' => 'answer', 'content' => 'Default submission content'],
        };
    }

    /**
     * Generate multiple choice submission content
     */
    private function generateMultipleChoiceContent(): array
    {
        // Random selection of 1-4 answers
        $possibleAnswers = ['A', 'B', 'C', 'D'];
        $numAnswers = rand(1, 4);
        $selectedAnswers = array_slice($possibleAnswers, 0, $numAnswers);

        return [
            'submit_type' => 'answer',
            'content' => $selectedAnswers,
        ];
    }

    /**
     * Generate code editor submission content
     */
    private function generateCodeEditorContent(): array
    {
        $codeSamples = [
            'php' => "<?php\n\nfunction solve(\$input) {\n    // Implementation\n    return \$result;\n}\n\n// Test\necho solve('test');\n",
            
            'javascript' => "function solve(input) {\n    // Implementation\n    const result = input.toUpperCase();\n    return result;\n}\n\nconsole.log(solve('test'));\n",
            
            'python' => "def solve(input):\n    # Implementation\n    result = input.upper()\n    return result\n\nprint(solve('test'))\n",
        ];

        $language = array_rand($codeSamples);

        return [
            'submit_type' => 'code',
            'language' => $language,
            'code' => $codeSamples[$language],
        ];
    }

    /**
     * Generate file upload submission content
     */
    private function generateFileUploadContent(): array
    {
        // NO file_path - important to avoid fake S3 paths
        return [
            'submit_type' => 'file',
            'description' => 'Project submission - file akan di-upload terpisah',
            'note' => 'File sudah disiapkan dalam format ZIP sesuai requirements',
        ];
    }

    /**
     * Generate GitHub submission content
     */
    private function generateGithubContent(): array
    {
        $usernames = ['johndoe', 'janedoe', 'dev123', 'coder99', 'webdev', 'student'];
        $projectNames = ['web-project', 'final-project', 'challenge-submission', 'my-app', 'practice-project'];

        $username = $usernames[array_rand($usernames)];
        $projectName = $projectNames[array_rand($projectNames)];

        return [
            'submit_type' => 'link',
            'url' => "https://github.com/{$username}/{$projectName}",
            'branch' => 'main',
            'description' => 'Project submission via GitHub repository',
        ];
    }

    /**
     * Generate docker project submission content
     */
    private function generateDockerProjectContent(): array
    {
        return [
            'submit_type' => 'docker_project',
            'description' => 'Docker project sudah dikerjakan sesuai requirements',
            'containers' => [
                'web' => 'nginx:latest',
                'app' => 'php:8.2-fpm',
                'db' => 'mysql:8.0',
                'redis' => 'redis:alpine',
            ],
            'features_completed' => [
                'Multi-container setup',
                'Docker Compose configuration',
                'Volume mapping',
                'Network configuration',
            ],
            'technologies_used' => [
                'Docker',
                'Docker Compose',
                'Nginx',
                'PHP-FPM',
            ],
            'notes' => 'Docker containers sudah ditest dan berjalan dengan baik. Dokumentasi tersedia di README.md',
        ];
    }

    /**
     * Generate fill blank submission content
     */
    private function generateFillBlankContent(): array
    {
        $answers = [
            'Laravel adalah framework PHP yang menggunakan pola arsitektur MVC',
            'HTTP method untuk membuat resource baru adalah POST',
            'ORM yang digunakan Laravel adalah Eloquent',
            'Middleware di Laravel digunakan untuk filter HTTP request',
            'Composer adalah dependency manager untuk PHP',
        ];

        return [
            'submit_type' => 'answer',
            'content' => $answers[array_rand($answers)],
        ];
    }

    /**
     * Generate timed exam submission content
     */
    private function generateTimedExamContent(): array
    {
        $timeSpent = rand(30, 90);

        return [
            'submit_type' => 'exam',
            'started_at' => now()->subMinutes($timeSpent)->toIso8601String(),
            'completed_at' => now()->toIso8601String(),
            'answers' => [
                ['question_id' => 1, 'answer' => 'A'],
                ['question_id' => 2, 'answer' => 'B'],
                ['question_id' => 3, 'answer' => 'C'],
                ['question_id' => 4, 'answer' => 'D'],
                ['question_id' => 5, 'answer' => 'A'],
            ],
            'time_taken_minutes' => $timeSpent,
            'total_questions' => 5,
        ];
    }

    /**
     * Generate quiz group submission content
     */
    private function generateQuizGroupContent(): array
    {
        $totalQuestions = rand(5, 10);
        $correctAnswers = rand(3, $totalQuestions);

        return [
            'submit_type' => 'quiz',
            'answers' => array_map(fn($i) => [
                'question_id' => $i,
                'selected_answer' => ['A', 'B', 'C', 'D'][array_rand(['A', 'B', 'C', 'D'])],
                'is_correct' => $i <= $correctAnswers,
            ], range(1, $totalQuestions)),
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctAnswers,
            'score_percentage' => round(($correctAnswers / $totalQuestions) * 100),
        ];
    }
}
