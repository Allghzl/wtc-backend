<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Challenge;
use Illuminate\Support\Str;

class ChallengeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $totalChallenges = 0;

        // Create module-level challenges (not tied to specific lesson)
        $modules = Module::all();
        foreach ($modules as $module) {
            $challengeCount = rand(1, 2); // 1-2 module challenges

            for ($i = 1; $i <= $challengeCount; $i++) {
                Challenge::create([
                    'module_id' => $module->id,
                    'lesson_id' => null, // Module-level challenge
                    'title' => "{$module->title} - Challenge {$i}",
                    'slug' => Str::slug("{$module->title}-challenge-{$i}"),
                    'type' => $this->randomChallengeType(),
                    'difficulty' => $this->randomDifficulty(),
                    'order' => $i,
                    'content' => $this->generateChallengeContent($module->title),
                    'metadata' => json_encode([
                        'time_limit' => rand(30, 120), // minutes
                        'allowed_attempts' => rand(3, 5),
                        'passing_score' => 70,
                    ]),
                    'max_score' => 100,
                    'points' => rand(50, 200),
                ]);
                $totalChallenges++;
            }
        }

        // Create lesson-level challenges
        $lessons = Lesson::all();
        foreach ($lessons as $lesson) {
            // 30% chance to have lesson-specific challenge
            if (rand(1, 100) <= 30) {
                Challenge::create([
                    'module_id' => $lesson->module_id,
                    'lesson_id' => $lesson->id,
                    'title' => "{$lesson->title} - Quiz",
                    'slug' => Str::slug("{$lesson->title}-quiz"),
                    'type' => 'quiz',
                    'difficulty' => 'easy',
                    'order' => 1,
                    'content' => $this->generateQuizContent($lesson->title),
                    'metadata' => json_encode([
                        'time_limit' => 15,
                        'allowed_attempts' => 3,
                        'passing_score' => 80,
                        'questions_count' => 10,
                    ]),
                    'max_score' => 100,
                    'points' => 50,
                ]);
                $totalChallenges++;
            }
        }

        $this->command->info("✅ Challenges seeded: {$totalChallenges}");
    }

    /**
     * Get random challenge type
     */
    private function randomChallengeType(): string
    {
        $types = ['coding', 'quiz', 'project', 'essay'];
        return $types[array_rand($types)];
    }

    /**
     * Get random difficulty level
     */
    private function randomDifficulty(): string
    {
        $difficulties = ['easy', 'medium', 'hard'];
        return $difficulties[array_rand($difficulties)];
    }

    /**
     * Generate challenge content
     */
    private function generateChallengeContent(string $moduleTitle): string
    {
        return <<<HTML
<h2>Challenge: {$moduleTitle}</h2>

<h3>Objective</h3>
<p>Apply what you've learned in {$moduleTitle} to solve this challenge.</p>

<h3>Requirements</h3>
<ul>
    <li>Complete all tasks below</li>
    <li>Submit your solution before the deadline</li>
    <li>Follow best practices</li>
</ul>

<h3>Tasks</h3>
<ol>
    <li>Implement the required functionality</li>
    <li>Write clean, maintainable code</li>
    <li>Test your solution thoroughly</li>
</ol>

<h3>Submission Guidelines</h3>
<p>Upload your completed project files or paste your code in the submission form.</p>
HTML;
    }

    /**
     * Generate quiz content
     */
    private function generateQuizContent(string $lessonTitle): string
    {
        return <<<HTML
<h2>Quiz: {$lessonTitle}</h2>

<p>Test your understanding of the concepts covered in this lesson.</p>

<h3>Instructions</h3>
<ul>
    <li>Answer all questions</li>
    <li>You have 3 attempts</li>
    <li>Minimum passing score: 80%</li>
</ul>

<p><strong>Good luck!</strong></p>
HTML;
    }
}
