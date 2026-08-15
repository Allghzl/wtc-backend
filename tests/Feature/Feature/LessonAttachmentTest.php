<?php

namespace Tests\Feature\Feature;

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
        $teacherRole = Role::factory()->create(['name' => 'teacher']);
        $studentRole = Role::factory()->create(['name' => 'student']);

        // Create users
        $this->teacher = User::factory()->create();
        $this->teacher->roles()->attach($teacherRole);

        $this->student = User::factory()->create();
        $this->student->roles()->attach($studentRole);

        // Create lesson
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $this->lesson = Lesson::factory()->create(['module_id' => $module->id]);

        // Fake S3 storage
        Storage::fake('s3');
    }

    /** @test */
    public function teacher_can_upload_attachment_to_lesson()
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

        Storage::disk('s3')->assertExists(
            Attachment::where('lesson_id', $this->lesson->id)->first()->file_path
        );
    }

    /** @test */
    public function student_cannot_upload_attachment()
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

    /** @test */
    public function teacher_can_list_lesson_attachments()
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

    /** @test */
    public function student_can_list_lesson_attachments()
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

    /** @test */
    public function teacher_can_delete_lesson_attachment()
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
        Storage::disk('s3')->assertMissing($attachment->file_path);
    }

    /** @test */
    public function student_cannot_delete_attachment()
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

    /** @test */
    public function upload_requires_valid_file()
    {
        $response = $this->actingAs($this->teacher)
            ->postJson("/api/lessons/{$this->lesson->id}/attachments", [
                'title' => 'Test',
                'type' => 'material',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    /** @test */
    public function upload_requires_title()
    {
        $file = UploadedFile::fake()->create('document.pdf', 1024);

        $response = $this->actingAs($this->teacher)
            ->postJson("/api/lessons/{$this->lesson->id}/attachments", [
                'file' => $file,
                'type' => 'material',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    /** @test */
    public function upload_requires_valid_type()
    {
        $file = UploadedFile::fake()->create('document.pdf', 1024);

        $response = $this->actingAs($this->teacher)
            ->postJson("/api/lessons/{$this->lesson->id}/attachments", [
                'file' => $file,
                'title' => 'Test',
                'type' => 'invalid_type',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    /** @test */
    public function upload_rejects_unsupported_file_types()
    {
        $file = UploadedFile::fake()->create('malware.exe', 1024, 'application/x-msdownload');

        $response = $this->actingAs($this->teacher)
            ->postJson("/api/lessons/{$this->lesson->id}/attachments", [
                'file' => $file,
                'title' => 'Test',
                'type' => 'material',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    /** @test */
    public function authenticated_user_can_generate_file_url()
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

    /** @test */
    public function cannot_delete_attachment_from_wrong_lesson()
    {
        $otherLesson = Lesson::factory()->create(['module_id' => $this->lesson->module_id]);
        $attachment = Attachment::factory()->create([
            'lesson_id' => $otherLesson->id,
            'challenge_id' => null,
        ]);

        $response = $this->actingAs($this->teacher)
            ->deleteJson("/api/lessons/{$this->lesson->id}/attachments/{$attachment->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
    }
}
