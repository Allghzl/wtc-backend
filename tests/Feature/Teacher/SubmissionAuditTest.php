<?php

namespace Tests\Feature\Teacher;

use App\Models\Challenge;
use App\Models\Module;
use App\Models\Role;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

class SubmissionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_grading_creates_an_actor_linked_audit_with_changed_grading_values(): void
    {
        config(['audit.console' => true]);

        $teacher = $this->userWithRole('teacher');
        $student = $this->userWithRole('student');
        $track = Track::factory()->create(['created_by' => $teacher->profile->id]);
        $module = Module::factory()->create([
            'track_id' => $track->id,
            'created_by' => $teacher->profile->id,
        ]);
        $challenge = Challenge::factory()->create([
            'module_id' => $module->id,
            'created_by' => $teacher->profile->id,
            'max_score' => 10,
        ]);
        $submission = Submission::create([
            'challenge_id' => $challenge->id,
            'profile_id' => $student->profile->id,
            'attempt_number' => 1,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->actingAs($teacher, 'sanctum')
            ->patchJson("/api/submissions/{$submission->id}", [
                'manual_score' => 8,
                'feedback' => 'Good work',
                'status' => 'graded',
            ])
            ->assertOk();

        $audit = Audit::query()
            ->where('event', 'updated')
            ->where('auditable_type', Submission::class)
            ->where('auditable_id', $submission->id)
            ->firstOrFail();

        $this->assertSame($teacher->id, $audit->user_id);
        $this->assertSame(8, $audit->new_values['manual_score']);
        $this->assertSame('Good work', $audit->new_values['feedback']);
        $this->assertSame('graded', $audit->new_values['status']);
        $this->assertSame('pending', $audit->old_values['status']);
    }

    public function test_audit_actor_uses_the_sanctum_user_uuid(): void
    {
        config(['audit.console' => true]);

        $teacher = $this->userWithRole('teacher');
        $submission = Submission::create([
            'challenge_id' => Challenge::factory()->create()->id,
            'profile_id' => $this->userWithRole('student')->profile->id,
            'attempt_number' => 1,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->actingAs($teacher, 'sanctum');
        $submission->update(['status' => 'graded']);

        $this->assertSame($teacher->getKey(), Audit::query()->latest('id')->value('user_id'));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->withProfile()->create();
        $user->profile->roles()->attach(Role::create(['name' => $role]));

        return $user;
    }
}
