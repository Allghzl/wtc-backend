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
use App\Services\DashboardService;
use App\Services\LessonCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected DashboardService $dashboardService;
    protected LessonCompletionService $lessonCompletionService;
    protected User $user;
    protected Profile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dashboardService = app(DashboardService::class);
        $this->lessonCompletionService = app(LessonCompletionService::class);

        // Create test user with profile
        $this->user = User::factory()->create();
        $studyClass = StudyClass::factory()->create();
        $this->profile = Profile::factory()->create([
            'user_id' => $this->user->id,
            'study_class_id' => $studyClass->id,
        ]);
    }

    /** @test */
    public function it_returns_dashboard_with_no_enrollments()
    {
        $dashboard = $this->dashboardService->getStudentDashboard($this->profile);

        $this->assertArrayHasKey('profile', $dashboard);
        $this->assertArrayHasKey('stats', $dashboard);
        $this->assertArrayHasKey('tracks', $dashboard);
        $this->assertArrayHasKey('continue_learning', $dashboard);

        $this->assertEquals(0, $dashboard['stats']['active_tracks']);
        $this->assertEquals(0, $dashboard['stats']['completed_tracks']);
        $this->assertEmpty($dashboard['tracks']);
        $this->assertNull($dashboard['continue_learning']);
    }

    /** @test */
    public function it_calculates_stats_correctly()
    {
        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();

        $module1 = Module::factory()->create(['track_id' => $track1->id, 'order' => 1]);
        $module2 = Module::factory()->create(['track_id' => $track2->id, 'order' => 1]);

        $lesson1 = Lesson::factory()->create(['module_id' => $module1->id, 'order' => 1]);
        $lesson2 = Lesson::factory()->create(['module_id' => $module1->id, 'order' => 2]);
        $lesson3 = Lesson::factory()->create(['module_id' => $module2->id, 'order' => 1]);

        $challenge1 = Challenge::factory()->create(['lesson_id' => $lesson1->id, 'order' => 1]);
        $challenge2 = Challenge::factory()->create(['lesson_id' => $lesson3->id, 'order' => 1]);

        // Enroll in both tracks
        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $track1->id,
            'status' => 'active',
        ]);
        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $track2->id,
            'status' => 'active',
        ]);

        // Complete lesson1's challenge
        Submission::factory()->create([
            'challenge_id' => $challenge1->id,
            'profile_id' => $this->profile->id,
            'status' => 'graded',
        ]);

        // Complete lesson2 (no challenges)
        $this->lessonCompletionService->markAsComplete($lesson2, $this->profile);

        $dashboard = $this->dashboardService->getStudentDashboard($this->profile);

        $this->assertEquals(2, $dashboard['stats']['active_tracks']);
        $this->assertEquals(0, $dashboard['stats']['completed_tracks']);
        $this->assertEquals(1, $dashboard['stats']['total_completed_challenges']);
        $this->assertEquals(2, $dashboard['stats']['total_challenges']);
        $this->assertEquals(2, $dashboard['stats']['total_completed_lessons']);
        $this->assertEquals(3, $dashboard['stats']['total_lessons']);
    }

    /** @test */
    public function it_includes_track_progress_information()
    {
        $track = Track::factory()->create(['title' => 'Test Track']);
        $module = Module::factory()->create(['track_id' => $track->id, 'order' => 1]);

        $lesson1 = Lesson::factory()->create(['module_id' => $module->id, 'order' => 1]);
        $lesson2 = Lesson::factory()->create(['module_id' => $module->id, 'order' => 2]);

        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $track->id,
            'status' => 'active',
        ]);

        // Complete first lesson
        $this->lessonCompletionService->markAsComplete($lesson1, $this->profile);

        $dashboard = $this->dashboardService->getStudentDashboard($this->profile);

        $this->assertCount(1, $dashboard['tracks']);
        $trackData = $dashboard['tracks'][0];

        $this->assertEquals('Test Track', $trackData['title']);
        $this->assertArrayHasKey('progress', $trackData);
        $this->assertEquals(1, $trackData['progress']['completed_lessons']);
        $this->assertEquals(2, $trackData['progress']['total_lessons']);
        $this->assertEquals(50, $trackData['progress']['percent']);
    }

    /** @test */
    public function it_provides_continue_learning_information()
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id, 'order' => 1]);

        $lesson1 = Lesson::factory()->create(['module_id' => $module->id, 'order' => 1, 'title' => 'First Lesson']);
        $lesson2 = Lesson::factory()->create(['module_id' => $module->id, 'order' => 2, 'title' => 'Second Lesson']);

        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $track->id,
            'status' => 'active',
        ]);

        $dashboard = $this->dashboardService->getStudentDashboard($this->profile);

        $this->assertNotNull($dashboard['continue_learning']);
        $this->assertEquals('First Lesson', $dashboard['continue_learning']['lesson']['title']);

        // Complete first lesson
        $this->lessonCompletionService->markAsComplete($lesson1, $this->profile);

        $dashboard = $this->dashboardService->getStudentDashboard($this->profile);

        $this->assertNotNull($dashboard['continue_learning']);
        $this->assertEquals('Second Lesson', $dashboard['continue_learning']['lesson']['title']);
    }

    /** @test */
    public function it_counts_completed_tracks_correctly()
    {
        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();

        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $track1->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $track2->id,
            'status' => 'active',
        ]);

        $dashboard = $this->dashboardService->getStudentDashboard($this->profile);

        $this->assertEquals(1, $dashboard['stats']['active_tracks']);
        $this->assertEquals(1, $dashboard['stats']['completed_tracks']);
    }

    /** @test */
    public function it_includes_profile_information()
    {
        $this->profile->update(['display_name' => 'Test Student', 'points' => 100]);

        $dashboard = $this->dashboardService->getStudentDashboard($this->profile);

        $this->assertEquals('Test Student', $dashboard['profile']['display_name']);
        $this->assertEquals(100, $dashboard['profile']['points']);
        $this->assertArrayHasKey('study_class', $dashboard['profile']);
    }

    /** @test */
    public function dashboard_api_endpoint_returns_correct_structure()
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id, 'order' => 1]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'order' => 1]);

        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $track->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/my/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'profile' => [
                        'id',
                        'display_name',
                        'points',
                        'study_class',
                    ],
                    'stats' => [
                        'active_tracks',
                        'completed_tracks',
                        'total_completed_challenges',
                        'total_challenges',
                        'total_completed_lessons',
                        'total_lessons',
                        'overall_progress',
                    ],
                    'tracks',
                    'continue_learning',
                ],
            ]);
    }

    /** @test */
    public function dashboard_requires_authentication()
    {
        $response = $this->getJson('/api/my/dashboard');
        $response->assertStatus(401);
    }

    /** @test */
    public function it_calculates_overall_progress_percentage()
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id, 'order' => 1]);

        $lesson1 = Lesson::factory()->create(['module_id' => $module->id, 'order' => 1]);
        $lesson2 = Lesson::factory()->create(['module_id' => $module->id, 'order' => 2]);

        $challenge = Challenge::factory()->create(['module_id' => $module->id, 'order' => 1]);

        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $track->id,
            'status' => 'active',
        ]);

        // Total: 2 lessons + 1 challenge = 3 items
        // Complete 1 lesson
        $this->lessonCompletionService->markAsComplete($lesson1, $this->profile);

        $dashboard = $this->dashboardService->getStudentDashboard($this->profile);

        // 1 completed out of 3 total = 33%
        $this->assertEquals(33, $dashboard['stats']['overall_progress']);

        // Complete the challenge
        Submission::factory()->create([
            'challenge_id' => $challenge->id,
            'profile_id' => $this->profile->id,
            'status' => 'graded',
        ]);

        $dashboard = $this->dashboardService->getStudentDashboard($this->profile);

        // 2 completed out of 3 total = 67%
        $this->assertEquals(67, $dashboard['stats']['overall_progress']);
    }

    /** @test */
    public function it_handles_multiple_active_enrollments()
    {
        $track1 = Track::factory()->create(['title' => 'Track 1']);
        $track2 = Track::factory()->create(['title' => 'Track 2']);
        $track3 = Track::factory()->create(['title' => 'Track 3']);

        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $track1->id,
            'status' => 'active',
            'enrolled_at' => now()->subDays(3),
        ]);
        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $track2->id,
            'status' => 'active',
            'enrolled_at' => now()->subDays(2),
        ]);
        TrackEnrollment::factory()->create([
            'profile_id' => $this->profile->id,
            'track_id' => $track3->id,
            'status' => 'active',
            'enrolled_at' => now()->subDays(1),
        ]);

        $dashboard = $this->dashboardService->getStudentDashboard($this->profile);

        $this->assertCount(3, $dashboard['tracks']);
        $this->assertEquals(3, $dashboard['stats']['active_tracks']);

        // Should be ordered by most recent enrollment first
        $this->assertEquals('Track 3', $dashboard['tracks'][0]['title']);
        $this->assertEquals('Track 2', $dashboard['tracks'][1]['title']);
        $this->assertEquals('Track 1', $dashboard['tracks'][2]['title']);
    }
}
