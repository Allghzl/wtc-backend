<?php

namespace Tests\Feature\Api;

use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Profile;
use App\Models\Role;
use App\Models\Track;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Profile $profile;
    protected Role $teacherRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create teacher role
        $this->teacherRole = Role::create(['name' => 'teacher']);

        // Create a user with profile
        $this->user = User::factory()->withProfile()->create();
        $this->profile = $this->user->profile;
        $this->profile->roles()->attach($this->teacherRole);
    }

    public function test_it_returns_audit_logs_for_specific_track(): void
    {
        $this->actingAs($this->user);

        $track = Track::factory()->create();

        $response = $this->getJson("/api/tracks/{$track->slug}/audit-log");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'audit_logs' => [
                        '*' => [
                            'id',
                            'action',
                            'auditable_type',
                            'auditable_id',
                            'profile',
                            'old_values',
                            'new_values',
                            'created_at',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                    ],
                ],
            ]);
    }

    public function test_it_returns_audit_logs_for_specific_module(): void
    {
        $this->actingAs($this->user);

        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);

        $response = $this->getJson("/api/modules/{$module->id}/audit-log");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'audit_logs',
                    'pagination',
                ],
            ]);
    }

    public function test_it_returns_audit_logs_for_specific_lesson(): void
    {
        $this->actingAs($this->user);

        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        $response = $this->getJson("/api/lessons/{$lesson->id}/audit-log");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'audit_logs',
                    'pagination',
                ],
            ]);
    }

    public function test_it_returns_audit_logs_for_specific_challenge(): void
    {
        $this->actingAs($this->user);

        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);
        $challenge = Challenge::factory()->create(['module_id' => $module->id]);

        $response = $this->getJson("/api/challenges/{$challenge->id}/audit-log");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'audit_logs',
                    'pagination',
                ],
            ]);
    }

    public function test_it_returns_all_audit_logs(): void
    {
        $this->actingAs($this->user);

        // Create some content to generate audit logs
        Track::factory()->create();
        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);

        $response = $this->getJson('/api/audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'audit_logs',
                    'pagination',
                ],
            ]);

        // Should have at least 3 audit logs (2 tracks + 1 module)
        $this->assertGreaterThanOrEqual(3, count($response->json('data.audit_logs')));
    }

    public function test_it_filters_audit_logs_by_auditable_type(): void
    {
        $this->actingAs($this->user);

        $track = Track::factory()->create();
        $module = Module::factory()->create(['track_id' => $track->id]);

        $response = $this->getJson('/api/audit-logs?auditable_type=' . urlencode(Track::class));

        $response->assertStatus(200);

        $auditLogs = $response->json('data.audit_logs');
        foreach ($auditLogs as $log) {
            $this->assertEquals(Track::class, $log['auditable_type']);
        }
    }

    public function test_it_filters_audit_logs_by_auditable_id(): void
    {
        $this->actingAs($this->user);

        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();

        $response = $this->getJson("/api/audit-logs?auditable_type=" . urlencode(Track::class) . "&auditable_id={$track1->id}");

        $response->assertStatus(200);

        $auditLogs = $response->json('data.audit_logs');
        foreach ($auditLogs as $log) {
            $this->assertEquals($track1->id, $log['auditable_id']);
        }
    }

    public function test_it_filters_audit_logs_by_profile_id(): void
    {
        $this->actingAs($this->user);

        // Create content as this user
        Track::factory()->create();

        // Create another user and content
        $otherUser = User::factory()->withProfile()->create();
        $otherProfile = $otherUser->profile;
        $otherProfile->roles()->attach($this->teacherRole);

        $this->actingAs($otherUser);
        Track::factory()->create();

        // Filter by first user's profile
        $this->actingAs($this->user);
        $response = $this->getJson("/api/audit-logs?profile_id={$this->profile->id}");

        $response->assertStatus(200);

        $auditLogs = $response->json('data.audit_logs');
        foreach ($auditLogs as $log) {
            if (isset($log['profile']['id'])) {
                $this->assertEquals($this->profile->id, $log['profile']['id']);
            }
        }
    }

    public function test_it_filters_audit_logs_by_action(): void
    {
        $this->actingAs($this->user);

        $track = Track::factory()->create(['title' => 'Original']);

        // Update to create an 'updated' audit log
        $track->update(['title' => 'Updated']);

        $response = $this->getJson('/api/audit-logs?action=updated');

        $response->assertStatus(200);

        $auditLogs = $response->json('data.audit_logs');
        foreach ($auditLogs as $log) {
            $this->assertEquals('updated', $log['action']);
        }
    }

    public function test_it_filters_audit_logs_by_date_range(): void
    {
        $this->actingAs($this->user);

        $track = Track::factory()->create();

        // Manually set audit log date to yesterday
        $audit = Audit::where('auditable_type', Track::class)
            ->where('auditable_id', $track->id)
            ->first();
        $audit->created_at = Carbon::yesterday();
        $audit->save();

        // Create a new track today
        Track::factory()->create();

        // Filter for today only
        $response = $this->getJson('/api/audit-logs?date_from=' . Carbon::today()->toDateString());

        $response->assertStatus(200);

        $auditLogs = $response->json('data.audit_logs');
        $this->assertGreaterThan(0, count($auditLogs));

        // All logs should be from today or later
        foreach ($auditLogs as $log) {
            $logDate = Carbon::parse($log['created_at']);
            $this->assertTrue($logDate->gte(Carbon::today()));
        }
    }

    public function test_it_paginates_audit_logs(): void
    {
        $this->actingAs($this->user);

        // Create multiple tracks to generate audit logs
        for ($i = 0; $i < 20; $i++) {
            Track::factory()->create();
        }

        $response = $this->getJson('/api/audit-logs?per_page=5');

        $response->assertStatus(200);

        $pagination = $response->json('data.pagination');
        $this->assertEquals(5, $pagination['per_page']);
        $this->assertGreaterThanOrEqual(20, $pagination['total']);
        $this->assertGreaterThanOrEqual(4, $pagination['last_page']);
    }

    public function test_it_includes_profile_information_in_audit_logs(): void
    {
        $this->actingAs($this->user);

        $track = Track::factory()->create();

        $response = $this->getJson("/api/tracks/{$track->slug}/audit-log");

        $response->assertStatus(200);

        $auditLogs = $response->json('data.audit_logs');
        $this->assertGreaterThan(0, count($auditLogs));

        $firstLog = $auditLogs[0];
        $this->assertArrayHasKey('profile', $firstLog);

        if ($firstLog['profile']) {
            $this->assertArrayHasKey('id', $firstLog['profile']);
            $this->assertArrayHasKey('display_name', $firstLog['profile']);
        }
    }

    public function test_unauthorized_users_cannot_access_audit_logs(): void
    {
        $response = $this->getJson('/api/audit-logs');

        $response->assertStatus(401);
    }
}
