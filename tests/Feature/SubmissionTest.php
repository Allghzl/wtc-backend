<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Profile;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Profile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->profile = Profile::factory()->create(['user_id' => $this->user->id]);
    }

    
    public function test_it_creates_first_submission(): void
    {
        $challenge = Challenge::factory()->create(['allowed_attempts' => 1]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/challenges/{$challenge->id}/submit", [
                'submitted_content' => ['answer' => 'hello'],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('submissions', [
            'profile_id' => $this->profile->id,
            'challenge_id' => $challenge->id,
            'attempt_number' => 1,
        ]);
    }

    
    public function test_it_enforces_allowed_attempts(): void
    {
        $challenge = Challenge::factory()->create(['allowed_attempts' => 1]);

        Submission::create([
            'challenge_id' => $challenge->id,
            'profile_id' => $this->profile->id,
            'attempt_number' => 1,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/challenges/{$challenge->id}/submit", [
                'submitted_content' => ['answer' => 'again'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['submission']);
    }

    
    public function test_it_allows_unlimited_attempts_when_null(): void
    {
        $challenge = Challenge::factory()->create(['allowed_attempts' => null]);

        foreach ([1, 2] as $attempt) {
            $response = $this->actingAs($this->user)
                ->postJson("/api/challenges/{$challenge->id}/submit", [
                    'submitted_content' => ['answer' => "answer {$attempt}"],
                ]);
            $response->assertStatus(201);
        }

        $this->assertSame(
            2,
            Submission::where('profile_id', $this->profile->id)
                ->where('challenge_id', $challenge->id)
                ->count()
        );
    }
}
