<?php

namespace Tests\Feature\Teacher;

use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Profile;
use App\Models\Role;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;
    private User $student;
    private Profile $otherProfile;
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
        $this->otherProfile = $this->userWithRole($roles['teacher'])->profile;

        $this->track = Track::factory()->create(['created_by' => $this->otherProfile->id]);
        $this->module = Module::factory()->create([
            'track_id' => $this->track->id,
            'created_by' => $this->otherProfile->id,
        ]);
        $this->lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->otherProfile->id,
        ]);
        $this->challenge = Challenge::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->otherProfile->id,
        ]);
    }

    public static function contentEndpoints(): array
    {
        return [
            'track' => ['/api/tracks', 'tracks', fn (self $test) => $test->track, fn (self $test) => [
                'title' => 'New Track',
                'slug' => 'new-track',
            ], fn () => ['title' => 'Updated Track']],
            'module' => ['/api/modules', 'modules', fn (self $test) => $test->module, fn (self $test) => [
                'track_id' => $test->track->id,
                'title' => 'New Module',
                'slug' => 'new-module',
            ], fn () => ['title' => 'Updated Module']],
            'lesson' => ['/api/lessons', 'lessons', fn (self $test) => $test->lesson, fn (self $test) => [
                'module_id' => $test->module->id,
                'title' => 'New Lesson',
                'slug' => 'new-lesson',
                'content' => 'Lesson content',
            ], fn () => ['title' => 'Updated Lesson']],
            'challenge' => ['/api/challenges', 'challenges', fn (self $test) => $test->challenge, fn (self $test) => [
                'module_id' => $test->module->id,
                'title' => 'New Challenge',
                'slug' => 'new-challenge',
                'type' => 'multiple_choice',
                'content' => 'Challenge content',
                'max_score' => 100,
            ], fn () => ['title' => 'Updated Challenge']],
        ];
    }

    #[DataProvider('contentEndpoints')]
    public function test_teachers_and_admins_can_store_content(
        string $endpoint,
        string $table,
        callable $resource,
        callable $storePayload,
    ): void {
        foreach ([$this->teacher, $this->admin] as $user) {
            $payload = $storePayload($this);
            $payload['slug'] .= '-' . $user->id;

            $this->actingAs($user)
                ->postJson($endpoint, $payload)
                ->assertCreated();
        }
    }

    #[DataProvider('contentEndpoints')]
    public function test_teachers_and_admins_can_update_content_with_put_and_patch(
        string $endpoint,
        string $table,
        callable $resource,
        callable $storePayload,
        callable $updatePayload,
    ): void {
        foreach ([$this->teacher, $this->admin] as $user) {
            foreach (['putJson', 'patchJson'] as $method) {
                $model = $resource($this);

                $this->actingAs($user)
                    ->{$method}("{$endpoint}/{$model->getRouteKey()}", $updatePayload())
                    ->assertOk();
            }
        }
    }

    #[DataProvider('contentEndpoints')]
    public function test_students_cannot_store_or_update_content(
        string $endpoint,
        string $table,
        callable $resource,
        callable $storePayload,
        callable $updatePayload,
    ): void {
        $this->actingAs($this->student)
            ->postJson($endpoint, $storePayload($this))
            ->assertForbidden();

        $model = $resource($this);

        $this->actingAs($this->student)
            ->putJson("{$endpoint}/{$model->getRouteKey()}", $updatePayload())
            ->assertForbidden();

        $this->actingAs($this->student)
            ->patchJson("{$endpoint}/{$model->getRouteKey()}", $updatePayload())
            ->assertForbidden();
    }

    #[DataProvider('contentEndpoints')]
    public function test_guests_cannot_store_or_update_content(
        string $endpoint,
        string $table,
        callable $resource,
        callable $storePayload,
        callable $updatePayload,
    ): void {
        $this->postJson($endpoint, $storePayload($this))->assertUnauthorized();

        $model = $resource($this);

        $this->putJson("{$endpoint}/{$model->getRouteKey()}", $updatePayload())
            ->assertUnauthorized();

        $this->patchJson("{$endpoint}/{$model->getRouteKey()}", $updatePayload())
            ->assertUnauthorized();
    }

    public function test_teacher_or_admin_middleware_allows_teachers_and_admins_only(): void
    {
        Route::middleware('teacher_or_admin')->get('/teacher-or-admin-check', fn () => response()->json(['ok' => true]));

        $this->getJson('/teacher-or-admin-check')->assertUnauthorized();
        $this->actingAs($this->student)->getJson('/teacher-or-admin-check')->assertForbidden();
        $this->actingAs($this->teacher)->getJson('/teacher-or-admin-check')->assertOk();
        $this->actingAs($this->admin)->getJson('/teacher-or-admin-check')->assertOk();
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->withProfile()->create();
        $user->profile->roles()->attach($role);

        return $user;
    }
}
