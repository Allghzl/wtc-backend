<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChallengePaginationSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function test_index_returns_paginated_challenges_by_default(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        Challenge::factory()->count(20)->create(['module_id' => $module->id, 'lesson_id' => null]);

        $response = $this->actingAs($this->user)->getJson('/api/challenges');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total']
            ])
            ->assertJson(['success' => true])
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 20);

        $this->assertCount(15, $response->json('data'));
    }

    /** @test */
    public function test_pagination_can_be_disabled(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        Challenge::factory()->count(20)->create(['module_id' => $module->id, 'lesson_id' => null]);

        $response = $this->actingAs($this->user)->getJson('/api/challenges?pagination=false');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonMissing(['meta'])
            ->assertJsonCount(20, 'data');
    }

    /** @test */
    public function test_per_page_parameter_works(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        Challenge::factory()->count(25)->create(['module_id' => $module->id, 'lesson_id' => null]);

        $response = $this->actingAs($this->user)->getJson('/api/challenges?per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25);

        $this->assertCount(10, $response->json('data'));
    }

    /** @test */
    public function test_search_finds_matching_challenges_by_title(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        Challenge::factory()->create(['module_id' => $module->id, 'title' => 'Docker Compose Challenge']);
        Challenge::factory()->create(['module_id' => $module->id, 'title' => 'Kubernetes Quiz']);
        Challenge::factory()->create(['module_id' => $module->id, 'title' => 'Docker Networking Lab']);

        $response = $this->actingAs($this->user)->getJson('/api/challenges?search=Docker&pagination=false');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('Docker Compose Challenge', $titles);
        $this->assertContains('Docker Networking Lab', $titles);
        $this->assertNotContains('Kubernetes Quiz', $titles);
    }

    /** @test */
    public function test_search_finds_matching_challenges_by_content(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        Challenge::factory()->create(['module_id' => $module->id, 'content' => 'Build a containerization project']);
        Challenge::factory()->create(['module_id' => $module->id, 'content' => 'Deploy with orchestration']);
        Challenge::factory()->create(['module_id' => $module->id, 'content' => 'Advanced containerization']);

        $response = $this->actingAs($this->user)->getJson('/api/challenges?search=containerization&pagination=false');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function test_search_works_with_pagination(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        Challenge::factory()->count(10)->create(['module_id' => $module->id, 'title' => 'Docker Challenge']);
        Challenge::factory()->count(10)->create(['module_id' => $module->id, 'title' => 'Python Challenge']);

        $response = $this->actingAs($this->user)->getJson('/api/challenges?search=Docker&per_page=5');

        $response->assertStatus(200)
            ->assertJsonStructure(['meta'])
            ->assertJsonPath('meta.total', 10)
            ->assertJsonPath('meta.per_page', 5);

        $this->assertCount(5, $response->json('data'));
    }

    /** @test */
    public function test_search_works_with_module_id_filter(): void
    {
        $track = Track::factory()->create();
        $module1 = Module::factory()->create(['track_id' => $track->id]);
        $module2 = Module::factory()->create(['track_id' => $track->id]);

        Challenge::factory()->count(5)->create([
            'module_id' => $module1->id,
            'lesson_id' => null,
            'title' => 'Docker Challenge'
        ]);
        Challenge::factory()->count(3)->create([
            'module_id' => $module2->id,
            'lesson_id' => null,
            'title' => 'Docker Challenge'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/challenges?module_id={$module1->id}&search=Docker&pagination=false");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function test_search_works_with_lesson_id_filter(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $lesson1 = Lesson::factory()->create(['module_id' => $module->id]);
        $lesson2 = Lesson::factory()->create(['module_id' => $module->id]);

        Challenge::factory()->count(5)->create([
            'module_id' => $module->id,
            'lesson_id' => $lesson1->id,
            'title' => 'Docker Challenge'
        ]);
        Challenge::factory()->count(3)->create([
            'module_id' => $module->id,
            'lesson_id' => $lesson2->id,
            'title' => 'Docker Challenge'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/challenges?lesson_id={$lesson1->id}&search=Docker&pagination=false");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function test_search_works_with_track_id_filter(): void
    {
        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();

        $track1Module = Module::factory()->create(['track_id' => $track1->id]);
        $track2Module = Module::factory()->create(['track_id' => $track2->id]);

        Challenge::factory()->count(5)->create([
            'module_id' => $track1Module->id,
            'title' => 'Docker Challenge'
        ]);
        Challenge::factory()->count(3)->create([
            'module_id' => $track2Module->id,
            'title' => 'Docker Challenge'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/challenges?track_id={$track1->id}&search=Docker&pagination=false");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function test_pagination_works_with_module_id_filter(): void
    {
        $track = Track::factory()->create();
        $module1 = Module::factory()->create(['track_id' => $track->id]);
        $module2 = Module::factory()->create(['track_id' => $track->id]);

        Challenge::factory()->count(20)->create(['module_id' => $module1->id, 'lesson_id' => null]);
        Challenge::factory()->count(10)->create(['module_id' => $module2->id, 'lesson_id' => null]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/challenges?module_id={$module1->id}&per_page=10");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 10);

        $this->assertCount(10, $response->json('data'));
    }

    /** @test */
    public function test_existing_module_id_filter_still_works(): void
    {
        $track = Track::factory()->create();
        $module1 = Module::factory()->create(['track_id' => $track->id]);
        $module2 = Module::factory()->create(['track_id' => $track->id]);

        Challenge::factory()->count(5)->create(['module_id' => $module1->id, 'lesson_id' => null]);
        Challenge::factory()->count(3)->create(['module_id' => $module2->id, 'lesson_id' => null]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/challenges?module_id={$module1->id}&pagination=false");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function test_existing_lesson_id_filter_still_works(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $lesson1 = Lesson::factory()->create(['module_id' => $module->id]);
        $lesson2 = Lesson::factory()->create(['module_id' => $module->id]);

        Challenge::factory()->count(5)->create(['module_id' => $module->id, 'lesson_id' => $lesson1->id]);
        Challenge::factory()->count(3)->create(['module_id' => $module->id, 'lesson_id' => $lesson2->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/challenges?lesson_id={$lesson1->id}&pagination=false");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function test_existing_track_id_filter_still_works(): void
    {
        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();

        $track1Module = Module::factory()->create(['track_id' => $track1->id]);
        $track2Module = Module::factory()->create(['track_id' => $track2->id]);

        Challenge::factory()->count(5)->create(['module_id' => $track1Module->id]);
        Challenge::factory()->count(3)->create(['module_id' => $track2Module->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/challenges?track_id={$track1->id}&pagination=false");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function test_validates_module_id_exists(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/challenges?module_id=99999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['module_id']);
    }

    /** @test */
    public function test_validates_lesson_id_exists(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/challenges?lesson_id=99999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_id']);
    }

    /** @test */
    public function test_validates_track_id_exists(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/challenges?track_id=99999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['track_id']);
    }
}
