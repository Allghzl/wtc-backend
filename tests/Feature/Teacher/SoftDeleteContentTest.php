<?php

namespace Tests\Feature\Teacher;

use App\Models\Attachment;
use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Profile;
use App\Models\Role;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SoftDeleteContentTest extends TestCase
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
        $creator = $this->userWithRole($roles['teacher'])->profile;

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

        Storage::fake('s3');
    }

    public static function contentResources(): array
    {
        return [
            'track' => ['tracks', 'track'],
            'module' => ['modules', 'module'],
            'lesson' => ['lessons', 'lesson'],
            'challenge' => ['challenges', 'challenge'],
        ];
    }

    #[DataProvider('contentResources')]
    public function test_teacher_soft_deletes_content_while_normal_reads_exclude_it_and_only_admin_restores(
        string $resource,
        string $property,
    ): void {
        $content = $this->{$property};

        $this->actingAs($this->teacher)
            ->deleteJson("/api/{$resource}/{$content->getRouteKey()}")
            ->assertOk();

        $this->assertSoftDeleted($content->getTable(), ['id' => $content->id]);
        $this->actingAs($this->teacher)
            ->getJson("/api/{$resource}?pagination=false")
            ->assertNotFound();

        $this->actingAs($this->teacher)
            ->postJson("/api/admin/{$resource}/{$content->id}/restore")
            ->assertForbidden();
        $this->assertSoftDeleted($content->getTable(), ['id' => $content->id]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/{$resource}/{$content->id}/restore")
            ->assertOk();
        $this->assertDatabaseHas($content->getTable(), ['id' => $content->id, 'deleted_at' => null]);
    }

    #[DataProvider('contentResources')]
    public function test_admin_can_delete_content(string $resource, string $property): void
    {
        $content = $this->{$property};

        $this->actingAs($this->admin)
            ->deleteJson("/api/{$resource}/{$content->getRouteKey()}")
            ->assertOk();

        $this->assertSoftDeleted($content->getTable(), ['id' => $content->id]);
    }

    #[DataProvider('contentResources')]
    public function test_student_cannot_delete_content(string $resource, string $property): void
    {
        $content = $this->{$property};

        $this->actingAs($this->student)
            ->deleteJson("/api/{$resource}/{$content->getRouteKey()}")
            ->assertForbidden();

        $this->assertDatabaseHas($content->getTable(), ['id' => $content->id, 'deleted_at' => null]);
    }

    public static function attachmentParents(): array
    {
        return [
            'lesson' => ['lessons', 'lesson_id', 'lesson'],
            'challenge' => ['challenges', 'challenge_id', 'challenge'],
        ];
    }

    #[DataProvider('attachmentParents')]
    public function test_teacher_and_admin_can_upload_and_delete_attachments(
        string $resource,
        string $foreignKey,
        string $property,
    ): void {
        $parent = $this->{$property};

        foreach ([$this->teacher, $this->admin] as $user) {
            $this->actingAs($user)
                ->postJson("/api/{$resource}/{$parent->getRouteKey()}/attachments", [
                    'file' => UploadedFile::fake()->create('material.pdf', 1, 'application/pdf'),
                    'title' => 'Material',
                    'type' => 'material',
                ])
                ->assertCreated();

            $attachment = Attachment::where($foreignKey, $parent->id)->latest('id')->firstOrFail();

            $this->actingAs($user)
                ->deleteJson("/api/{$resource}/{$parent->getRouteKey()}/attachments/{$attachment->id}")
                ->assertOk();

            $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        }
    }

    #[DataProvider('attachmentParents')]
    public function test_student_cannot_write_or_delete_attachments(
        string $resource,
        string $foreignKey,
        string $property,
    ): void {
        $parent = $this->{$property};
        $attachment = Attachment::factory()->create([$foreignKey => $parent->id]);

        $this->actingAs($this->student)
            ->postJson("/api/{$resource}/{$parent->getRouteKey()}/attachments", [
                'file' => UploadedFile::fake()->create('material.pdf', 1, 'application/pdf'),
                'title' => 'Material',
                'type' => 'material',
            ])
            ->assertForbidden();

        $this->actingAs($this->student)
            ->deleteJson("/api/{$resource}/{$parent->getRouteKey()}/attachments/{$attachment->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->withProfile()->create();
        $user->profile->roles()->attach($role);

        return $user;
    }
}
