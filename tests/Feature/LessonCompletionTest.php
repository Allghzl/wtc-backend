<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Module;
use App\Models\Profile;
use App\Models\StudyClass;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Services\LessonCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected LessonCompletionService $service;
    protected User $user;
    protected Profile $profile;
    protected Track $track;
    protected Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LessonCompletionService::class);

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
    public function it_can_mark_lesson_without_challenges_as_complete()
    {
        $lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);

        $completion = $this->service->markAsComplete($lesson, $this->profile);

        $this->assertInstanceOf(LessonCompletion::class, $completion);
        $this->assertEquals($lesson->id, $completion->lesson_id);
        $this->assertEquals($this->profile->id, $completion->profile_id);
        $this->assertNotNull($completion->completed_at);

        $this->assertDatabaseHas('lesson_completions', [
            'lesson_id' => $lesson->id,
            'profile_id' => $this->profile->id,
        ]);
    }

    /** @test */
    public function it_is_idempotent_when_marking_lesson_complete()
    {
        $lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);

        $firstCompletion = $this->service->markAsComplete($lesson, $this->profile);
        $secondCompletion = $this->service->markAsComplete($lesson, $this->profile);

        $this->assertEquals($firstCompletion->id, $secondCompletion->id);
        $this->assertEquals(1, LessonCompletion::count());
    }

    /** @test */
    public function it_detects_explicit_lesson_completion()
    {
        $lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);

        $this->assertFalse($this->service->isLessonCompleted($lesson, $this->profile));

        $this->service->markAsComplete($lesson, $this->profile);

        $this->assertTrue($this->service->isLessonCompleted($lesson, $this->profile));
    }

    /** @test */
    public function it_detects_challenge_based_lesson_completion()
    {
        $lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);

        $challenge1 = Challenge::factory()->create([
            'lesson_id' => $lesson->id,
            'order' => 1,
        ]);
        $challenge2 = Challenge::factory()->create([
            'lesson_id' => $lesson->id,
            'order' => 2,
        ]);

        // Lesson not completed initially
        $this->assertFalse($this->service->isLessonCompleted($lesson, $this->profile));

        // Complete first challenge
        Submission::factory()->create([
            'challenge_id' => $challenge1->id,
            'profile_id' => $this->profile->id,
            'status' => 'graded',
        ]);

        // Still not completed (only 1 of 2 challenges done)
        $this->assertFalse($this->service->isLessonCompleted($lesson, $this->profile));

        // Complete second challenge
        Submission::factory()->create([
            'challenge_id' => $challenge2->id,
            'profile_id' => $this->profile->id,
            'status' => 'reviewed',
        ]);

        // Now completed
        $this->assertTrue($this->service->isLessonCompleted($lesson, $this->profile));
    }

    /** @test */
    public function it_batch_checks_lesson_completion()
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

        // Mark lesson1 and lesson3 as complete
        $this->service->markAsComplete($lesson1, $this->profile);
        $this->service->markAsComplete($lesson3, $this->profile);

        $completionMap = $this->service->areLessonsCompleted(
            collect([$lesson1, $lesson2, $lesson3]),
            $this->profile
        );

        $this->assertTrue($completionMap[$lesson1->id]);
        $this->assertFalse($completionMap[$lesson2->id]);
        $this->assertTrue($completionMap[$lesson3->id]);
    }

    /** @test */
    public function it_can_complete_lesson_via_api_endpoint()
    {
        $lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/lessons/{$lesson->id}/complete");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lesson marked as completed.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'lesson_id',
                    'profile_id',
                    'completed_at',
                ],
            ]);

        $this->assertDatabaseHas('lesson_completions', [
            'lesson_id' => $lesson->id,
            'profile_id' => $this->profile->id,
        ]);
    }

    /** @test */
    public function it_requires_authentication_to_complete_lesson()
    {
        $lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);

        $response = $this->postJson("/api/lessons/{$lesson->id}/complete");

        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_non_existent_lesson()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/lessons/99999/complete');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_returns_same_completion_on_repeated_api_calls()
    {
        $lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);

        $response1 = $this->actingAs($this->user)
            ->postJson("/api/lessons/{$lesson->id}/complete");

        $response2 = $this->actingAs($this->user)
            ->postJson("/api/lessons/{$lesson->id}/complete");

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $completionId1 = $response1->json('data.id');
        $completionId2 = $response2->json('data.id');

        $this->assertEquals($completionId1, $completionId2);
        $this->assertEquals(1, LessonCompletion::count());
    }
}
