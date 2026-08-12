<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Role;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LessonAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $student;
    protected Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $teacherRole = Role::create(['name' => 'teacher']);
        $studentRole = Role::create(['name' => 'student']);

        // Create users
        $this->teacher = User::factory()->create();
        $this->teacher->profile->roles()->attach($teacherRole);

        $this->student = User::factory()->create();
        $this->student->profile->roles()->attach($studentRole);

        // Create lesson
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $this->lesson = Lesson::factory()->create(['module_id' => $module->id]);

        // Fake S3 storage
        Storage::fake('s3');
    }

    public function test_teacher_can_upload_attachment_to_lesson(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->teacher)
            ->postJson("/api/lessons/{$this->lesson->id}/attachments", [
                'file' => $file,
                'title' => 'Lesson Material',
                'description' => 'Main learning material',
                'type' => 'material',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'type',
                    'file_name',
                    'mime_type',
                    'size',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('attachments', [
            'lesson_id' => $this->lesson->id,
            'challenge_id' => null,
            'title' => 'Lesson Material',
            'type' => 'material',
        ]);

        $attachment = Attachment::where('lesson_id', $this->lesson->id)->first();
        $this->assertNotNull($attachment);
        Storage::disk('s3')->assertExists($attachment->file_path);
    }

    public function test_student_cannot_upload_attachment(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 1024);

        $response = $this->actingAs($this->student)
            ->postJson("/api/lessons/{$this->lesson->id}/attachments", [
                'file' => $file,
                'title' => 'Test',
                'type' => 'material',
            ]);

        $response->assertStatus(403);
    }

    public function test_teacher_can_list_lesson_attachments(): void
    {
        Attachment::factory()->count(3)->create([
            'lesson_id' => $this->lesson->id,
            'challenge_id' => null,
        ]);

        $response = $this->actingAs($this->teacher)
            ->getJson("/api/lessons/{$this->lesson->id}/attachments");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_student_can_list_lesson_attachments(): void
    {
        Attachment::factory()->count(2)->create([
            'lesson_id' => $this->lesson->id,
            'challenge_id' => null,
        ]);

        $response = $this->actingAs($this->student)
            ->getJson("/api/lessons/{$this->lesson->id}/attachments");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_teacher_can_delete_lesson_attachment(): void
    {
        $attachment = Attachment::factory()->create([
            'lesson_id' => $this->lesson->id,
            'challenge_id' => null,
        ]);

        Storage::disk('s3')->put($attachment->file_path, 'test content');

        $response = $this->actingAs($this->teacher)
            ->deleteJson("/api/lessons/{$this->lesson->id}/attachments/{$attachment->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        $this->assertFalse(Storage::disk('s3')->exists($attachment->file_path));
    }

    public function test_student_cannot_delete_attachment(): void
    {
        $attachment = Attachment::factory()->create([
            'lesson_id' => $this->lesson->id,
            'challenge_id' => null,
        ]);

        $response = $this->actingAs($this->student)
            ->deleteJson("/api/lessons/{$this->lesson->id}/attachments/{$attachment->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
    }

    public function test_authenticated_user_can_generate_file_url(): void
    {
        $attachment = Attachment::factory()->create([
            'lesson_id' => $this->lesson->id,
            'challenge_id' => null,
        ]);

        Storage::disk('s3')->put($attachment->file_path, 'test content');

        $response = $this->actingAs($this->student)
            ->getJson("/api/attachments/{$attachment->id}/file");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'file' => [
                        'name',
                        'url',
                        'expires_at',
                    ],
                ],
            ]);
    }
}
