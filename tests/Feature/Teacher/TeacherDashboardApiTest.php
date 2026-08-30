<?php

namespace Tests\Feature\Teacher;

use App\Models\Challenge;
use App\Models\Role;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole   = Role::create(['name' => 'admin']);
        $teacherRole = Role::create(['name' => 'teacher']);
        $studentRole = Role::create(['name' => 'student']);

        $this->admin   = $this->makeUserWithRole($adminRole);
        $this->teacher = $this->makeUserWithRole($teacherRole);
        $this->student = $this->makeUserWithRole($studentRole);
    }

    // -------------------------------------------------------------------------
    // Dashboard — authorization
    // -------------------------------------------------------------------------

    public function test_teacher_can_access_dashboard(): void
    {
        $this->actingAs($this->teacher, 'sanctum')
            ->getJson('/api/teacher/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'stats' => [
                        'total_submissions',
                        'pending_submissions',
                        'graded_submissions',
                        'total_students',
                        'total_challenges',
                    ],
                    'pending_submissions',
                    'leaderboard',
                ],
            ]);
    }

    public function test_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/teacher/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_student_cannot_access_dashboard(): void
    {
        $this->actingAs($this->student, 'sanctum')
            ->getJson('/api/teacher/dashboard')
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $this->getJson('/api/teacher/dashboard')
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Dashboard — stats correctness
    // -------------------------------------------------------------------------

    public function test_dashboard_stats_reflect_database_counts(): void
    {
        $challenge = Challenge::factory()->create();

        Submission::create([
            'challenge_id'   => $challenge->id,
            'profile_id'     => $this->student->profile->id,
            'attempt_number' => 1,
            'status'         => 'pending',
            'submitted_at'   => now(),
        ]);
        Submission::create([
            'challenge_id'   => $challenge->id,
            'profile_id'     => $this->student->profile->id,
            'attempt_number' => 2,
            'status'         => 'graded',
            'submitted_at'   => now(),
        ]);

        $this->actingAs($this->teacher, 'sanctum')
            ->getJson('/api/teacher/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.total_submissions', 2)
            ->assertJsonPath('data.stats.pending_submissions', 1)
            ->assertJsonPath('data.stats.graded_submissions', 1);
    }

    // -------------------------------------------------------------------------
    // Dashboard — pending queue preview
    // -------------------------------------------------------------------------

    public function test_dashboard_pending_queue_includes_challenge_and_profile_summaries(): void
    {
        $challenge = Challenge::factory()->create();
        Submission::create([
            'challenge_id'   => $challenge->id,
            'profile_id'     => $this->student->profile->id,
            'attempt_number' => 1,
            'status'         => 'pending',
            'submitted_at'   => now(),
        ]);

        $response = $this->actingAs($this->teacher, 'sanctum')
            ->getJson('/api/teacher/dashboard');

        $response->assertOk();
        $pending = $response->json('data.pending_submissions');
        $this->assertNotEmpty($pending);
        $this->assertArrayHasKey('challenge', $pending[0]);
        $this->assertArrayHasKey('profile', $pending[0]);
        $this->assertArrayHasKey('status', $pending[0]);
        $this->assertArrayHasKey('submitted_at', $pending[0]);
        $this->assertSame('pending', $pending[0]['status']);
    }

    public function test_dashboard_pending_queue_excludes_non_pending_submissions(): void
    {
        $challenge = Challenge::factory()->create();
        Submission::create([
            'challenge_id'   => $challenge->id,
            'profile_id'     => $this->student->profile->id,
            'attempt_number' => 1,
            'status'         => 'graded',
            'submitted_at'   => now(),
        ]);

        $response = $this->actingAs($this->teacher, 'sanctum')
            ->getJson('/api/teacher/dashboard');

        $response->assertOk();
        $this->assertEmpty($response->json('data.pending_submissions'));
    }

    public function test_dashboard_pending_queue_capped_at_ten(): void
    {
        $challenge = Challenge::factory()->create();
        for ($i = 1; $i <= 15; $i++) {
            Submission::create([
                'challenge_id'   => $challenge->id,
                'profile_id'     => $this->student->profile->id,
                'attempt_number' => $i,
                'status'         => 'pending',
                'submitted_at'   => now(),
            ]);
        }

        $response = $this->actingAs($this->teacher, 'sanctum')
            ->getJson('/api/teacher/dashboard');

        $response->assertOk();
        $this->assertCount(10, $response->json('data.pending_submissions'));
    }

    // -------------------------------------------------------------------------
    // Submissions — authorization
    // -------------------------------------------------------------------------

    public function test_teacher_can_list_submissions(): void
    {
        $this->actingAs($this->teacher, 'sanctum')
            ->getJson('/api/teacher/submissions')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_admin_can_list_submissions(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/teacher/submissions')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_student_cannot_list_submissions(): void
    {
        $this->actingAs($this->student, 'sanctum')
            ->getJson('/api/teacher/submissions')
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_list_submissions(): void
    {
        $this->getJson('/api/teacher/submissions')
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Submissions — response shape
    // -------------------------------------------------------------------------

    public function test_submission_item_has_expected_fields(): void
    {
        $challenge = Challenge::factory()->create();
        Submission::create([
            'challenge_id'   => $challenge->id,
            'profile_id'     => $this->student->profile->id,
            'attempt_number' => 1,
            'status'         => 'pending',
            'submitted_at'   => now(),
        ]);

        $response = $this->actingAs($this->teacher, 'sanctum')
            ->getJson('/api/teacher/submissions');

        $response->assertOk();
        $item = $response->json('data.0');
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('status', $item);
        $this->assertArrayHasKey('score', $item);
        $this->assertArrayHasKey('challenge', $item);
        $this->assertArrayHasKey('profile', $item);
        $this->assertArrayHasKey('submitted_at', $item);
        $this->assertArrayHasKey('feedback', $item);
    }

    public function test_submission_score_block_contains_auto_manual_total(): void
    {
        $challenge = Challenge::factory()->create();
        Submission::create([
            'challenge_id'   => $challenge->id,
            'profile_id'     => $this->student->profile->id,
            'attempt_number' => 1,
            'status'         => 'graded',
            'auto_score'     => 40,
            'manual_score'   => 20,
            'submitted_at'   => now(),
        ]);

        $response = $this->actingAs($this->teacher, 'sanctum')
            ->getJson('/api/teacher/submissions');

        $score = $response->json('data.0.score');
        $this->assertSame(40, $score['auto']);
        $this->assertSame(20, $score['manual']);
        $this->assertSame(60, $score['total']);
    }

    // -------------------------------------------------------------------------
    // Submissions — filters
    // -------------------------------------------------------------------------

    public function test_filter_by_status(): void
    {
        $challenge = Challenge::factory()->create();
        Submission::create([
            'challenge_id'   => $challenge->id,
            'profile_id'     => $this->student->profile->id,
            'attempt_number' => 1,
            'status'         => 'pending',
            'submitted_at'   => now(),
        ]);
        Submission::create([
            'challenge_id'   => $challenge->id,
            'profile_id'     => $this->student->profile->id,
            'attempt_number' => 2,
            'status'         => 'graded',
            'submitted_at'   => now(),
        ]);

        $response = $this->actingAs($this->teacher, 'sanctum')
            ->getJson('/api/teacher/submissions?status=pending');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('pending', $response->json('data.0.status'));
    }

    public function test_filter_by_challenge_id(): void
    {
        $challengeA = Challenge::factory()->create();
        $challengeB = Challenge::factory()->create();

        Submission::create([
            'challenge_id'   => $challengeA->id,
            'profile_id'     => $this->student->profile->id,
            'attempt_number' => 1,
            'status'         => 'pending',
            'submitted_at'   => now(),
        ]);
        Submission::create([
            'challenge_id'   => $challengeB->id,
            'profile_id'     => $this->student->profile->id,
            'attempt_number' => 1,
            'status'         => 'pending',
            'submitted_at'   => now(),
        ]);

        $response = $this->actingAs($this->teacher, 'sanctum')
            ->getJson("/api/teacher/submissions?challenge_id={$challengeA->id}");

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($challengeA->id, $response->json('data.0.challenge.id'));
    }

    public function test_filter_by_profile_id(): void
    {
        $studentRole  = Role::firstWhere('name', 'student');
        $otherStudent = $this->makeUserWithRole($studentRole);

        $challenge = Challenge::factory()->create();
        Submission::create([
            'challenge_id'   => $challenge->id,
            'profile_id'     => $this->student->profile->id,
            'attempt_number' => 1,
            'status'         => 'pending',
            'submitted_at'   => now(),
        ]);
        Submission::create([
            'challenge_id'   => $challenge->id,
            'profile_id'     => $otherStudent->profile->id,
            'attempt_number' => 1,
            'status'         => 'pending',
            'submitted_at'   => now(),
        ]);

        $response = $this->actingAs($this->teacher, 'sanctum')
            ->getJson("/api/teacher/submissions?profile_id={$this->student->profile->id}");

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($this->student->profile->id, $response->json('data.0.profile.id'));
    }

    public function test_filters_can_be_combined(): void
    {
        $challengeA = Challenge::factory()->create();
        $challengeB = Challenge::factory()->create();

        Submission::create([
            'challenge_id'   => $challengeA->id,
            'profile_id'     => $this->student->profile->id,
            'attempt_number' => 1,
            'status'         => 'pending',
            'submitted_at'   => now(),
        ]);
        Submission::create([
            'challenge_id'   => $challengeA->id,
            'profile_id'     => $this->student->profile->id,
            'attempt_number' => 2,
            'status'         => 'graded',
            'submitted_at'   => now(),
        ]);
        Submission::create([
            'challenge_id'   => $challengeB->id,
            'profile_id'     => $this->student->profile->id,
            'attempt_number' => 1,
            'status'         => 'pending',
            'submitted_at'   => now(),
        ]);

        $response = $this->actingAs($this->teacher, 'sanctum')
            ->getJson("/api/teacher/submissions?status=pending&challenge_id={$challengeA->id}");

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
    }

    // -------------------------------------------------------------------------
    // Submissions — pagination
    // -------------------------------------------------------------------------

    public function test_pagination_meta_is_correct(): void
    {
        $challenge = Challenge::factory()->create();
        for ($i = 1; $i <= 5; $i++) {
            Submission::create([
                'challenge_id'   => $challenge->id,
                'profile_id'     => $this->student->profile->id,
                'attempt_number' => $i,
                'status'         => 'pending',
                'submitted_at'   => now(),
            ]);
        }

        $response = $this->actingAs($this->teacher, 'sanctum')
            ->getJson('/api/teacher/submissions?per_page=2&page=1');

        $response->assertOk();
        $this->assertSame(5, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.per_page'));
        $this->assertSame(1, $response->json('meta.current_page'));
        $this->assertCount(2, $response->json('data'));
    }

    public function test_pagination_second_page_returns_correct_items(): void
    {
        $challenge = Challenge::factory()->create();
        for ($i = 1; $i <= 5; $i++) {
            Submission::create([
                'challenge_id'   => $challenge->id,
                'profile_id'     => $this->student->profile->id,
                'attempt_number' => $i,
                'status'         => 'pending',
                'submitted_at'   => now(),
            ]);
        }

        $response = $this->actingAs($this->teacher, 'sanctum')
            ->getJson('/api/teacher/submissions?per_page=2&page=2');

        $response->assertOk();
        $this->assertSame(2, $response->json('meta.current_page'));
        $this->assertCount(2, $response->json('data'));
    }

    public function test_per_page_defaults_to_fifteen(): void
    {
        $this->actingAs($this->teacher, 'sanctum')
            ->getJson('/api/teacher/submissions')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 15);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeUserWithRole(Role $role): User
    {
        $user = User::factory()->withProfile()->create();
        $user->profile->roles()->attach($role);

        return $user;
    }
}
