<?php

namespace Tests\Feature\Audit;

use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Profile;
use App\Models\Role;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Profile $profile;
    protected Role $teacherRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create teacher role
        $this->teacherRole = Role::create(['name' => 'teacher']);

        // Create a user with profile
        $this->user = User::factory()->withProfile()->create();
        $this->profile = $this->user->profile;
        $this->profile->roles()->attach($this->teacherRole);
    }

    public function test_it_creates_audit_log_on_track_creation(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/tracks', [
            'title' => 'Test Track',
            'slug' => 'test-track',
            'description' => 'Test description',
            'order' => 1,
        ]);

        $response->assertStatus(201);

        $track = Track::where('slug', 'test-track')->first();

        // Check audit log was created
        $audit = Audit::where('auditable_type', Track::class)
            ->where('auditable_id', $track->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->user->id, $audit->user_id);
    }

    public function test_it_creates_audit_log_on_track_update(): void
    {
        $this->actingAs($this->user);

        $track = Track::factory()->create(['title' => 'Original Title']);

        $response = $this->putJson("/api/tracks/{$track->slug}", [
            'title' => 'Updated Title',
            'description' => $track->description,
            'order' => $track->order,
        ]);

        $response->assertStatus(200);

        // Check audit log was created
        $audit = Audit::where('auditable_type', Track::class)
            ->where('auditable_id', $track->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->user->id, $audit->user_id);
        $this->assertEquals('Original Title', $audit->old_values['title']);
        $this->assertEquals('Updated Title', $audit->new_values['title']);
    }

    public function test_it_creates_audit_log_on_track_deletion(): void
    {
        $this->actingAs($this->user);

        $track = Track::factory()->create();

        $response = $this->deleteJson("/api/tracks/{$track->slug}");

        $response->assertStatus(200);

        // Check audit log was created
        $audit = Audit::where('auditable_type', Track::class)
            ->where('auditable_id', $track->id)
            ->where('event', 'deleted')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->user->id, $audit->user_id);
    }

    public function test_it_creates_audit_log_on_module_creation(): void
    {
        $this->actingAs($this->user);

        $track = Track::factory()->create();

        $response = $this->postJson('/api/modules', [
            'track_id' => $track->id,
            'title' => 'Test Module',
            'slug' => 'test-module',
            'order' => 1,
        ]);

        $response->assertStatus(201);

        $module = Module::where('slug', 'test-module')->first();

        // Check audit log was created
        $audit = Audit::where('auditable_type', Module::class)
            ->where('auditable_id', $module->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->user->id, $audit->user_id);
    }

    public function test_it_creates_audit_log_on_lesson_creation(): void
    {
        $this->actingAs($this->user);

        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);

        $response = $this->postJson('/api/lessons', [
            'module_id' => $module->id,
            'title' => 'Test Lesson',
            'slug' => 'test-lesson',
            'content' => 'Test content',
            'order' => 1,
        ]);

        $response->assertStatus(201);

        $lesson = Lesson::where('slug', 'test-lesson')->first();

        // Check audit log was created
        $audit = Audit::where('auditable_type', Lesson::class)
            ->where('auditable_id', $lesson->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->user->id, $audit->user_id);
    }

    public function test_it_creates_audit_log_on_challenge_creation(): void
    {
        $this->actingAs($this->user);

        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);

        $response = $this->postJson('/api/challenges', [
            'module_id' => $module->id,
            'title' => 'Test Challenge',
            'slug' => 'test-challenge',
            'type' => 'multiple_choice',
            'content' => 'Test content',
            'max_score' => 100,
        ]);

        $response->assertStatus(201);

        $challenge = Challenge::where('slug', 'test-challenge')->first();

        // Check audit log was created
        $audit = Audit::where('auditable_type', Challenge::class)
            ->where('auditable_id', $challenge->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->user->id, $audit->user_id);
    }

    public function test_audit_log_includes_profile_information(): void
    {
        $this->actingAs($this->user);

        $track = Track::factory()->create();

        // Get audit log with user relationship
        $audit = Audit::where('auditable_type', Track::class)
            ->where('auditable_id', $track->id)
            ->with('user.profile')
            ->first();

        $this->assertNotNull($audit);
        $this->assertNotNull($audit->user);
        $this->assertNotNull($audit->user->profile);
        $this->assertEquals($this->profile->id, $audit->user->profile->id);
    }
}
