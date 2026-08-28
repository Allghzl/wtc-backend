<?php

namespace Tests\Feature\Teacher;

use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Role;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;
    private User $student;
    private Track $track;
    private Module $module;
    private Lesson $lesson;
    private Challenge $challenge;

    protected function setUp(): void
    {
        parent::setUp();

        $roles = collect(['admin', 'teacher', 'student'])->mapWithKeys(
            fn (string $name) => [$name => Role::create(['name' => $name])]
        );

        $this->admin = $this->userWithRole($roles['admin']);
        $this->teacher = $this->userWithRole($roles['teacher']);
        $this->student = $this->userWithRole($roles['student']);
        $creator = $this->teacher->profile;

        $this->track = Track::factory()->create(['created_by' => $creator->id]);
        $this->module = Module::factory()->create([
            'track_id' => $this->track->id,
            'created_by' => $creator->id,
        ]);
        $this->lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $creator->id,
        ]);
        $this->challenge = Challenge::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $creator->id,
        ]);
    }

    public function test_students_cannot_read_all_student_submissions_or_audit_history(): void
    {
        $this->actingAs($this->student, 'sanctum')
            ->getJson("/api/challenges/{$this->challenge->getRouteKey()}/submissions")
            ->assertForbidden();

        foreach ($this->auditEndpoints() as $endpoint) {
            $this->actingAs($this->student, 'sanctum')
                ->getJson($endpoint)
                ->assertForbidden();
        }
    }

    public function test_teachers_and_admins_can_read_staff_submission_and_audit_endpoints(): void
    {
        foreach ([$this->teacher, $this->admin] as $user) {
            $this->actingAs($user, 'sanctum')
                ->getJson("/api/challenges/{$this->challenge->getRouteKey()}/submissions")
                ->assertOk();

            foreach ($this->auditEndpoints() as $endpoint) {
                $this->actingAs($user, 'sanctum')->getJson($endpoint)->assertOk();
            }
        }
    }

    public function test_audit_logs_reject_non_content_auditable_types(): void
    {
        $this->actingAs($this->teacher, 'sanctum')
            ->getJson('/api/audit-logs?auditable_type=' . urlencode(User::class))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('auditable_type');
    }

    public function test_default_audit_logs_exclude_submission_audits(): void
    {
        config(['audit.console' => true]);

        $this->actingAs($this->teacher, 'sanctum');
        $content = Track::factory()->create(['created_by' => $this->teacher->profile->id]);
        $submission = \App\Models\Submission::create([
            'challenge_id' => $this->challenge->id,
            'profile_id' => $this->student->profile->id,
            'attempt_number' => 1,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        $submission->update(['status' => 'graded']);

        $auditTypes = $this->getJson('/api/audit-logs')
            ->assertOk()
            ->json('data.audit_logs.*.auditable_type');

        $this->assertContains(Track::class, $auditTypes);
        $this->assertNotContains(\App\Models\Submission::class, $auditTypes);
    }

    public function test_audit_actor_metadata_contains_only_display_name_roles_and_avatar(): void
    {
        config(['audit.console' => true]);
        $this->actingAs($this->teacher, 'sanctum');
        $track = Track::factory()->create(['created_by' => $this->teacher->profile->id]);

        $actor = $this->getJson('/api/audit-logs?auditable_type=' . urlencode(Track::class))
            ->assertOk()
            ->json('data.audit_logs.0.profile');

        $this->assertSame(['display_name', 'roles', 'avatar'], array_keys($actor));
    }

    /** @return array<int, string> */
    private function auditEndpoints(): array
    {
        return [
            '/api/audit-logs',
            "/api/tracks/{$this->track->getRouteKey()}/audit-log",
            "/api/modules/{$this->module->getRouteKey()}/audit-log",
            "/api/lessons/{$this->lesson->getRouteKey()}/audit-log",
            "/api/challenges/{$this->challenge->getRouteKey()}/audit-log",
        ];
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->withProfile()->create();
        $user->profile->roles()->attach($role);

        return $user;
    }
}
