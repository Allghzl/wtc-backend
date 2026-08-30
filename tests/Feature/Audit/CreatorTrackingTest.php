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

class CreatorTrackingTest extends TestCase
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

    public function test_it_sets_created_by_when_creating_track(): void
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
        $this->assertNotNull($track);
        $this->assertEquals($this->profile->id, $track->created_by);
    }

    public function test_it_sets_created_by_when_creating_module(): void
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
        $this->assertNotNull($module);
        $this->assertEquals($this->profile->id, $module->created_by);
    }

    public function test_it_sets_created_by_when_creating_lesson(): void
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
        $this->assertNotNull($lesson);
        $this->assertEquals($this->profile->id, $lesson->created_by);
    }

    public function test_it_sets_created_by_when_creating_challenge(): void
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
        $this->assertNotNull($challenge);
        $this->assertEquals($this->profile->id, $challenge->created_by);
    }

    public function test_it_loads_creator_relationship_correctly(): void
    {
        $track = Track::factory()->create(['created_by' => $this->profile->id]);

        $track->load('creator');

        $this->assertNotNull($track->creator);
        $this->assertEquals($this->profile->id, $track->creator->id);
    }
}
