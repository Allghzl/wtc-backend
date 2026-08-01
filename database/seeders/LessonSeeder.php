<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Module;
use App\Models\Lesson;
use Illuminate\Support\Str;

class LessonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = Module::all();
        $totalLessons = 0;

        foreach ($modules as $module) {
            // Generate 3-5 lessons per module
            $lessonCount = rand(3, 5);

            for ($i = 1; $i <= $lessonCount; $i++) {
                Lesson::create([
                    'module_id' => $module->id,
                    'title' => "{$module->title} - Lesson {$i}",
                    'slug' => Str::slug("{$module->title}-lesson-{$i}"),
                    'description' => "Learn key concepts of {$module->title} in this comprehensive lesson.",
                    'content' => $this->generateLessonContent($module->title, $i),
                    'video_url' => "https://www.youtube.com/watch?v=dQw4w9WgXcQ", // Sample
                    'duration' => rand(10, 60), // 10-60 minutes
                    'order' => $i,
                ]);
                $totalLessons++;
            }
        }

        $this->command->info("✅ Lessons seeded: {$totalLessons}");
    }

    /**
     * Generate sample lesson content
     */
    private function generateLessonContent(string $moduleTitle, int $lessonNumber): string
    {
        return <<<HTML
<h1>Welcome to {$moduleTitle} - Lesson {$lessonNumber}</h1>

<h2>Learning Objectives</h2>
<ul>
    <li>Understand core concepts of {$moduleTitle}</li>
    <li>Apply practical skills through hands-on examples</li>
    <li>Build real-world projects</li>
</ul>

<h2>Introduction</h2>
<p>In this lesson, we will explore the fundamental concepts of {$moduleTitle}. 
You'll learn through practical examples and interactive exercises.</p>

<h2>Key Concepts</h2>
<p>This lesson covers important topics that will help you master {$moduleTitle}.</p>

<h2>Practical Example</h2>
<pre><code>// Sample code example
function example() {
    console.log('Learning {$moduleTitle}');
}
</code></pre>

<h2>Summary</h2>
<p>You've completed lesson {$lessonNumber} of {$moduleTitle}. 
Practice the concepts learned and move on to the next lesson!</p>
HTML;
    }
}
