<?php

namespace Database\Seeders;

use App\Models\{Challenge, Lesson, Module};
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ChallengeSeeder extends Seeder
{
    /**
     * Challenge types supported by the system
     */
    private const TYPES = [
        'multiple_choice',
        'fill_blank',
        'code_editor',
        'file_upload',
        'github_submission',
        'docker_project',
        'timed_exam',
        'quiz_group',
    ];

    /**
     * Difficulty levels
     */
    private const DIFFICULTIES = ['easy', 'medium', 'hard'];

    /**
     * Run the database seeds.
     *
     * Creates 2-3 challenges per lesson for comprehensive testing.
     * Target: 500-750 challenges across all lessons.
     */
    public function run(): void
    {
        $lessons = Lesson::with('module')->get();
        $totalChallenges = 0;

        foreach ($lessons as $lessonIndex => $lesson) {
            // Create 2-3 challenges per lesson
            $numChallenges = ($lessonIndex % 5 === 0) ? 3 : 2;

            for ($i = 0; $i < $numChallenges; $i++) {
                $this->createChallenge($lesson, $i, $totalChallenges);
                $totalChallenges++;
            }
        }

        $this->command->info("✅ Challenges seeded: {$totalChallenges}");
        $this->command->info("   📊 Distribution: 2-3 challenges per lesson");
        $this->command->info("   🎯 Types: multiple_choice, essay, coding, file_upload, github_submission, project");
    }

    /**
     * Create a single challenge for a lesson
     */
    private function createChallenge(Lesson $lesson, int $index, int $globalIndex): void
    {
        // Determine challenge type based on global index for variety
        $type = self::TYPES[$globalIndex % count(self::TYPES)];

        // Determine difficulty - distribution: 50% easy, 30% medium, 20% hard
        $rand = $globalIndex % 10;
        if ($rand < 5) {
            $difficulty = 'easy';
        } elseif ($rand < 8) {
            $difficulty = 'medium';
        } else {
            $difficulty = 'hard';
        }

        // Set max_score based on difficulty
        $maxScore = match ($difficulty) {
            'easy' => [10, 20, 25][array_rand([10, 20, 25])],
            'medium' => [50, 75][array_rand([50, 75])],
            'hard' => [100, 150][array_rand([100, 150])],
        };

        // Set points (usually same or slightly higher than max_score)
        $points = match ($difficulty) {
            'easy' => [25, 50][array_rand([25, 50])],
            'medium' => [75, 100][array_rand([75, 100])],
            'hard' => [125, 150][array_rand([125, 150])],
        };

        // Set allowed_attempts - most get 1, some get 2, few get 3
        $attemptRand = $globalIndex % 20;
        if ($attemptRand < 12) {
            $allowedAttempts = 1;
        } elseif ($attemptRand < 18) {
            $allowedAttempts = 2;
        } else {
            $allowedAttempts = 3;
        }

        // Generate challenge data
        $title = $this->generateTitle($lesson->title, $type, $index);
        $slug = Str::slug($title . '-' . $lesson->id . '-' . $index);

        Challenge::updateOrCreate(
            ['slug' => $slug],
            [
                'module_id' => null,
                'lesson_id' => $lesson->id,
                'title' => $title,
                'type' => $type,
                'difficulty' => $difficulty,
                'order' => $index + 1,
                'content' => $this->generateContent($type, $lesson->title),
                'settings' => $this->generateSettings($type),
                'metadata' => $this->generateMetadata($type, $lesson->title),
                'max_score' => $maxScore,
                'points' => $points,
                'allowed_attempts' => $allowedAttempts,
            ]
        );
    }

    /**
     * Generate challenge title
     */
    private function generateTitle(string $lessonTitle, string $type, int $index): string
    {
        $typeLabels = [
            'multiple_choice' => 'Quiz',
            'essay' => 'Esai',
            'coding' => 'Coding',
            'file_upload' => 'Project Upload',
            'github_submission' => 'GitHub Project',
            'project' => 'Project',
        ];

        $label = $typeLabels[$type] ?? 'Challenge';
        return "{$lessonTitle} - {$label} " . ($index + 1);
    }

    /**
     * Generate challenge content HTML
     */
    private function generateContent(string $type, string $lessonTitle): string
    {
        $instructions = match ($type) {
            'multiple_choice' => 'Pilih jawaban yang paling tepat berdasarkan materi yang telah dipelajari.',
            'fill_blank' => 'Lengkapi bagian yang kosong dengan jawaban yang tepat.',
            'code_editor' => 'Selesaikan kode berikut sesuai dengan spesifikasi yang diminta.',
            'file_upload' => 'Selesaikan project dan upload hasilnya dalam format ZIP atau RAR (maksimal 50MB).',
            'github_submission' => 'Kerjakan project dan push ke GitHub repository, lalu submit URL repository-nya.',
            'docker_project' => 'Kerjakan project Docker sesuai requirements yang diberikan.',
            'timed_exam' => 'Kerjakan ujian dalam waktu yang ditentukan. Pastikan selesai sebelum waktu habis.',
            'quiz_group' => 'Jawab semua pertanyaan quiz berikut dengan teliti.',
        };

        return <<<HTML
<h2>{$lessonTitle}</h2>
<p>{$instructions}</p>

<h3>Petunjuk Pengerjaan</h3>
<ul>
    <li>Baca soal dengan teliti</li>
    <li>Kerjakan sesuai dengan materi yang telah dipelajari</li>
    <li>Pastikan jawaban Anda lengkap dan sesuai requirements</li>
</ul>
HTML;
    }

    /**
     * Generate challenge settings
     */
    private function generateSettings(string $type): array
    {
        return match ($type) {
            'multiple_choice' => [
                'time_limit' => 15,
                'shuffle_options' => true,
                'passing_score' => 70,
                'show_correct_answers' => false,
            ],
            'fill_blank' => [
                'time_limit' => 20,
                'case_sensitive' => false,
                'passing_score' => 70,
            ],
            'code_editor' => [
                'time_limit' => 45,
                'passing_score' => 80,
                'allow_run_tests' => true,
            ],
            'file_upload' => [
                'max_file_size_mb' => 50,
                'allowed_extensions' => ['zip', 'rar', '7z'],
                'passing_score' => 75,
            ],
            'github_submission' => [
                'required_branch' => 'main',
                'repository_visibility' => 'public',
                'passing_score' => 75,
            ],
            'docker_project' => [
                'time_limit' => 120,
                'passing_score' => 75,
                'required_files' => ['docker-compose.yml', 'Dockerfile'],
            ],
            'timed_exam' => [
                'time_limit' => 60,
                'passing_score' => 80,
                'can_pause' => false,
                'show_timer' => true,
            ],
            'quiz_group' => [
                'time_limit' => 30,
                'passing_score' => 70,
                'shuffle_questions' => true,
                'show_results_immediately' => false,
            ],
        };
    }

    /**
     * Generate challenge metadata based on type
     */
    private function generateMetadata(string $type, string $lessonTitle): array
    {
        return match ($type) {
            'multiple_choice' => $this->generateMultipleChoiceMetadata($lessonTitle),
            'fill_blank' => $this->generateFillBlankMetadata($lessonTitle),
            'code_editor' => $this->generateCodeEditorMetadata($lessonTitle),
            'file_upload' => $this->generateFileUploadMetadata($lessonTitle),
            'github_submission' => $this->generateGithubMetadata($lessonTitle),
            'docker_project' => $this->generateDockerProjectMetadata($lessonTitle),
            'timed_exam' => $this->generateTimedExamMetadata($lessonTitle),
            'quiz_group' => $this->generateQuizGroupMetadata($lessonTitle),
        };
    }

    /**
     * Generate multiple choice metadata
     */
    private function generateMultipleChoiceMetadata(string $lessonTitle): array
    {
        $questions = [
            'Apa yang dimaksud dengan ' . $lessonTitle . '?',
            'Manakah pernyataan yang BENAR tentang ' . $lessonTitle . '?',
            'Fungsi utama dari ' . $lessonTitle . ' adalah?',
            'Bagaimana cara mengimplementasikan ' . $lessonTitle . '?',
        ];

        return [
            'options' => [
                ['key' => 'A', 'text' => 'Opsi A - Jawaban yang mungkin benar', 'is_correct' => true],
                ['key' => 'B', 'text' => 'Opsi B - Jawaban yang salah', 'is_correct' => false],
                ['key' => 'C', 'text' => 'Opsi C - Jawaban yang salah', 'is_correct' => false],
                ['key' => 'D', 'text' => 'Opsi D - Jawaban yang salah', 'is_correct' => false],
            ],
            'question' => $questions[array_rand($questions)],
            'explanation' => 'Penjelasan: Opsi A adalah jawaban yang paling tepat karena sesuai dengan konsep ' . $lessonTitle . '.',
        ];
    }

    /**
     * Generate code editor challenge metadata
     */
    private function generateCodeEditorMetadata(string $lessonTitle): array
    {
        $languages = ['php', 'javascript', 'python', 'html', 'css'];
        $language = $languages[array_rand($languages)];

        $starterCode = match ($language) {
            'php' => "<?php\n\nfunction solve() {\n    // Your code here\n}\n",
            'javascript' => "function solve() {\n    // Your code here\n}\n",
            'python' => "def solve():\n    # Your code here\n    pass\n",
            'html' => "<!DOCTYPE html>\n<html>\n<head>\n    <title>Solution</title>\n</head>\n<body>\n    <!-- Your code here -->\n</body>\n</html>\n",
            'css' => "/* Your CSS solution here */\n\n.container {\n    /* Add styles */\n}\n",
        };

        return [
            'language' => $language,
            'starter_code' => $starterCode,
            'expected_output' => 'Expected output based on ' . $lessonTitle,
            'test_cases' => [
                [
                    'input' => 'test input 1',
                    'expected_output' => 'expected output 1',
                    'is_hidden' => false,
                ],
                [
                    'input' => 'test input 2',
                    'expected_output' => 'expected output 2',
                    'is_hidden' => true,
                ],
            ],
            'instructions' => 'Selesaikan kode sesuai dengan konsep ' . $lessonTitle . ' yang telah dipelajari.',
        ];
    }

    /**
     * Generate file upload challenge metadata
     */
    private function generateFileUploadMetadata(string $lessonTitle): array
    {
        return [
            'allowed_extensions' => ['zip', 'rar', '7z'],
            'max_file_size_mb' => 50,
            'instructions' => 'Upload hasil project ' . $lessonTitle . ' dalam format archive (ZIP/RAR).',
            'requirements' => [
                'File harus berupa archive (ZIP/RAR/7Z)',
                'Ukuran maksimal 50MB',
                'Harus mengandung file-file project yang lengkap',
                'Sertakan README.md dengan dokumentasi',
            ],
            'checklist' => [
                'Source code lengkap',
                'File konfigurasi',
                'Dokumentasi (README.md)',
                'Screenshot hasil (optional)',
            ],
        ];
    }

    /**
     * Generate GitHub submission challenge metadata
     */
    private function generateGithubMetadata(string $lessonTitle): array
    {
        return [
            'required_branch' => 'main',
            'repository_visibility' => 'public',
            'instructions' => 'Push project ' . $lessonTitle . ' ke GitHub repository dan submit URL-nya.',
            'requirements' => [
                'Repository harus public',
                'Harus ada README.md dengan penjelasan project',
                'Code harus di branch main',
                'Sertakan .gitignore yang sesuai',
            ],
            'checklist' => [
                'Repository berisi source code lengkap',
                'README.md dengan dokumentasi',
                'File .gitignore',
                'Commit history yang jelas',
            ],
            'url_pattern' => 'https://github.com/{username}/{repository}',
        ];
    }

    /**
     * Generate docker project challenge metadata
     */
    private function generateDockerProjectMetadata(string $lessonTitle): array
    {
        return [
            'instructions' => 'Kerjakan Docker project ' . $lessonTitle . ' sesuai dengan requirements yang diberikan.',
            'deliverables' => [
                'docker-compose.yml file',
                'Dockerfile(s) untuk setiap service',
                'Dokumentasi deployment',
                'README dengan instruksi setup',
            ],
            'requirements' => [
                'Implementasi harus sesuai dengan konsep ' . $lessonTitle,
                'Docker containers harus berjalan dengan baik',
                'Harus ada docker-compose untuk orchestration',
                'Network dan volume configuration harus tepat',
            ],
            'evaluation_criteria' => [
                'container_setup' => 'Setup containers (40%)',
                'networking' => 'Networking configuration (25%)',
                'documentation' => 'Dokumentasi (20%)',
                'best_practices' => 'Docker best practices (15%)',
            ],
        ];
    }

    /**
     * Generate fill blank challenge metadata
     */
    private function generateFillBlankMetadata(string $lessonTitle): array
    {
        return [
            'instructions' => 'Lengkapi bagian yang kosong dengan jawaban yang tepat sesuai dengan materi ' . $lessonTitle . '.',
            'blanks' => [
                ['position' => 1, 'expected_answer' => 'Sample answer 1'],
                ['position' => 2, 'expected_answer' => 'Sample answer 2'],
            ],
            'case_sensitive' => false,
            'partial_credit' => true,
            'evaluation_criteria' => [
                'correctness' => 'Ketepatan jawaban (70%)',
                'completeness' => 'Kelengkapan (30%)',
            ],
        ];
    }

    /**
     * Generate timed exam challenge metadata
     */
    private function generateTimedExamMetadata(string $lessonTitle): array
    {
        return [
            'instructions' => 'Kerjakan ujian tentang ' . $lessonTitle . ' dalam waktu yang ditentukan.',
            'time_limit_minutes' => rand(45, 90),
            'total_questions' => rand(20, 30),
            'question_types' => ['multiple_choice', 'fill_blank', 'short_answer'],
            'can_pause' => false,
            'show_timer' => true,
            'evaluation_criteria' => [
                'correctness' => 'Ketepatan jawaban (80%)',
                'completion_time' => 'Waktu penyelesaian (20%)',
            ],
        ];
    }

    /**
     * Generate quiz group challenge metadata
     */
    private function generateQuizGroupMetadata(string $lessonTitle): array
    {
        return [
            'instructions' => 'Jawab semua pertanyaan quiz tentang ' . $lessonTitle . ' dengan teliti.',
            'total_questions' => rand(5, 10),
            'shuffle_questions' => true,
            'shuffle_answers' => true,
            'show_results_immediately' => false,
            'retry_allowed' => true,
            'evaluation_criteria' => [
                'correctness' => 'Ketepatan jawaban (100%)',
            ],
        ];
    }
}
