<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Module;
use App\Models\Profile;
use App\Models\Submission;
use App\Models\Track;
use App\Models\TrackEnrollment;
use App\Models\User;
use App\Services\LessonCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Profile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Manually create profile since UserFactory doesn't create it automatically
        $this->profile = Profile::create([
            'user_id' => $this->user->id,
            'display_name' => $this->user->name,
            'points' => 0,
        ]);
    }

    protected function createSubmission(Challenge $challenge, Profile $profile, string $status): Submission
    {
        return Submission::create([
            'challenge_id' => $challenge->id,
            'profile_id' => $profile->id,
            'attempt_number' => 1,
            'status' => $status,
            'submitted_at' => now(),
        ]);
    }

    /** @test */
    public function test_track_with_one_challenge_shows_0_percent_when_incomplete(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $challenge = Challenge::factory()->create(['lesson_id' => $lesson->id]);

        // Enroll
        TrackEnrollment::create([
            'track_id' => $track->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$track->slug}/progress");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'progress' => [
                        'percent' => 0,
                        'completed_challenges' => 0,
                        'total_challenges' => 1,
                    ],
                ],
            ]);
    }

    /** @test */
    public function test_track_with_one_challenge_shows_100_percent_when_completed(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $challenge = Challenge::factory()->create(['lesson_id' => $lesson->id]);

        // Enroll
        TrackEnrollment::create([
            'track_id' => $track->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        // Complete challenge with graded submission
        $this->createSubmission($challenge, $this->profile, 'graded');

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$track->slug}/progress");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'progress' => [
                        'percent' => 100,
                        'completed_challenges' => 1,
                        'total_challenges' => 1,
                    ],
                ],
            ]);
    }

    /** @test */
    public function test_track_with_multiple_challenges_shows_correct_percentage(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        // Create 4 challenges in the lesson
        $challenge1 = Challenge::factory()->create(['lesson_id' => $lesson->id]);
        $challenge2 = Challenge::factory()->create(['lesson_id' => $lesson->id]);
        $challenge3 = Challenge::factory()->create(['lesson_id' => $lesson->id]);
        $challenge4 = Challenge::factory()->create(['lesson_id' => $lesson->id]);

        // Enroll
        TrackEnrollment::create([
            'track_id' => $track->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        // Complete 2 out of 4 challenges
        $this->createSubmission($challenge1, $this->profile, 'graded');
        $this->createSubmission($challenge2, $this->profile, 'reviewed');

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$track->slug}/progress");

        // CORRECTED EXPECTATION: Lesson with challenges = ONE progress item
        // Lesson is NOT complete until ALL challenges are done
        // So 2 of 4 challenges = 0% (lesson not complete)
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'progress' => [
                        'percent' => 0, // 0 of 1 lesson complete
                        'completed_lessons' => 0,
                        'total_lessons' => 1,
                        'completed_challenges' => 2, // Informational metric
                        'total_challenges' => 4, // Informational metric
                    ],
                ],
            ]);

        // Now complete the remaining 2 challenges
        $this->createSubmission($challenge3, $this->profile, 'graded');
        $this->createSubmission($challenge4, $this->profile, 'reviewed');

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$track->slug}/progress");

        // Now the lesson is complete (all 4 challenges done)
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'progress' => [
                        'percent' => 100, // 1 of 1 lesson complete
                        'completed_lessons' => 1,
                        'total_lessons' => 1,
                        'completed_challenges' => 4,
                        'total_challenges' => 4,
                    ],
                ],
            ]);
    }

    /** @test */
    public function test_lesson_without_challenges_is_counted_in_progress(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);

        // Lesson with no challenges
        $lessonEmpty = Lesson::factory()->create(['module_id' => $module->id]);

        // Direct module challenge
        $challenge = Challenge::factory()->create([
            'module_id' => $module->id,
            'lesson_id' => null,
        ]);

        // Enroll
        TrackEnrollment::create([
            'track_id' => $track->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        // Check initial progress - lesson without challenges now counts
        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$track->slug}/progress");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'progress' => [
                        'total_lessons' => 1,
                        'total_challenges' => 1,
                        'completed_lessons' => 0,
                        'completed_challenges' => 0,
                        'percent' => 0,
                    ],
                ],
            ]);

        // Complete the challenge only
        $this->createSubmission($challenge, $this->profile, 'graded');

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$track->slug}/progress");

        // Only 1 of 2 items complete (challenge done, lesson not)
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'progress' => [
                        'total_lessons' => 1,
                        'total_challenges' => 1,
                        'completed_lessons' => 0,
                        'completed_challenges' => 1,
                        'percent' => 50, // 1 of 2 items
                    ],
                ],
            ]);
    }

    /** @test */
    public function test_direct_module_challenges_are_counted(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);

        // Direct module challenges (no lesson)
        $challenge1 = Challenge::factory()->create([
            'module_id' => $module->id,
            'lesson_id' => null,
        ]);
        $challenge2 = Challenge::factory()->create([
            'module_id' => $module->id,
            'lesson_id' => null,
        ]);

        // Enroll
        TrackEnrollment::create([
            'track_id' => $track->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        // Complete one direct module challenge
        $this->createSubmission($challenge1, $this->profile, 'graded');

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$track->slug}/progress");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'progress' => [
                        'total_challenges' => 2,
                        'completed_challenges' => 1,
                        'percent' => 50,
                    ],
                ],
            ]);
    }

    /** @test */
    public function test_lesson_challenges_are_counted(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        $challenge1 = Challenge::factory()->create(['lesson_id' => $lesson->id]);
        $challenge2 = Challenge::factory()->create(['lesson_id' => $lesson->id]);

        // Enroll
        TrackEnrollment::create([
            'track_id' => $track->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        // Complete both challenges
        $this->createSubmission($challenge1, $this->profile, 'graded');
        $this->createSubmission($challenge2, $this->profile, 'reviewed');

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$track->slug}/progress");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'progress' => [
                        'total_challenges' => 2,
                        'completed_challenges' => 2,
                        'percent' => 100,
                    ],
                ],
            ]);
    }

    /** @test */
    public function test_pending_submission_does_not_complete_challenge(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $challenge = Challenge::factory()->create(['module_id' => $module->id]);

        TrackEnrollment::create([
            'track_id' => $track->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        // Create pending submission
        $this->createSubmission($challenge, $this->profile, 'pending');

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$track->slug}/progress");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'progress' => [
                        'completed_challenges' => 0,
                    ],
                ],
            ]);
    }

    /** @test */
    public function test_rejected_submission_does_not_complete_challenge(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $challenge = Challenge::factory()->create(['module_id' => $module->id]);

        TrackEnrollment::create([
            'track_id' => $track->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        // Create rejected submission
        $this->createSubmission($challenge, $this->profile, 'rejected');

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$track->slug}/progress");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'progress' => [
                        'completed_challenges' => 0,
                    ],
                ],
            ]);
    }

    /** @test */
    public function test_reviewed_submission_completes_challenge(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $challenge = Challenge::factory()->create(['module_id' => $module->id]);

        TrackEnrollment::create([
            'track_id' => $track->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        // Create reviewed submission
        $this->createSubmission($challenge, $this->profile, 'reviewed');

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$track->slug}/progress");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'progress' => [
                        'completed_challenges' => 1,
                        'total_challenges' => 1,
                        'percent' => 100,
                    ],
                ],
            ]);
    }

    /** @test */
    public function test_graded_submission_completes_challenge(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $challenge = Challenge::factory()->create(['module_id' => $module->id]);

        TrackEnrollment::create([
            'track_id' => $track->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        // Create graded submission
        $this->createSubmission($challenge, $this->profile, 'graded');

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$track->slug}/progress");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'progress' => [
                        'completed_challenges' => 1,
                        'total_challenges' => 1,
                        'percent' => 100,
                    ],
                ],
            ]);
    }

    /** @test */
    public function test_multiple_submissions_with_one_graded_completes_challenge(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $challenge = Challenge::factory()->create(['module_id' => $module->id]);

        TrackEnrollment::create([
            'track_id' => $track->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        // Create rejected submission first
        Submission::create([
            'challenge_id' => $challenge->id,
            'profile_id' => $this->profile->id,
            'attempt_number' => 1,
            'status' => 'rejected',
            'submitted_at' => now()->subDay(),
        ]);

        // Create graded submission second
        Submission::create([
            'challenge_id' => $challenge->id,
            'profile_id' => $this->profile->id,
            'attempt_number' => 2,
            'status' => 'graded',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$track->slug}/progress");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'progress' => [
                        'completed_challenges' => 1,
                        'total_challenges' => 1,
                        'percent' => 100,
                    ],
                ],
            ]);
    }

    /** @test */
    public function test_module_with_zero_challenges_has_zero_percent(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        // No challenges created

        TrackEnrollment::create([
            'track_id' => $track->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$track->slug}/progress");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'progress' => [
                        'total_challenges' => 0,
                        'completed_challenges' => 0,
                        'percent' => 0,
                    ],
                ],
            ]);
    }

    /** @test */
    public function test_track_with_multiple_modules_calculates_correctly(): void
    {
        $track = Track::factory()->create();

        // Module 1: 3 challenges, all completed
        $module1 = Module::factory()->create(['track_id' => $track->id]);
        $challenge1_1 = Challenge::factory()->create(['module_id' => $module1->id]);
        $challenge1_2 = Challenge::factory()->create(['module_id' => $module1->id]);
        $challenge1_3 = Challenge::factory()->create(['module_id' => $module1->id]);

        // Module 2: 2 challenges, 1 completed
        $module2 = Module::factory()->create(['track_id' => $track->id]);
        $challenge2_1 = Challenge::factory()->create(['module_id' => $module2->id]);
        $challenge2_2 = Challenge::factory()->create(['module_id' => $module2->id]);

        // Module 3: 0 challenges (should not affect calculation)
        $module3 = Module::factory()->create(['track_id' => $track->id]);

        // Enroll
        TrackEnrollment::create([
            'track_id' => $track->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        // Complete module 1 challenges
        $this->createSubmission($challenge1_1, $this->profile, 'graded');
        $this->createSubmission($challenge1_2, $this->profile, 'reviewed');
        $this->createSubmission($challenge1_3, $this->profile, 'graded');

        // Complete only one challenge in module 2
        $this->createSubmission($challenge2_1, $this->profile, 'graded');

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$track->slug}/progress");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'progress' => [
                        'total_challenges' => 5,
                        'completed_challenges' => 4,
                        'percent' => 80,
                        'total_modules' => 3,
                        'completed_modules' => 1, // Only module 1 is fully completed
                    ],
                ],
            ]);
    }

    /** @test */
    public function lesson_without_challenges_counts_in_total_lessons()
    {
        $lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);

        $progress = $this->progressService->getModuleProgress($this->module, $this->profile);

        $this->assertEquals(0, $progress['completed_lessons']);
        $this->assertEquals(1, $progress['total_lessons']);
        $this->assertEquals(0, $progress['completed_challenges']);
        $this->assertEquals(0, $progress['total_challenges']);
    }

    /** @test */
    public function explicit_lesson_completion_increases_completed_lessons()
    {
        $lessonCompletionService = app(LessonCompletionService::class);

        $lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);

        $lessonCompletionService->markAsComplete($lesson, $this->profile);

        $progress = $this->progressService->getModuleProgress($this->module, $this->profile);

        $this->assertEquals(1, $progress['completed_lessons']);
        $this->assertEquals(1, $progress['total_lessons']);
        $this->assertEquals(100, $progress['percent']);
    }

    /** @test */
    public function module_progress_includes_both_lessons_and_challenges()
    {
        $lessonCompletionService = app(LessonCompletionService::class);

        // Lesson without challenges
        $lesson1 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);

        // Lesson with challenge
        $lesson2 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 2,
        ]);
        $challenge = Challenge::factory()->create([
            'lesson_id' => $lesson2->id,
            'order' => 1,
        ]);

        // Total: 2 lessons + 1 challenge = 3 items
        $progress = $this->progressService->getModuleProgress($this->module, $this->profile);
        $this->assertEquals(0, $progress['percent']);
        $this->assertEquals(0, $progress['completed_lessons']);
        $this->assertEquals(2, $progress['total_lessons']);
        $this->assertEquals(0, $progress['completed_challenges']);
        $this->assertEquals(1, $progress['total_challenges']);

        // Complete lesson1 (explicit)
        $lessonCompletionService->markAsComplete($lesson1, $this->profile);

        $progress = $this->progressService->getModuleProgress($this->module, $this->profile);
        $this->assertEquals(33, $progress['percent']); // 1 of 3
        $this->assertEquals(1, $progress['completed_lessons']);

        // Complete challenge
        Submission::factory()->create([
            'challenge_id' => $challenge->id,
            'profile_id' => $this->profile->id,
            'status' => 'graded',
        ]);

        $progress = $this->progressService->getModuleProgress($this->module, $this->profile);
        $this->assertEquals(100, $progress['percent']); // 3 of 3 (lesson1 + lesson2 + challenge)
        $this->assertEquals(2, $progress['completed_lessons']);
        $this->assertEquals(1, $progress['completed_challenges']);
    }

    /** @test */
    public function track_progress_aggregates_lessons_and_challenges()
    {
        $lessonCompletionService = app(LessonCompletionService::class);

        $module2 = Module::factory()->create([
            'track_id' => $this->track->id,
            'order' => 2,
        ]);

        // Module 1: 1 lesson without challenges
        $lesson1 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);

        // Module 2: 1 lesson with challenge
        $lesson2 = Lesson::factory()->create([
            'module_id' => $module2->id,
            'order' => 1,
        ]);
        $challenge = Challenge::factory()->create([
            'lesson_id' => $lesson2->id,
            'order' => 1,
        ]);

        $progress = $this->progressService->getTrackProgress($this->track, $this->profile);
        $this->assertEquals(2, $progress['total_lessons']);
        $this->assertEquals(1, $progress['total_challenges']);
        $this->assertEquals(0, $progress['completed_lessons']);
        $this->assertEquals(0, $progress['completed_challenges']);

        // Complete lesson1
        $lessonCompletionService->markAsComplete($lesson1, $this->profile);

        $progress = $this->progressService->getTrackProgress($this->track, $this->profile);
        $this->assertEquals(1, $progress['completed_lessons']);
        $this->assertEquals(33, $progress['percent']); // 1 of 3 items
    }

    /** @test */
    public function lesson_with_challenges_derives_completion_from_challenges()
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

        $progress = $this->progressService->getModuleProgress($this->module, $this->profile);
        $this->assertEquals(0, $progress['completed_lessons']);
        $this->assertEquals(1, $progress['total_lessons']);
        $this->assertEquals(2, $progress['total_challenges']);

        // Complete first challenge only
        Submission::factory()->create([
            'challenge_id' => $challenge1->id,
            'profile_id' => $this->profile->id,
            'status' => 'graded',
        ]);

        $progress = $this->progressService->getModuleProgress($this->module, $this->profile);
        $this->assertEquals(0, $progress['completed_lessons']); // Lesson not complete yet
        $this->assertEquals(1, $progress['completed_challenges']);
        $this->assertEquals(33, $progress['percent']); // 1 of 3

        // Complete second challenge
        Submission::factory()->create([
            'challenge_id' => $challenge2->id,
            'profile_id' => $this->profile->id,
            'status' => 'reviewed',
        ]);

        $progress = $this->progressService->getModuleProgress($this->module, $this->profile);
        $this->assertEquals(1, $progress['completed_lessons']); // Now lesson is complete
        $this->assertEquals(2, $progress['completed_challenges']);
        $this->assertEquals(100, $progress['percent']); // All complete
    }

    /** @test */
    public function module_with_direct_challenges_and_lessons_calculates_correctly()
    {
        $lessonCompletionService = app(LessonCompletionService::class);

        // Lesson without challenges
        $lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]);

        // Direct module challenge (not in a lesson)
        $directChallenge = Challenge::factory()->create([
            'module_id' => $this->module->id,
            'lesson_id' => null,
            'order' => 1,
        ]);

        $progress = $this->progressService->getModuleProgress($this->module, $this->profile);
        $this->assertEquals(1, $progress['total_lessons']);
        $this->assertEquals(1, $progress['total_challenges']);
        $this->assertEquals(0, $progress['percent']);

        // Complete lesson
        $lessonCompletionService->markAsComplete($lesson, $this->profile);

        $progress = $this->progressService->getModuleProgress($this->module, $this->profile);
        $this->assertEquals(50, $progress['percent']); // 1 of 2

        // Complete direct challenge
        Submission::factory()->create([
            'challenge_id' => $directChallenge->id,
            'profile_id' => $this->profile->id,
            'status' => 'graded',
        ]);

        $progress = $this->progressService->getModuleProgress($this->module, $this->profile);
        $this->assertEquals(100, $progress['percent']); // 2 of 2
    }

    /** @test */
    public function critical_regression_progress_formula_does_not_double_count_lesson_challenges()
    {
        // CRITICAL TEST: Verify lessons with challenges are counted as ONE item, not N items
        // This test confirms the fix for the double-counting bug

        $lessonCompletionService = app(LessonCompletionService::class);

        // Create 3 lessons as specified in task
        $lessonA = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 1,
        ]); // 0 challenges

        $lessonB = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 2,
        ]); // Will have 3 challenges

        $lessonC = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'order' => 3,
        ]); // Will have 1 challenge

        // Add challenges to lesson B
        Challenge::factory()->create(['lesson_id' => $lessonB->id, 'order' => 1]);
        Challenge::factory()->create(['lesson_id' => $lessonB->id, 'order' => 2]);
        Challenge::factory()->create(['lesson_id' => $lessonB->id, 'order' => 3]);

        // Add challenge to lesson C
        Challenge::factory()->create(['lesson_id' => $lessonC->id, 'order' => 1]);

        // Initial state: 3 lessons, 0 completed
        // Total items = 3 (NOT 7: 3 lessons + 4 challenges)
        $progress = $this->progressService->getModuleProgress($this->module, $this->profile);
        $this->assertEquals(3, $progress['total_lessons']);
        $this->assertEquals(4, $progress['total_challenges']); // Informational metric
        $this->assertEquals(0, $progress['completed_lessons']);
        $this->assertEquals(0, $progress['percent']);

        // Complete lesson A (explicit completion since no challenges)
        $lessonCompletionService->markAsComplete($lessonA, $this->profile);

        // After completing lesson A: 1 of 3 = 33%
        // NOT 1 of 7 (which would be the bug behavior)
        $progress = $this->progressService->getModuleProgress($this->module, $this->profile);
        $this->assertEquals(1, $progress['completed_lessons']);
        $this->assertEquals(3, $progress['total_lessons']);
        $this->assertEquals(33, $progress['percent']); // CRITICAL: Must be 33%, not ~14%

        // Verify total_challenges is still tracked (informational)
        $this->assertEquals(4, $progress['total_challenges']);
        $this->assertEquals(0, $progress['completed_challenges']);
    }
}
