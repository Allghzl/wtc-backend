<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Challenge;
use App\Models\Module;
use App\Models\Role;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChallengeAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $student;
    protected Challenge $challenge;

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

        // Create challenge
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $this->challenge = Challenge::factory()->create(['module_id' => $module->id]);

        // Fake S3 storage
        Storage::fake('s3');
    }

    /** @test */
    public function teacher_can_upload_attachment_to_challenge()
    {
        $file = UploadedFile::fake()->create('starter.zip', 2048, 'application/zip');

        $response = $this->actingAs($this->teacher)
            ->postJson("/api/challenges/{$this->challenge->id}/attachments", [
                'file' => $file,
                'title' => 'Starter Project',
                'description' => 'Initial project files',
                'type' => 'starter_file',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('attachments', [
            'challenge_id' => $this->challenge->id,
            'lesson_id' => null,
            'title' => 'Starter Project',
            'type' => 'starter_file',
        ]);
    }

    /** @test */
    public function student_cannot_upload_attachment_to_challenge()
    {
        $file = UploadedFile::fake()->create('document.pdf', 1024);

        $response = $this->actingAs($this->student)
            ->postJson("/api/challenges/{$this->challenge->id}/attachments", [
                'file' => $file,
                'title' => 'Test',
                'type' => 'example',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function teacher_can_list_challenge_attachments()
    {
        Attachment::factory()->count(3)->create([
            'challenge_id' => $this->challenge->id,
            'lesson_id' => null,
        ]);

        $response = $this->actingAs($this->teacher)
            ->getJson("/api/challenges/{$this->challenge->id}/attachments");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function teacher_can_delete_challenge_attachment()
    {
        $attachment = Attachment::factory()->create([
            'challenge_id' => $this->challenge->id,
            'lesson_id' => null,
        ]);

        Storage::disk('s3')->put($attachment->file_path, 'test content');

        $response = $this->actingAs($this->teacher)
            ->deleteJson("/api/challenges/{$this->challenge->id}/attachments/{$attachment->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }
}
