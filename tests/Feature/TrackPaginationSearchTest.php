<?php

namespace Tests\Feature;

use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackPaginationSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function test_index_returns_paginated_tracks_by_default(): void
    {
        Track::factory()->count(20)->create();

        $response = $this->actingAs($this->user)->getJson('/api/tracks');

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
        Track::factory()->count(20)->create();

        $response = $this->actingAs($this->user)->getJson('/api/tracks?pagination=false');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonMissing(['meta'])
            ->assertJsonCount(20, 'data');
    }

    /** @test */
    public function test_pagination_true_explicitly_enables_pagination(): void
    {
        Track::factory()->count(20)->create();

        $response = $this->actingAs($this->user)->getJson('/api/tracks?pagination=true');

        $response->assertStatus(200)
            ->assertJsonStructure(['meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    }

    /** @test */
    public function test_per_page_parameter_works(): void
    {
        Track::factory()->count(25)->create();

        $response = $this->actingAs($this->user)->getJson('/api/tracks?per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25);

        $this->assertCount(10, $response->json('data'));
    }

    /** @test */
    public function test_page_parameter_works(): void
    {
        Track::factory()->count(20)->create();

        $response = $this->actingAs($this->user)->getJson('/api/tracks?page=2&per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 10);

        $this->assertCount(10, $response->json('data'));
    }

    /** @test */
    public function test_search_finds_matching_tracks_by_title(): void
    {
        Track::factory()->create(['title' => 'Docker Fundamentals']);
        Track::factory()->create(['title' => 'Kubernetes Basics']);
        Track::factory()->create(['title' => 'Docker Advanced']);

        $response = $this->actingAs($this->user)->getJson('/api/tracks?search=Docker&pagination=false');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('Docker Fundamentals', $titles);
        $this->assertContains('Docker Advanced', $titles);
        $this->assertNotContains('Kubernetes Basics', $titles);
    }

    /** @test */
    public function test_search_finds_matching_tracks_by_description(): void
    {
        Track::factory()->create(['title' => 'Track A', 'description' => 'Learn about containerization']);
        Track::factory()->create(['title' => 'Track B', 'description' => 'Learn about orchestration']);
        Track::factory()->create(['title' => 'Track C', 'description' => 'Advanced containerization techniques']);

        $response = $this->actingAs($this->user)->getJson('/api/tracks?search=containerization&pagination=false');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function test_search_is_case_insensitive(): void
    {
        Track::factory()->create(['title' => 'Docker Fundamentals']);
        Track::factory()->create(['title' => 'Kubernetes Basics']);

        $response = $this->actingAs($this->user)->getJson('/api/tracks?search=docker&pagination=false');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function test_search_with_no_results_returns_404(): void
    {
        Track::factory()->count(5)->create();

        $response = $this->actingAs($this->user)->getJson('/api/tracks?search=NonExistentTrack');

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function test_empty_search_returns_all_tracks(): void
    {
        Track::factory()->count(5)->create();

        $response = $this->actingAs($this->user)->getJson('/api/tracks?search=&pagination=false');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function test_search_works_with_pagination(): void
    {
        Track::factory()->count(10)->create(['title' => 'Docker Track']);
        Track::factory()->count(10)->create(['title' => 'Python Track']);

        $response = $this->actingAs($this->user)->getJson('/api/tracks?search=Docker&per_page=5');

        $response->assertStatus(200)
            ->assertJsonStructure(['meta'])
            ->assertJsonPath('meta.total', 10)
            ->assertJsonPath('meta.per_page', 5);

        $this->assertCount(5, $response->json('data'));
    }

    /** @test */
    public function test_validates_per_page_is_integer(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/tracks?per_page=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    /** @test */
    public function test_validates_per_page_minimum(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/tracks?per_page=0');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    /** @test */
    public function test_validates_per_page_maximum(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/tracks?per_page=101');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    /** @test */
    public function test_validates_page_is_integer(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/tracks?page=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }

    /** @test */
    public function test_validates_page_minimum(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/tracks?page=0');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }
}
