<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Profile;
use App\Models\StudyClass;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Services\LearningStateService;
use App\Services\LessonCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningStateTest extends TestCase
{
    use RefreshDatabase;

    protected LearningStateService $learningStateService;
    protected LessonCompletionService $lessonCompletionService;
    protected User $user;
    protected Profile $profile;
    protected Track $track;
    protected Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->learningStateService = app(LearningStateService::class);
        $this->lessonCompletionService = app(LessonCompletionService::class);

        // Create test user with profile
        $this->user = User::factory()->create();
        $studyClass = StudyClass::factory()->create();
        $this->profile = Profile::factory()->create([
            'user_id' => $this->user->id,
            'study_class_id' => $studyClass->id,
        ]);

        // Create track and module
        $this->track = Track::factory()->create();
        $this->module = Module::factory()->create([
            'track_id' => $this->track->id,
            'order' => 1,
        ]);
    }

    /** @test */
    public function first_lesson_is_always_unlocked()
    {
        $lesson1 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);
        $lesson2 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 2,
        ]);

        $this->assertTrue($this->learningStateService->isLessonUnlocked($lesson1, $this->profile));
        $this->assertFalse($this->learningStateService->isLessonUnlocked($lesson2, $this->profile));
    }

    /** @test */
    public function lesson_unlocks_when_previous_lesson_completed()
    {
        $lesson1 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);
        $lesson2 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 2,
        ]);
        $lesson3 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 3,
        ]);

        // Initially only lesson1 is unlocked
        $this->assertTrue($this->learningStateService->isLessonUnlocked($lesson1, $this->profile));
        $this->assertFalse($this->learningStateService->isLessonUnlocked($lesson2, $this->profile));
        $this->assertFalse($this->learningStateService->isLessonUnlocked($lesson3, $this->profile));

        // Complete lesson1
        $this->lessonCompletionService->markAsComplete($lesson1, $this->profile);

        // Now lesson2 is unlocked
        $this->assertTrue($this->learningStateService->isLessonUnlocked($lesson2, $this->profile));
        $this->assertFalse($this->learningStateService->isLessonUnlocked($lesson3, $this->profile));

        // Complete lesson2
        $this->lessonCompletionService->markAsComplete($lesson2, $this->profile);

        // Now lesson3 is unlocked
        $this->assertTrue($this->learningStateService->isLessonUnlocked($lesson3, $this->profile));
    }

    /** @test */
    public function it_calculates_correct_lesson_states()
    {
        $lesson1 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);
        $lesson2 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 2,
        ]);
        $lesson3 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 3,
        ]);

        // Initial states
        $this->assertEquals('current', $this->learningStateService->getLessonState($lesson1, $this->profile));
        $this->assertEquals('locked', $this->learningStateService->getLessonState($lesson2, $this->profile));
        $this->assertEquals('locked', $this->learningStateService->getLessonState($lesson3, $this->profile));

        // Complete lesson1
        $this->lessonCompletionService->markAsComplete($lesson1, $this->profile);

        $this->assertEquals('completed', $this->learningStateService->getLessonState($lesson1, $this->profile));
        $this->assertEquals('current', $this->learningStateService->getLessonState($lesson2, $this->profile));
        $this->assertEquals('locked', $this->learningStateService->getLessonState($lesson3, $this->profile));
    }

    /** @test */
    public function it_gets_current_lesson_correctly()
    {
        $lesson1 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);
        $lesson2 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 2,
        ]);
        $lesson3 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 3,
        ]);

        // Initially, current is lesson1
        $current = $this->learningStateService->getCurrentLesson($this->module, $this->profile);
        $this->assertEquals($lesson1->id, $current->id);

        // Complete lesson1
        $this->lessonCompletionService->markAsComplete($lesson1, $this->profile);

        // Now current is lesson2
        $current = $this->learningStateService->getCurrentLesson($this->module, $this->profile);
        $this->assertEquals($lesson2->id, $current->id);

        // Complete lesson2
        $this->lessonCompletionService->markAsComplete($lesson2, $this->profile);

        // Now current is lesson3
        $current = $this->learningStateService->getCurrentLesson($this->module, $this->profile);
        $this->assertEquals($lesson3->id, $current->id);

        // Complete lesson3
        $this->lessonCompletionService->markAsComplete($lesson3, $this->profile);

        // All completed, no current lesson
        $current = $this->learningStateService->getCurrentLesson($this->module, $this->profile);
        $this->assertNull($current);
    }

    /** @test */
    public function it_gets_next_lesson_correctly()
    {
        $lesson1 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);
        $lesson2 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 2,
        ]);
        $lesson3 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 3,
        ]);

        // Next lesson after lesson1 is lesson2
        $next = $this->learningStateService->getNextLesson($lesson1, $this->profile);
        $this->assertEquals($lesson2->id, $next->id);

        // Next lesson after lesson2 is lesson3
        $next = $this->learningStateService->getNextLesson($lesson2, $this->profile);
        $this->assertEquals($lesson3->id, $next->id);

        // No next lesson after lesson3
        $next = $this->learningStateService->getNextLesson($lesson3, $this->profile);
        $this->assertNull($next);
    }

    /** @test */
    public function it_provides_continue_learning_across_tracks()
    {
        $module2 = Module::factory()->create([
            'track_id' => $this->track->id,
            'order' => 2,
        ]);

        $lesson1 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);
        $lesson2 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 2,
        ]);
        $lesson3 = Lesson::factory()->create([
            'module_id' => $module2->id,
            'order' => 1,
        ]);

        // Initially, continue learning points to lesson1
        $continue = $this->learningStateService->getContinueLearning($this->profile);
        $this->assertNotNull($continue);
        $this->assertEquals($lesson1->id, $continue['lesson']->id);

        // Complete lesson1
        $this->lessonCompletionService->markAsComplete($lesson1, $this->profile);

        // Now continue learning points to lesson2
        $continue = $this->learningStateService->getContinueLearning($this->profile);
        $this->assertEquals($lesson2->id, $continue['lesson']->id);

        // Complete lesson2
        $this->lessonCompletionService->markAsComplete($lesson2, $this->profile);

        // Now continue learning points to lesson3 in module2
        $continue = $this->learningStateService->getContinueLearning($this->profile);
        $this->assertEquals($lesson3->id, $continue['lesson']->id);
        $this->assertEquals($module2->id, $continue['module']->id);
    }

    /** @test */
    public function challenge_based_lesson_unlocks_next_lesson()
    {
        $lesson1 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);
        $lesson2 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 2,
        ]);

        $challenge = Challenge::factory()->create([
            'lesson_id' => $lesson1->id,
            'order' => 1,
        ]);

        // lesson1 has challenge, lesson2 is locked
        $this->assertFalse($this->learningStateService->isLessonUnlocked($lesson2, $this->profile));

        // Complete the challenge
        Submission::factory()->create([
            'challenge_id' => $challenge->id,
            'profile_id' => $this->profile->id,
            'status' => 'graded',
        ]);

        // Now lesson2 should be unlocked
        $this->assertTrue($this->learningStateService->isLessonUnlocked($lesson2, $this->profile));
    }

    /** @test */
    public function returns_null_when_no_lessons_exist()
    {
        $continue = $this->learningStateService->getContinueLearning($this->profile);
        $this->assertNull($continue);
    }
}
