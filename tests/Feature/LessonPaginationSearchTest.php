<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonPaginationSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function test_index_returns_paginated_lessons_by_default(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        Lesson::factory()->count(20)->create(['module_id' => $module->id]);

        $response = $this->actingAs($this->user)->getJson('/api/lessons');

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
        Lesson::factory()->count(20)->create(['module_id' => $module->id]);

        $response = $this->actingAs($this->user)->getJson('/api/lessons?pagination=false');

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
        Lesson::factory()->count(25)->create(['module_id' => $module->id]);

        $response = $this->actingAs($this->user)->getJson('/api/lessons?per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25);

        $this->assertCount(10, $response->json('data'));
    }

    /** @test */
    public function test_search_finds_matching_lessons_by_title(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        Lesson::factory()->create(['module_id' => $module->id, 'title' => 'Docker Compose Basics']);
        Lesson::factory()->create(['module_id' => $module->id, 'title' => 'Kubernetes Overview']);
        Lesson::factory()->create(['module_id' => $module->id, 'title' => 'Docker Networking']);

        $response = $this->actingAs($this->user)->getJson('/api/lessons?search=Docker&pagination=false');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('Docker Compose Basics', $titles);
        $this->assertContains('Docker Networking', $titles);
        $this->assertNotContains('Kubernetes Overview', $titles);
    }

    /** @test */
    public function test_search_finds_matching_lessons_by_content(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        Lesson::factory()->create(['module_id' => $module->id, 'content' => 'Learn about containerization']);
        Lesson::factory()->create(['module_id' => $module->id, 'content' => 'Learn about orchestration']);
        Lesson::factory()->create(['module_id' => $module->id, 'content' => 'Advanced containerization techniques']);

        $response = $this->actingAs($this->user)->getJson('/api/lessons?search=containerization&pagination=false');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function test_search_is_case_insensitive(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        Lesson::factory()->create(['module_id' => $module->id, 'title' => 'Docker Fundamentals']);
        Lesson::factory()->create(['module_id' => $module->id, 'title' => 'Kubernetes Basics']);

        $response = $this->actingAs($this->user)->getJson('/api/lessons?search=docker&pagination=false');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function test_search_works_with_pagination(): void
    {
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        Lesson::factory()->count(10)->create(['module_id' => $module->id, 'title' => 'Docker Lesson']);
        Lesson::factory()->count(10)->create(['module_id' => $module->id, 'title' => 'Python Lesson']);

        $response = $this->actingAs($this->user)->getJson('/api/lessons?search=Docker&per_page=5');

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

        Lesson::factory()->count(5)->create(['module_id' => $module1->id, 'title' => 'Docker Lesson']);
        Lesson::factory()->count(3)->create(['module_id' => $module2->id, 'title' => 'Docker Lesson']);
        Lesson::factory()->count(2)->create(['module_id' => $module1->id, 'title' => 'Python Lesson']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/lessons?module_id={$module1->id}&search=Docker&pagination=false");

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

        Lesson::factory()->count(5)->create(['module_id' => $track1Module->id, 'title' => 'Docker Lesson']);
        Lesson::factory()->count(3)->create(['module_id' => $track2Module->id, 'title' => 'Docker Lesson']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/lessons?track_id={$track1->id}&search=Docker&pagination=false");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function test_pagination_works_with_module_id_filter(): void
    {
        $track = Track::factory()->create();
        $module1 = Module::factory()->create(['track_id' => $track->id]);
        $module2 = Module::factory()->create(['track_id' => $track->id]);

        Lesson::factory()->count(20)->create(['module_id' => $module1->id]);
        Lesson::factory()->count(10)->create(['module_id' => $module2->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/lessons?module_id={$module1->id}&per_page=10");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 10);

        $this->assertCount(10, $response->json('data'));
    }

    /** @test */
    public function test_pagination_works_with_track_id_filter(): void
    {
        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();

        $track1Module1 = Module::factory()->create(['track_id' => $track1->id]);
        $track1Module2 = Module::factory()->create(['track_id' => $track1->id]);
        $track2Module = Module::factory()->create(['track_id' => $track2->id]);

        Lesson::factory()->count(10)->create(['module_id' => $track1Module1->id]);
        Lesson::factory()->count(10)->create(['module_id' => $track1Module2->id]);
        Lesson::factory()->count(5)->create(['module_id' => $track2Module->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/lessons?track_id={$track1->id}&per_page=15");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 15);

        $this->assertCount(15, $response->json('data'));
    }

    /** @test */
    public function test_search_pagination_and_module_filter_work_together(): void
    {
        $track = Track::factory()->create();
        $module1 = Module::factory()->create(['track_id' => $track->id]);
        $module2 = Module::factory()->create(['track_id' => $track->id]);

        Lesson::factory()->count(15)->create(['module_id' => $module1->id, 'title' => 'Docker Lesson']);
        Lesson::factory()->count(5)->create(['module_id' => $module2->id, 'title' => 'Docker Lesson']);
        Lesson::factory()->count(5)->create(['module_id' => $module1->id, 'title' => 'Python Lesson']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/lessons?module_id={$module1->id}&search=Docker&per_page=10&page=1");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.current_page', 1);

        $this->assertCount(10, $response->json('data'));
    }

    /** @test */
    public function test_search_pagination_and_track_filter_work_together(): void
    {
        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();

        $track1Module = Module::factory()->create(['track_id' => $track1->id]);
        $track2Module = Module::factory()->create(['track_id' => $track2->id]);

        Lesson::factory()->count(15)->create(['module_id' => $track1Module->id, 'title' => 'Docker Lesson']);
        Lesson::factory()->count(5)->create(['module_id' => $track2Module->id, 'title' => 'Docker Lesson']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/lessons?track_id={$track1->id}&search=Docker&per_page=10");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.per_page', 10);

        $this->assertCount(10, $response->json('data'));
    }

    /** @test */
    public function test_existing_module_id_filter_still_works(): void
    {
        $track = Track::factory()->create();
        $module1 = Module::factory()->create(['track_id' => $track->id]);
        $module2 = Module::factory()->create(['track_id' => $track->id]);

        Lesson::factory()->count(5)->create(['module_id' => $module1->id]);
        Lesson::factory()->count(3)->create(['module_id' => $module2->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/lessons?module_id={$module1->id}&pagination=false");

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

        Lesson::factory()->count(5)->create(['module_id' => $track1Module->id]);
        Lesson::factory()->count(3)->create(['module_id' => $track2Module->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/lessons?track_id={$track1->id}&pagination=false");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function test_validates_module_id_exists(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/lessons?module_id=99999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['module_id']);
    }

    /** @test */
    public function test_validates_track_id_exists(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/lessons?track_id=99999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['track_id']);
    }
}
