<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Track;
use App\Models\TrackEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Profile $profile;
    protected Track $track;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Manually create profile since UserFactory doesn't create it automatically
        $this->profile = Profile::create([
            'user_id' => $this->user->id,
            'display_name' => $this->user->name,
            'points' => 0,
        ]);

        $this->track = Track::factory()->create();
    }

    /** @test */
    public function test_unauthenticated_user_cannot_enroll(): void
    {
        $response = $this->postJson("/api/tracks/{$this->track->slug}/enroll");

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function test_authenticated_user_can_enroll_in_track(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/tracks/{$this->track->slug}/enroll");

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Berhasil mendaftar ke track.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'track_id',
                    'profile_id',
                    'status',
                    'enrolled_at',
                ],
            ]);

        $this->assertDatabaseHas('track_enrollments', [
            'track_id' => $this->track->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function test_duplicate_active_enrollment_is_rejected(): void
    {
        // First enrollment
        $this->actingAs($this->user)
            ->postJson("/api/tracks/{$this->track->slug}/enroll");

        // Attempt duplicate enrollment
        $response = $this->actingAs($this->user)
            ->postJson("/api/tracks/{$this->track->slug}/enroll");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'errors' => [
                    'enrollment' => ['Anda sudah terdaftar dalam track ini.'],
                ],
            ]);
    }

    /** @test */
    public function test_dropped_enrollment_can_be_reactivated(): void
    {
        // Create dropped enrollment
        TrackEnrollment::create([
            'track_id' => $this->track->id,
            'profile_id' => $this->profile->id,
            'status' => 'dropped',
            'enrolled_at' => now()->subDays(10),
            'dropped_at' => now()->subDays(5),
        ]);

        // Re-enroll
        $response = $this->actingAs($this->user)
            ->postJson("/api/tracks/{$this->track->slug}/enroll");

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('track_enrollments', [
            'track_id' => $this->track->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function test_completed_enrollment_cannot_be_reactivated(): void
    {
        // Create completed enrollment
        TrackEnrollment::create([
            'track_id' => $this->track->id,
            'profile_id' => $this->profile->id,
            'status' => 'completed',
            'enrolled_at' => now()->subDays(30),
            'completed_at' => now()->subDays(1),
        ]);

        // Attempt to re-enroll
        $response = $this->actingAs($this->user)
            ->postJson("/api/tracks/{$this->track->slug}/enroll");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'errors' => [
                    'enrollment' => ['Anda sudah menyelesaikan track ini.'],
                ],
            ]);
    }

    /** @test */
    public function test_user_can_unenroll_from_track(): void
    {
        // Create active enrollment
        TrackEnrollment::create([
            'track_id' => $this->track->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/tracks/{$this->track->slug}/enroll");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Berhasil keluar dari track.',
            ]);

        $this->assertDatabaseHas('track_enrollments', [
            'track_id' => $this->track->id,
            'profile_id' => $this->profile->id,
            'status' => 'dropped',
        ]);
    }

    /** @test */
    public function test_cannot_unenroll_if_not_enrolled(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/tracks/{$this->track->slug}/enroll");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'errors' => [
                    'enrollment' => ['Anda tidak terdaftar dalam track ini.'],
                ],
            ]);
    }

    /** @test */
    public function test_my_tracks_returns_enrolled_tracks(): void
    {
        $track1 = Track::factory()->create(['title' => 'Track 1']);
        $track2 = Track::factory()->create(['title' => 'Track 2']);

        // Enroll in track1 and track2
        TrackEnrollment::create([
            'track_id' => $track1->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        TrackEnrollment::create([
            'track_id' => $track2->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/my/tracks');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function test_my_tracks_returns_404_if_no_enrollments(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/my/tracks');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Anda belum mendaftar ke track manapun.',
            ]);
    }

    /** @test */
    public function test_get_enrollment_returns_enrollment_status(): void
    {
        TrackEnrollment::create([
            'track_id' => $this->track->id,
            'profile_id' => $this->profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/tracks/{$this->track->slug}/enrollment");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'track_id',
                    'profile_id',
                    'status',
                    'enrolled_at',
                ],
            ]);
    }

    /** @test */
    public function test_get_enrollment_returns_404_if_not_enrolled(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/tracks/{$this->track->slug}/enrollment");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Anda belum mendaftar ke track ini.',
            ]);
    }
}
