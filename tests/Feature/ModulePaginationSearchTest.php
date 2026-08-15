<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulePaginationSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function test_index_returns_paginated_modules_by_default(): void
    {
        $track = Track::factory()->create();
        Module::factory()->count(20)->create(['track_id' => $track->id]);

        $response = $this->actingAs($this->user)->getJson('/api/modules');

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
        Module::factory()->count(20)->create(['track_id' => $track->id]);

        $response = $this->actingAs($this->user)->getJson('/api/modules?pagination=false');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonMissing(['meta'])
            ->assertJsonCount(20, 'data');
    }

    /** @test */
    public function test_per_page_parameter_works(): void
    {
        $track = Track::factory()->create();
        Module::factory()->count(25)->create(['track_id' => $track->id]);

        $response = $this->actingAs($this->user)->getJson('/api/modules?per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25);

        $this->assertCount(10, $response->json('data'));
    }

    /** @test */
    public function test_search_finds_matching_modules_by_title(): void
    {
        $track = Track::factory()->create();
        Module::factory()->create(['track_id' => $track->id, 'title' => 'Docker Fundamentals']);
        Module::factory()->create(['track_id' => $track->id, 'title' => 'Kubernetes Basics']);
        Module::factory()->create(['track_id' => $track->id, 'title' => 'Docker Advanced']);

        $response = $this->actingAs($this->user)->getJson('/api/modules?search=Docker&pagination=false');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('Docker Fundamentals', $titles);
        $this->assertContains('Docker Advanced', $titles);
        $this->assertNotContains('Kubernetes Basics', $titles);
    }

    /** @test */
    public function test_search_finds_matching_modules_by_description(): void
    {
        $track = Track::factory()->create();
        Module::factory()->create(['track_id' => $track->id, 'description' => 'Learn about containerization']);
        Module::factory()->create(['track_id' => $track->id, 'description' => 'Learn about orchestration']);
        Module::factory()->create(['track_id' => $track->id, 'description' => 'Advanced containerization']);

        $response = $this->actingAs($this->user)->getJson('/api/modules?search=containerization&pagination=false');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function test_search_is_case_insensitive(): void
    {
        $track = Track::factory()->create();
        Module::factory()->create(['track_id' => $track->id, 'title' => 'Docker Fundamentals']);
        Module::factory()->create(['track_id' => $track->id, 'title' => 'Kubernetes Basics']);

        $response = $this->actingAs($this->user)->getJson('/api/modules?search=docker&pagination=false');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function test_search_works_with_pagination(): void
    {
        $track = Track::factory()->create();
        Module::factory()->count(10)->create(['track_id' => $track->id, 'title' => 'Docker Module']);
        Module::factory()->count(10)->create(['track_id' => $track->id, 'title' => 'Python Module']);

        $response = $this->actingAs($this->user)->getJson('/api/modules?search=Docker&per_page=5');

        $response->assertStatus(200)
            ->assertJsonStructure(['meta'])
            ->assertJsonPath('meta.total', 10)
            ->assertJsonPath('meta.per_page', 5);

        $this->assertCount(5, $response->json('data'));
    }

    /** @test */
    public function test_search_works_with_track_id_filter(): void
    {
        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();

        Module::factory()->count(5)->create(['track_id' => $track1->id, 'title' => 'Docker Module']);
        Module::factory()->count(3)->create(['track_id' => $track2->id, 'title' => 'Docker Module']);
        Module::factory()->count(2)->create(['track_id' => $track1->id, 'title' => 'Python Module']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/modules?track_id={$track1->id}&search=Docker&pagination=false");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function test_pagination_works_with_track_id_filter(): void
    {
        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();

        Module::factory()->count(20)->create(['track_id' => $track1->id]);
        Module::factory()->count(10)->create(['track_id' => $track2->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/modules?track_id={$track1->id}&per_page=10");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 10);

        $this->assertCount(10, $response->json('data'));
    }

    /** @test */
    public function test_search_pagination_and_track_filter_work_together(): void
    {
        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();

        Module::factory()->count(15)->create([
            'track_id' => $track1->id,
            'title' => 'Docker Module'
        ]);
        Module::factory()->count(5)->create([
            'track_id' => $track2->id,
            'title' => 'Docker Module'
        ]);
        Module::factory()->count(5)->create([
            'track_id' => $track1->id,
            'title' => 'Python Module'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/modules?track_id={$track1->id}&search=Docker&per_page=10&page=1");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.current_page', 1);

        $this->assertCount(10, $response->json('data'));
    }

    /** @test */
    public function test_existing_track_id_filter_still_works(): void
    {
        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();

        Module::factory()->count(5)->create(['track_id' => $track1->id]);
        Module::factory()->count(3)->create(['track_id' => $track2->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/modules?track_id={$track1->id}&pagination=false");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function test_validates_per_page_is_integer(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/modules?per_page=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    /** @test */
    public function test_validates_per_page_maximum(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/modules?per_page=101');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    /** @test */
    public function test_validates_track_id_exists(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/modules?track_id=99999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['track_id']);
    }
}
