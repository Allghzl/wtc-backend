<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Profile;
use App\Models\StudyClass;
use App\Models\Submission;
use App\Models\Track;
use App\Models\TrackEnrollment;
use App\Models\User;
use App\Services\LessonCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Profile $profile;
    protected Track $track;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user with profile
        $this->user = User::factory()->create();
        $studyClass = StudyClass::factory()->create();
        $this->profile = Profile::factory()->create([
            'user_id' => $this->user->id,
            'study_class_id' => $studyClass->id,
        ]);

        // Create track
        $this->track = Track::factory()->create();
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson("/api/my/tracks/{$this->track->slug}/overview");
        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_profile()
    {
        $userWithoutProfile = User::factory()->create();

        $response = $this->actingAs($userWithoutProfile)
            ->getJson("/api/my/tracks/{$this->track->slug}/overview");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['profile']);
    }

    /** @test */
    public function it_requires_active_enrollment()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$this->track->slug}/overview");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function it_rejects_inactive_enrollment()
    {
        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $this->track->id,
            'status' => 'dropped',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$this->track->slug}/overview");

        $response->assertStatus(403);
    }

    /** @test */
    public function it_returns_complete_track_overview()
    {
        // Create enrollment
        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $this->track->id,
            'status' => 'active',
        ]);

        // Create module with lessons
        $module = Module::factory()->create([
            'track_id' => $this->track->id,
            'order' => 1,
        ]);

        $lesson1 = Lesson::factory()->create([
            'module_id' => $module->id,
            'order' => 1,
        ]);

        $lesson2 = Lesson::factory()->create([
            'module_id' => $module->id,
            'order' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$this->track->slug}/overview");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'track' => ['id', 'title', 'slug', 'description', 'image_url'],
                    'enrollment' => ['status', 'enrolled_at'],
                    'progress' => [
                        'percent',
                        'completed_lessons',
                        'total_lessons',
                        'completed_challenges',
                        'total_challenges',
                    ],
                    'modules' => [
                        '*' => [
                            'id',
                            'title',
                            'slug',
                            'description',
                            'order',
                            'progress',
                            'lessons' => [
                                '*' => [
                                    'id',
                                    'title',
                                    'slug',
                                    'order',
                                    'state',
                                    'completed',
                                    'challenges_count',
                                ],
                            ],
                            'direct_challenges',
                        ],
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_includes_correct_learning_states()
    {
        $lessonCompletionService = app(LessonCompletionService::class);

        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $this->track->id,
            'status' => 'active',
        ]);

        $module = Module::factory()->create([
            'track_id' => $this->track->id,
            'order' => 1,
        ]);

        $lesson1 = Lesson::factory()->create([
            'module_id' => $module->id,
            'order' => 1,
        ]);

        $lesson2 = Lesson::factory()->create([
            'module_id' => $module->id,
            'order' => 2,
        ]);

        $lesson3 = Lesson::factory()->create([
            'module_id' => $module->id,
            'order' => 3,
        ]);

        // Complete lesson 1
        $lessonCompletionService->markAsComplete($lesson1, $this->profile);

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$this->track->slug}/overview");

        $response->assertStatus(200);

        $lessons = $response->json('data.modules.0.lessons');

        // Lesson 1 should be completed
        $this->assertEquals('completed', $lessons[0]['state']);
        $this->assertTrue($lessons[0]['completed']);

        // Lesson 2 should be current (unlocked after lesson 1)
        $this->assertEquals('current', $lessons[1]['state']);
        $this->assertFalse($lessons[1]['completed']);

        // Lesson 3 should be locked
        $this->assertEquals('locked', $lessons[2]['state']);
        $this->assertFalse($lessons[2]['completed']);
    }

    /** @test */
    public function it_handles_lessons_with_challenges_correctly()
    {
        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $this->track->id,
            'status' => 'active',
        ]);

        $module = Module::factory()->create([
            'track_id' => $this->track->id,
            'order' => 1,
        ]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
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

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$this->track->slug}/overview");

        $response->assertStatus(200);

        $lessonData = $response->json('data.modules.0.lessons.0');
        $this->assertEquals(2, $lessonData['challenges_count']);
        $this->assertFalse($lessonData['completed']);

        // Complete first challenge
        Submission::factory()->create([
            'challenge_id' => $challenge1->id,
            'profile_id' => $this->profile->id,
            'status' => 'graded',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$this->track->slug}/overview");

        $lessonData = $response->json('data.modules.0.lessons.0');
        $this->assertFalse($lessonData['completed']); // Still not complete (1 of 2)

        // Complete second challenge
        Submission::factory()->create([
            'challenge_id' => $challenge2->id,
            'profile_id' => $this->profile->id,
            'status' => 'reviewed',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$this->track->slug}/overview");

        $lessonData = $response->json('data.modules.0.lessons.0');
        $this->assertTrue($lessonData['completed']); // Now complete (2 of 2)
    }

    /** @test */
    public function it_includes_direct_module_challenges()
    {
        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $this->track->id,
            'status' => 'active',
        ]);

        $module = Module::factory()->create([
            'track_id' => $this->track->id,
            'order' => 1,
        ]);

        $directChallenge = Challenge::factory()->create([
            'module_id' => $module->id,
            'lesson_id' => null,
            'order' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$this->track->slug}/overview");

        $response->assertStatus(200);

        $directChallenges = $response->json('data.modules.0.direct_challenges');
        $this->assertCount(1, $directChallenges);
        $this->assertEquals($directChallenge->id, $directChallenges[0]['id']);
        $this->assertFalse($directChallenges[0]['completed']);
    }

    /** @test */
    public function it_does_not_leak_data_from_other_profiles()
    {
        // Create another user with progress
        $otherUser = User::factory()->create();
        $otherProfile = Profile::factory()->create([
            'user_id' => $otherUser->id,
            'study_class_id' => StudyClass::factory()->create()->id,
        ]);

        TrackEnrollment::factory()->create([
            'profile_id' => $otherProfile->id,
            'track_id' => $this->track->id,
            'status' => 'active',
        ]);

        $module = Module::factory()->create([
            'track_id' => $this->track->id,
            'order' => 1,
        ]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'order' => 1,
        ]);

        // Other user completes the lesson
        app(LessonCompletionService::class)->markAsComplete($lesson, $otherProfile);

        // Current user enrolls but hasn't completed anything
        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $this->track->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$this->track->slug}/overview");

        $response->assertStatus(200);

        // Current user should see lesson as NOT completed
        $lessonData = $response->json('data.modules.0.lessons.0');
        $this->assertFalse($lessonData['completed']);
    }

    /** @test */
    public function it_calculates_progress_with_corrected_formula()
    {
        $lessonCompletionService = app(LessonCompletionService::class);

        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $this->track->id,
            'status' => 'active',
        ]);

        $module = Module::factory()->create([
            'track_id' => $this->track->id,
            'order' => 1,
        ]);

        // Create 3 lessons as per critical test
        $lessonA = Lesson::factory()->create([
            'module_id' => $module->id,
            'order' => 1,
        ]); // No challenges

        $lessonB = Lesson::factory()->create([
            'module_id' => $module->id,
            'order' => 2,
        ]); // Will have 3 challenges

        Challenge::factory()->count(3)->create(['lesson_id' => $lessonB->id]);

        $lessonC = Lesson::factory()->create([
            'module_id' => $module->id,
            'order' => 3,
        ]); // Will have 1 challenge

        Challenge::factory()->create(['lesson_id' => $lessonC->id]);

        // Complete only lesson A
        $lessonCompletionService->markAsComplete($lessonA, $this->profile);

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$this->track->slug}/overview");

        $response->assertStatus(200);

        $progress = $response->json('data.progress');

        // Critical assertion: 1 of 3 lessons = 33%, NOT 1/7 = 14%
        $this->assertEquals(33, $progress['percent']);
        $this->assertEquals(1, $progress['completed_lessons']);
        $this->assertEquals(3, $progress['total_lessons']);
    }
}
