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
use Tests\TestCase;

class RestoreContentTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $teacherUser;
    protected Profile $adminProfile;
    protected Profile $teacherProfile;
    protected Role $adminRole;
    protected Role $teacherRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $this->adminRole = Role::create(['name' => 'admin']);
        $this->teacherRole = Role::create(['name' => 'teacher']);

        // Create admin user with profile and role
        $this->adminUser = User::factory()->withProfile()->create();
        $this->adminProfile = $this->adminUser->profile;
        $this->adminProfile->roles()->attach($this->adminRole->id);

        // Create teacher user with profile and role
        $this->teacherUser = User::factory()->withProfile()->create();
        $this->teacherProfile = $this->teacherUser->profile;
        $this->teacherProfile->roles()->attach($this->teacherRole->id);
    }

    public function test_admin_can_restore_soft_deleted_track(): void
    {
        $this->actingAs($this->adminUser);

        $track = Track::factory()->create();
        $trackId = $track->id;

        // Soft delete the track
        $track->delete();
        $this->assertSoftDeleted('tracks', ['id' => $trackId]);

        // Restore the track
        $response = $this->postJson("/api/admin/tracks/{$trackId}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Track restored successfully.',
            ]);

        $this->assertDatabaseHas('tracks', ['id' => $trackId, 'deleted_at' => null]);
    }

    public function test_teacher_cannot_restore_soft_deleted_track(): void
    {
        $this->actingAs($this->teacherUser);

        $track = Track::factory()->create();
        $trackId = $track->id;

        // Soft delete the track
        $track->delete();

        // Try to restore the track as teacher
        $response = $this->postJson("/api/admin/tracks/{$trackId}/restore");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden. Admin access required.',
            ]);

        // Verify track is still soft deleted
        $this->assertSoftDeleted('tracks', ['id' => $trackId]);
    }

    public function test_admin_can_restore_soft_deleted_module(): void
    {
        $this->actingAs($this->adminUser);

        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $moduleId = $module->id;

        // Soft delete the module
        $module->delete();
        $this->assertSoftDeleted('modules', ['id' => $moduleId]);

        // Restore the module
        $response = $this->postJson("/api/admin/modules/{$moduleId}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Module restored successfully.',
            ]);

        $this->assertDatabaseHas('modules', ['id' => $moduleId, 'deleted_at' => null]);
    }

    public function test_teacher_cannot_restore_soft_deleted_module(): void
    {
        $this->actingAs($this->teacherUser);

        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $moduleId = $module->id;

        // Soft delete the module
        $module->delete();

        // Try to restore the module as teacher
        $response = $this->postJson("/api/admin/modules/{$moduleId}/restore");

        $response->assertStatus(403);

        // Verify module is still soft deleted
        $this->assertSoftDeleted('modules', ['id' => $moduleId]);
    }

    public function test_admin_can_restore_soft_deleted_lesson(): void
    {
        $this->actingAs($this->adminUser);

        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $lessonId = $lesson->id;

        // Soft delete the lesson
        $lesson->delete();
        $this->assertSoftDeleted('lessons', ['id' => $lessonId]);

        // Restore the lesson
        $response = $this->postJson("/api/admin/lessons/{$lessonId}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lesson restored successfully.',
            ]);

        $this->assertDatabaseHas('lessons', ['id' => $lessonId, 'deleted_at' => null]);
    }

    public function test_admin_can_restore_soft_deleted_challenge(): void
    {
        $this->actingAs($this->adminUser);

        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $challenge = Challenge::factory()->create(['module_id' => $module->id]);
        $challengeId = $challenge->id;

        // Soft delete the challenge
        $challenge->delete();
        $this->assertSoftDeleted('challenges', ['id' => $challengeId]);

        // Restore the challenge
        $response = $this->postJson("/api/admin/challenges/{$challengeId}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Challenge restored successfully.',
            ]);

        $this->assertDatabaseHas('challenges', ['id' => $challengeId, 'deleted_at' => null]);
    }

    public function test_restore_returns_404_if_content_not_found_or_not_deleted(): void
    {
        $this->actingAs($this->adminUser);

        // Try to restore non-existent track
        $response = $this->postJson("/api/admin/tracks/99999/restore");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Track not found or not deleted.',
            ]);

        // Try to restore a track that is not deleted
        $track = Track::factory()->create();
        $response = $this->postJson("/api/admin/tracks/{$track->id}/restore");

        $response->assertStatus(404);
    }
}
