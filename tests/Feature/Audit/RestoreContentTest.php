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

    public function test_deleting_track_cascades_to_modules_lessons_and_challenges(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $lessonChallenge = Challenge::factory()->create([
            'module_id' => null,
            'lesson_id' => $lesson->id,
        ]);
        $moduleChallenge = Challenge::factory()->create([
            'module_id' => $module->id,
            'lesson_id' => null,
        ]);

        $track->delete();

        $this->assertSoftDeleted('tracks', ['id' => $track->id]);
        $this->assertSoftDeleted('modules', ['id' => $module->id]);
        $this->assertSoftDeleted('lessons', ['id' => $lesson->id]);
        $this->assertSoftDeleted('challenges', ['id' => $lessonChallenge->id]);
        $this->assertSoftDeleted('challenges', ['id' => $moduleChallenge->id]);
    }

    public function test_deleting_track_does_not_affect_study_classes(): void
    {
        $studyClass = \App\Models\StudyClass::factory()->create();
        $track = Track::factory()->create();
        $studyClass->tracks()->attach($track->id);

        $track->delete();

        $this->assertNotSoftDeleted('study_classes', ['id' => $studyClass->id]);
        $this->assertDatabaseHas('study_class_tracks', [
            'study_class_id' => $studyClass->id,
            'track_id' => $track->id,
        ]);
    }

    public function test_deleting_module_cascades_to_lessons_and_challenges(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $challenge = Challenge::factory()->create([
            'module_id' => null,
            'lesson_id' => $lesson->id,
        ]);

        $module->delete();

        $this->assertSoftDeleted('lessons', ['id' => $lesson->id]);
        $this->assertSoftDeleted('challenges', ['id' => $challenge->id]);
        $this->assertNotSoftDeleted('tracks', ['id' => $track->id]);
    }

    public function test_restoring_track_restores_its_whole_curriculum(): void
    {
        $this->actingAs($this->adminUser);

        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $challenge = Challenge::factory()->create([
            'module_id' => $module->id,
            'lesson_id' => null,
        ]);

        $track->delete();

        $response = $this->postJson("/api/admin/tracks/{$track->id}/restore");
        $response->assertStatus(200);

        $this->assertNotSoftDeleted('tracks', ['id' => $track->id]);
        $this->assertNotSoftDeleted('modules', ['id' => $module->id]);
        $this->assertNotSoftDeleted('lessons', ['id' => $lesson->id]);
        $this->assertNotSoftDeleted('challenges', ['id' => $challenge->id]);
    }

    public function test_restoring_module_restores_its_lessons_and_challenges(): void
    {
        $this->actingAs($this->adminUser);

        $module = Module::factory()->create();
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $lessonChallenge = Challenge::factory()->create(['module_id' => null, 'lesson_id' => $lesson->id]);
        $moduleChallenge = Challenge::factory()->create(['module_id' => $module->id, 'lesson_id' => null]);

        $module->delete();

        $this->postJson("/api/admin/modules/{$module->id}/restore")->assertStatus(200);

        $this->assertNotSoftDeleted('lessons', ['id' => $lesson->id]);
        $this->assertNotSoftDeleted('challenges', ['id' => $lessonChallenge->id]);
        $this->assertNotSoftDeleted('challenges', ['id' => $moduleChallenge->id]);
    }

    public function test_restoring_a_lesson_also_restores_its_trashed_ancestors(): void
    {
        $this->actingAs($this->adminUser);

        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        $track->delete();

        $this->postJson("/api/admin/lessons/{$lesson->id}/restore")->assertStatus(200);

        $this->assertNotSoftDeleted('lessons', ['id' => $lesson->id]);
        $this->assertNotSoftDeleted('modules', ['id' => $module->id]);
        $this->assertNotSoftDeleted('tracks', ['id' => $track->id]);
    }

    public function test_admin_can_permanently_delete_a_trashed_track(): void
    {
        $this->actingAs($this->adminUser);

        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $challenge = Challenge::factory()->create([
            'module_id' => $module->id,
            'lesson_id' => null,
        ]);
        $trackId = $track->id;
        $moduleId = $module->id;
        $lessonId = $lesson->id;
        $challengeId = $challenge->id;

        $track->delete();

        $response = $this->deleteJson("/api/admin/tracks/{$trackId}/force");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Track permanently deleted.']);

        $this->assertDatabaseMissing('tracks', ['id' => $trackId]);
        $this->assertDatabaseMissing('modules', ['id' => $moduleId]);
        $this->assertDatabaseMissing('lessons', ['id' => $lessonId]);
        $this->assertDatabaseMissing('challenges', ['id' => $challengeId]);
    }

    public function test_force_delete_requires_the_item_to_be_trashed(): void
    {
        $this->actingAs($this->adminUser);

        $track = Track::factory()->create();

        $this->deleteJson("/api/admin/tracks/{$track->id}/force")->assertStatus(404);
        $this->assertDatabaseHas('tracks', ['id' => $track->id, 'deleted_at' => null]);
    }

    public function test_trashed_track_list_includes_child_counts_and_pagination(): void
    {
        $this->actingAs($this->adminUser);

        $track = Track::factory()->create();
        Module::factory()->count(2)->create(['track_id' => $track->id]);
        $track->delete();

        $response = $this->getJson('/api/admin/tracks/trashed');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.modules_count', 2)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.deleted_at', fn ($v) => $v !== null);
    }

    public function test_trashed_module_list_exposes_parent_track_name(): void
    {
        $this->actingAs($this->adminUser);

        $track = Track::factory()->create(['title' => 'Track Induk']);
        $module = Module::factory()->create(['track_id' => $track->id]);
        Lesson::factory()->count(2)->create(['module_id' => $module->id]);
        $track->delete();

        $response = $this->getJson('/api/admin/modules/trashed');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.track.title', 'Track Induk')
            ->assertJsonPath('data.0.lessons_count', 2);
    }

    public function test_teacher_cannot_permanently_delete(): void
    {
        $this->actingAs($this->teacherUser);

        $track = Track::factory()->create();
        $track->delete();

        $this->deleteJson("/api/admin/tracks/{$track->id}/force")->assertStatus(403);
        $this->assertSoftDeleted('tracks', ['id' => $track->id]);
    }
}
