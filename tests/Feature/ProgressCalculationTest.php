<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Profile;
use App\Models\Submission;
use App\Models\Track;
use App\Models\TrackEnrollment;
use App\Models\User;
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

        // Create 4 challenges
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

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'progress' => [
                        'percent' => 50,
                        'completed_challenges' => 2,
                        'total_challenges' => 4,
                    ],
                ],
            ]);
    }

    /** @test */
    public function test_lesson_with_zero_challenges_does_not_affect_progress(): void
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

        // Don't complete the challenge yet
        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$track->slug}/progress");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'progress' => [
                        'total_challenges' => 1, // Only the direct module challenge
                        'completed_challenges' => 0,
                        'percent' => 0,
                    ],
                ],
            ]);

        // Now complete the challenge
        $this->createSubmission($challenge, $this->profile, 'graded');

        $response = $this->actingAs($this->user)
            ->getJson("/api/my/tracks/{$track->slug}/progress");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'progress' => [
                        'total_challenges' => 1,
                        'completed_challenges' => 1,
                        'percent' => 100,
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
}
