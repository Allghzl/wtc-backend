<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChallengeFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user for authentication
        $this->user = User::factory()->create();
    }

    public function test_index_returns_all_challenges_without_filter(): void
    {
        // Arrange: Create tracks, modules, lessons, and challenges
        $track = Track::factory()->create();
        $module1 = Module::factory()->create(['track_id' => $track->id]);
        $module2 = Module::factory()->create(['track_id' => $track->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module1->id]);

        // Module-based challenges
        Challenge::factory()->count(2)->forModule($module1->id)->create();
        Challenge::factory()->count(2)->forModule($module2->id)->create();

        // Lesson-based challenges
        Challenge::factory()->count(2)->forLesson($lesson->id)->create();

        // Act: Request all challenges without filter
        $response = $this->actingAs($this->user)->getJson('/api/challenges');

        // Assert: Should return all 6 challenges
        $response->assertStatus(200)
            ->assertJsonCount(6, 'data');
    }

    public function test_index_filters_challenges_by_module_id(): void
    {
        // Arrange: Create modules with challenges
        $track = Track::factory()->create();
        $module1 = Module::factory()->create(['track_id' => $track->id]);
        $module2 = Module::factory()->create(['track_id' => $track->id]);

        // Challenges directly under module1 (not under any lesson)
        $module1Challenges = Challenge::factory()->count(3)
            ->forModule($module1->id)
            ->create();

        // Challenges under module2
        Challenge::factory()->count(2)->forModule($module2->id)->create();

        // Act: Request challenges filtered by module1
        $response = $this->actingAs($this->user)
            ->getJson("/api/challenges?module_id={$module1->id}");

        // Assert: Should return only module1's challenges (where lesson_id is null)
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');

        // Verify all returned challenges belong to module1 and have null lesson_id
        $returnedChallengeIds = collect($response->json('data'))->pluck('id')->toArray();
        $expectedChallengeIds = $module1Challenges->pluck('id')->toArray();

        $this->assertEquals(sort($expectedChallengeIds), sort($returnedChallengeIds));
    }

    public function test_index_filters_challenges_by_lesson_id(): void
    {
        // Arrange: Create lessons with challenges
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $lesson1 = Lesson::factory()->create(['module_id' => $module->id]);
        $lesson2 = Lesson::factory()->create(['module_id' => $module->id]);

        // Challenges under lesson1
        $lesson1Challenges = Challenge::factory()->count(3)
            ->forLesson($lesson1->id)
            ->create();

        // Challenges under lesson2
        Challenge::factory()->count(2)->forLesson($lesson2->id)->create();

        // Act: Request challenges filtered by lesson1
        $response = $this->actingAs($this->user)
            ->getJson("/api/challenges?lesson_id={$lesson1->id}");

        // Assert: Should return only lesson1's challenges
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');

        // Verify all returned challenges belong to lesson1
        $returnedChallengeIds = collect($response->json('data'))->pluck('id')->toArray();
        $expectedChallengeIds = $lesson1Challenges->pluck('id')->toArray();

        $this->assertEquals(sort($expectedChallengeIds), sort($returnedChallengeIds));
    }

    public function test_index_filters_challenges_by_track_id(): void
    {
        // Arrange: Create tracks with modules, lessons, and challenges
        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();

        $track1Module1 = Module::factory()->create(['track_id' => $track1->id]);
        $track1Module2 = Module::factory()->create(['track_id' => $track1->id]);
        $track2Module = Module::factory()->create(['track_id' => $track2->id]);

        $track1Lesson = Lesson::factory()->create(['module_id' => $track1Module1->id]);

        $track1Challenges = collect();

        // Track1: Module-based challenges
        $track1Challenges = $track1Challenges->merge(
            Challenge::factory()->count(2)->forModule($track1Module1->id)->create()
        );
        $track1Challenges = $track1Challenges->merge(
            Challenge::factory()->count(2)->forModule($track1Module2->id)->create()
        );

        // Track1: Lesson-based challenges
        $track1Challenges = $track1Challenges->merge(
            Challenge::factory()->count(2)->forLesson($track1Lesson->id)->create()
        );

        // Track2: Challenges (should not be returned)
        Challenge::factory()->count(3)->forModule($track2Module->id)->create();

        // Act: Request challenges filtered by track1
        $response = $this->actingAs($this->user)
            ->getJson("/api/challenges?track_id={$track1->id}");

        // Assert: Should return only track1's challenges
        $response->assertStatus(200)
            ->assertJsonCount(6, 'data');

        // Verify all returned challenges belong to track1
        $returnedChallengeIds = collect($response->json('data'))->pluck('id')->toArray();
        $expectedChallengeIds = $track1Challenges->pluck('id')->toArray();

        $this->assertEquals(sort($expectedChallengeIds), sort($returnedChallengeIds));
    }

    public function test_module_filter_excludes_lesson_based_challenges(): void
    {
        // Arrange: Create module with both module-level and lesson-level challenges
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        // Challenge directly under module (should be returned)
        $moduleChallenges = Challenge::factory()->count(2)
            ->forModule($module->id)
            ->create();

        // Challenges under a lesson within the same module (should NOT be returned)
        $lessonChallenges = Challenge::factory()->count(2)
            ->forLesson($lesson->id)
            ->create();

        // Act: Request challenges filtered by module
        $response = $this->actingAs($this->user)
            ->getJson("/api/challenges?module_id={$module->id}");

        // Assert: Should return only module-level challenges, not lesson-based ones
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $returnedChallengeIds = collect($response->json('data'))->pluck('id')->toArray();

        // Should contain module challenges
        foreach ($moduleChallenges as $challenge) {
            $this->assertContains($challenge->id, $returnedChallengeIds);
        }

        // Should NOT contain lesson challenges
        foreach ($lessonChallenges as $challenge) {
            $this->assertNotContains($challenge->id, $returnedChallengeIds);
        }
    }

    public function test_filtering_does_not_return_challenges_from_other_modules(): void
    {
        // Arrange
        $track = Track::factory()->create();
        $module1 = Module::factory()->create(['track_id' => $track->id]);
        $module2 = Module::factory()->create(['track_id' => $track->id]);

        Challenge::factory()->forModule($module1->id)->create(['title' => 'Module 1 Challenge']);
        $module2Challenge = Challenge::factory()->forModule($module2->id)
            ->create(['title' => 'Module 2 Challenge']);

        // Act: Request challenges from module1
        $response = $this->actingAs($this->user)
            ->getJson("/api/challenges?module_id={$module1->id}");

        // Assert: Should not contain module2's challenge
        $response->assertStatus(200);

        $returnedChallengeIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($module2Challenge->id, $returnedChallengeIds);
    }

    public function test_filtering_does_not_return_challenges_from_other_lessons(): void
    {
        // Arrange
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $lesson1 = Lesson::factory()->create(['module_id' => $module->id]);
        $lesson2 = Lesson::factory()->create(['module_id' => $module->id]);

        Challenge::factory()->forLesson($lesson1->id)->create(['title' => 'Lesson 1 Challenge']);
        $lesson2Challenge = Challenge::factory()->forLesson($lesson2->id)
            ->create(['title' => 'Lesson 2 Challenge']);

        // Act: Request challenges from lesson1
        $response = $this->actingAs($this->user)
            ->getJson("/api/challenges?lesson_id={$lesson1->id}");

        // Assert: Should not contain lesson2's challenge
        $response->assertStatus(200);

        $returnedChallengeIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($lesson2Challenge->id, $returnedChallengeIds);
    }

    public function test_filtering_does_not_return_challenges_from_other_tracks(): void
    {
        // Arrange
        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();

        $track1Module = Module::factory()->create(['track_id' => $track1->id]);
        $track2Module = Module::factory()->create(['track_id' => $track2->id]);

        Challenge::factory()->forModule($track1Module->id)->create();
        $track2Challenge = Challenge::factory()->forModule($track2Module->id)->create();

        // Act: Request challenges from track1
        $response = $this->actingAs($this->user)
            ->getJson("/api/challenges?track_id={$track1->id}");

        // Assert: Should not contain track2's challenge
        $response->assertStatus(200);

        $returnedChallengeIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($track2Challenge->id, $returnedChallengeIds);
    }

    public function test_returns_404_when_no_challenges_found(): void
    {
        // Arrange: Create a module without challenges
        $track = Track::factory()->create();
        $emptyModule = Module::factory()->create(['track_id' => $track->id]);

        // Act: Request challenges for empty module
        $response = $this->actingAs($this->user)
            ->getJson("/api/challenges?module_id={$emptyModule->id}");

        // Assert: Should return 404
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'No challenges found'
            ]);
    }

    public function test_validates_module_id_exists(): void
    {
        // Act: Request with non-existent module_id
        $response = $this->actingAs($this->user)
            ->getJson('/api/challenges?module_id=99999');

        // Assert: Should return validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['module_id']);
    }

    public function test_validates_lesson_id_exists(): void
    {
        // Act: Request with non-existent lesson_id
        $response = $this->actingAs($this->user)
            ->getJson('/api/challenges?lesson_id=99999');

        // Assert: Should return validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_id']);
    }

    public function test_validates_track_id_exists(): void
    {
        // Act: Request with non-existent track_id
        $response = $this->actingAs($this->user)
            ->getJson('/api/challenges?track_id=99999');

        // Assert: Should return validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['track_id']);
    }

    public function test_validates_ids_are_integers(): void
    {
        // Act: Request with invalid id formats
        $response = $this->actingAs($this->user)
            ->getJson('/api/challenges?module_id=invalid&lesson_id=invalid&track_id=invalid');

        // Assert: Should return validation errors
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['module_id', 'lesson_id', 'track_id']);
    }
}
