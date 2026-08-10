<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user for authentication
        $this->user = User::factory()->create();
    }

    public function test_index_returns_all_modules_without_filter(): void
    {
        // Arrange: Create multiple tracks with modules
        $track1 = Track::factory()->create(['title' => 'Track 1']);
        $track2 = Track::factory()->create(['title' => 'Track 2']);

        Module::factory()->count(3)->create(['track_id' => $track1->id]);
        Module::factory()->count(2)->create(['track_id' => $track2->id]);

        // Act: Request all modules without filter
        $response = $this->actingAs($this->user)->getJson('/api/modules');

        // Assert: Should return all 5 modules
        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    public function test_index_filters_modules_by_track_id(): void
    {
        // Arrange: Create two tracks with modules
        $track1 = Track::factory()->create(['title' => 'Backend Track']);
        $track2 = Track::factory()->create(['title' => 'Frontend Track']);

        $track1Modules = Module::factory()->count(3)->create(['track_id' => $track1->id]);
        Module::factory()->count(2)->create(['track_id' => $track2->id]);

        // Act: Request modules filtered by track1
        $response = $this->actingAs($this->user)->getJson("/api/modules?track_id={$track1->id}");

        // Assert: Should return only track1's modules
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');

        // Verify all returned modules belong to track1
        $returnedModuleIds = collect($response->json('data'))->pluck('id')->toArray();
        $expectedModuleIds = $track1Modules->pluck('id')->toArray();

        $this->assertEquals(sort($expectedModuleIds), sort($returnedModuleIds));
    }

    public function test_filtering_does_not_return_modules_from_other_tracks(): void
    {
        // Arrange: Create two tracks
        $track1 = Track::factory()->create(['title' => 'Track 1']);
        $track2 = Track::factory()->create(['title' => 'Track 2']);

        Module::factory()->create(['track_id' => $track1->id, 'title' => 'Module Track 1']);
        $track2Module = Module::factory()->create(['track_id' => $track2->id, 'title' => 'Module Track 2']);

        // Act: Request modules from track1
        $response = $this->actingAs($this->user)->getJson("/api/modules?track_id={$track1->id}");

        // Assert: Should not contain track2's module
        $response->assertStatus(200);

        $returnedModuleIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($track2Module->id, $returnedModuleIds);
    }

    public function test_returns_404_when_no_modules_found_for_track(): void
    {
        // Arrange: Create a track without modules
        $emptyTrack = Track::factory()->create();

        // Act: Request modules for empty track
        $response = $this->actingAs($this->user)->getJson("/api/modules?track_id={$emptyTrack->id}");

        // Assert: Should return 404
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'No modules found'
            ]);
    }

    public function test_validates_track_id_exists(): void
    {
        // Act: Request with non-existent track_id
        $response = $this->actingAs($this->user)->getJson('/api/modules?track_id=99999');

        // Assert: Should return validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['track_id']);
    }

    public function test_validates_track_id_is_integer(): void
    {
        // Act: Request with invalid track_id format
        $response = $this->actingAs($this->user)->getJson('/api/modules?track_id=invalid');

        // Assert: Should return validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['track_id']);
    }
}
