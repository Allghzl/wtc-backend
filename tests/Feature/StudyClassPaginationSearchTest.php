<?php

namespace Tests\Feature;

use App\Models\StudyClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudyClassPaginationSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function test_index_returns_paginated_study_classes_by_default(): void
    {
        StudyClass::factory()->count(20)->create();

        $response = $this->actingAs($this->user)->getJson('/api/study-classes');

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
        StudyClass::factory()->count(20)->create();

        $response = $this->actingAs($this->user)->getJson('/api/study-classes?pagination=false');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonMissing(['meta'])
            ->assertJsonCount(20, 'data');
    }

    /** @test */
    public function test_pagination_true_explicitly_enables_pagination(): void
    {
        StudyClass::factory()->count(20)->create();

        $response = $this->actingAs($this->user)->getJson('/api/study-classes?pagination=true');

        $response->assertStatus(200)
            ->assertJsonStructure(['meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    }

    /** @test */
    public function test_per_page_parameter_works(): void
    {
        StudyClass::factory()->count(25)->create();

        $response = $this->actingAs($this->user)->getJson('/api/study-classes?per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25);

        $this->assertCount(10, $response->json('data'));
    }

    /** @test */
    public function test_page_parameter_works(): void
    {
        StudyClass::factory()->count(20)->create();

        $response = $this->actingAs($this->user)->getJson('/api/study-classes?page=2&per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 10);

        $this->assertCount(10, $response->json('data'));
    }

    /** @test */
    public function test_search_finds_matching_study_classes_by_name(): void
    {
        StudyClass::factory()->create(['name' => 'Class A - Morning']);
        StudyClass::factory()->create(['name' => 'Class B - Afternoon']);
        StudyClass::factory()->create(['name' => 'Class A - Evening']);

        $response = $this->actingAs($this->user)->getJson('/api/study-classes?search=Class A&pagination=false');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Class A - Morning', $names);
        $this->assertContains('Class A - Evening', $names);
        $this->assertNotContains('Class B - Afternoon', $names);
    }

    /** @test */
    public function test_search_is_case_insensitive(): void
    {
        StudyClass::factory()->create(['name' => 'Advanced Programming']);
        StudyClass::factory()->create(['name' => 'Basic Mathematics']);

        $response = $this->actingAs($this->user)->getJson('/api/study-classes?search=advanced&pagination=false');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function test_empty_search_returns_all_study_classes(): void
    {
        StudyClass::factory()->count(5)->create();

        $response = $this->actingAs($this->user)->getJson('/api/study-classes?search=&pagination=false');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function test_search_works_with_pagination(): void
    {
        StudyClass::factory()->count(10)->create(['name' => 'Class A']);
        StudyClass::factory()->count(10)->create(['name' => 'Class B']);

        $response = $this->actingAs($this->user)->getJson('/api/study-classes?search=Class A&per_page=5');

        $response->assertStatus(200)
            ->assertJsonStructure(['meta'])
            ->assertJsonPath('meta.total', 10)
            ->assertJsonPath('meta.per_page', 5);

        $this->assertCount(5, $response->json('data'));
    }

    /** @test */
    public function test_validates_per_page_is_integer(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/study-classes?per_page=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    /** @test */
    public function test_validates_per_page_minimum(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/study-classes?per_page=0');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    /** @test */
    public function test_validates_per_page_maximum(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/study-classes?per_page=101');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    /** @test */
    public function test_validates_page_is_integer(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/study-classes?page=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }

    /** @test */
    public function test_validates_page_minimum(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/study-classes?page=0');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }
}
