<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user for authentication
        $this->user = User::factory()->create();
    }

    public function test_index_returns_all_lessons_without_filter(): void
    {
        // Arrange: Create tracks, modules, and lessons
        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();

        $module1 = Module::factory()->create(['track_id' => $track1->id]);
        $module2 = Module::factory()->create(['track_id' => $track2->id]);

        Lesson::factory()->count(3)->create(['module_id' => $module1->id]);
        Lesson::factory()->count(2)->create(['module_id' => $module2->id]);

        // Act: Request all lessons without filter
        $response = $this->actingAs($this->user)->getJson('/api/lessons');

        // Assert: Should return all 5 lessons
        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    public function test_index_filters_lessons_by_module_id(): void
    {
        // Arrange: Create modules with lessons
        $track = Track::factory()->create();
        $module1 = Module::factory()->create(['track_id' => $track->id]);
        $module2 = Module::factory()->create(['track_id' => $track->id]);

        $module1Lessons = Lesson::factory()->count(3)->create(['module_id' => $module1->id]);
        Lesson::factory()->count(2)->create(['module_id' => $module2->id]);

        // Act: Request lessons filtered by module1
        $response = $this->actingAs($this->user)->getJson("/api/lessons?module_id={$module1->id}");

        // Assert: Should return only module1's lessons
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');

        // Verify all returned lessons belong to module1
        $returnedLessonIds = collect($response->json('data'))->pluck('id')->toArray();
        $expectedLessonIds = $module1Lessons->pluck('id')->toArray();

        $this->assertEquals(sort($expectedLessonIds), sort($returnedLessonIds));
    }

    public function test_index_filters_lessons_by_track_id(): void
    {
        // Arrange: Create tracks with modules and lessons
        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();

        $track1Module1 = Module::factory()->create(['track_id' => $track1->id]);
        $track1Module2 = Module::factory()->create(['track_id' => $track1->id]);
        $track2Module = Module::factory()->create(['track_id' => $track2->id]);

        $track1Lessons = collect();
        $track1Lessons = $track1Lessons->merge(Lesson::factory()->count(2)->create(['module_id' => $track1Module1->id]));
        $track1Lessons = $track1Lessons->merge(Lesson::factory()->count(2)->create(['module_id' => $track1Module2->id]));

        Lesson::factory()->count(3)->create(['module_id' => $track2Module->id]);

        // Act: Request lessons filtered by track1
        $response = $this->actingAs($this->user)->getJson("/api/lessons?track_id={$track1->id}");

        // Assert: Should return only track1's lessons
        $response->assertStatus(200)
            ->assertJsonCount(4, 'data');

        // Verify all returned lessons belong to track1's modules
        $returnedLessonIds = collect($response->json('data'))->pluck('id')->toArray();
        $expectedLessonIds = $track1Lessons->pluck('id')->toArray();

        $this->assertEquals(sort($expectedLessonIds), sort($returnedLessonIds));
    }

    public function test_index_filters_lessons_by_both_module_id_and_track_id(): void
    {
        // Arrange: Create structure with multiple tracks and modules
        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();

        $track1Module1 = Module::factory()->create(['track_id' => $track1->id]);
        $track1Module2 = Module::factory()->create(['track_id' => $track1->id]);
        $track2Module = Module::factory()->create(['track_id' => $track2->id]);

        $targetLessons = Lesson::factory()->count(2)->create(['module_id' => $track1Module1->id]);
        Lesson::factory()->count(2)->create(['module_id' => $track1Module2->id]);
        Lesson::factory()->count(2)->create(['module_id' => $track2Module->id]);

        // Act: Request lessons filtered by both module and track
        $response = $this->actingAs($this->user)
            ->getJson("/api/lessons?module_id={$track1Module1->id}&track_id={$track1->id}");

        // Assert: Should return only lessons matching BOTH filters
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Verify returned lessons match both filters
        $returnedLessonIds = collect($response->json('data'))->pluck('id')->toArray();
        $expectedLessonIds = $targetLessons->pluck('id')->toArray();

        $this->assertEquals(sort($expectedLessonIds), sort($returnedLessonIds));
    }

    public function test_filtering_does_not_return_lessons_from_other_modules(): void
    {
        // Arrange
        $track = Track::factory()->create();
        $module1 = Module::factory()->create(['track_id' => $track->id]);
        $module2 = Module::factory()->create(['track_id' => $track->id]);

        Lesson::factory()->create(['module_id' => $module1->id, 'title' => 'Module 1 Lesson']);
        $module2Lesson = Lesson::factory()->create(['module_id' => $module2->id, 'title' => 'Module 2 Lesson']);

        // Act: Request lessons from module1
        $response = $this->actingAs($this->user)->getJson("/api/lessons?module_id={$module1->id}");

        // Assert: Should not contain module2's lesson
        $response->assertStatus(200);

        $returnedLessonIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($module2Lesson->id, $returnedLessonIds);
    }

    public function test_filtering_does_not_return_lessons_from_other_tracks(): void
    {
        // Arrange
        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();

        $track1Module = Module::factory()->create(['track_id' => $track1->id]);
        $track2Module = Module::factory()->create(['track_id' => $track2->id]);

        Lesson::factory()->create(['module_id' => $track1Module->id]);
        $track2Lesson = Lesson::factory()->create(['module_id' => $track2Module->id]);

        // Act: Request lessons from track1
        $response = $this->actingAs($this->user)->getJson("/api/lessons?track_id={$track1->id}");

        // Assert: Should not contain track2's lesson
        $response->assertStatus(200);

        $returnedLessonIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($track2Lesson->id, $returnedLessonIds);
    }

    public function test_returns_404_when_no_lessons_found(): void
    {
        // Arrange: Create a module without lessons
        $track = Track::factory()->create();
        $emptyModule = Module::factory()->create(['track_id' => $track->id]);

        // Act: Request lessons for empty module
        $response = $this->actingAs($this->user)->getJson("/api/lessons?module_id={$emptyModule->id}");

        // Assert: Should return 404
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'No lessons found'
            ]);
    }

    public function test_validates_module_id_exists(): void
    {
        // Act: Request with non-existent module_id
        $response = $this->actingAs($this->user)->getJson('/api/lessons?module_id=99999');

        // Assert: Should return validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['module_id']);
    }

    public function test_validates_track_id_exists(): void
    {
        // Act: Request with non-existent track_id
        $response = $this->actingAs($this->user)->getJson('/api/lessons?track_id=99999');

        // Assert: Should return validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['track_id']);
    }

    public function test_validates_ids_are_integers(): void
    {
        // Act: Request with invalid id formats
        $response = $this->actingAs($this->user)
            ->getJson('/api/lessons?module_id=invalid&track_id=invalid');

        // Assert: Should return validation errors
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['module_id', 'track_id']);
    }
}
