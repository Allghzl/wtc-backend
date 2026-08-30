<?php

namespace Tests\Feature\Audit;

use App\Models\Role;
use App\Models\Track;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

class AuditLogCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_audit_logs_older_than_90_days(): void
    {
        $teacherRole = Role::create(['name' => 'teacher']);
        $user = User::factory()->withProfile()->create();
        $user->profile->roles()->attach($teacherRole);

        $this->actingAs($user);

        // Create a track which generates an audit log
        $track = Track::factory()->create();

        // Get the audit log and manually set its created_at to 91 days ago
        $oldAudit = Audit::where('auditable_type', Track::class)
            ->where('auditable_id', $track->id)
            ->first();

        $oldAudit->created_at = Carbon::now()->subDays(91);
        $oldAudit->save();

        // Create another track with a recent audit log
        $recentTrack = Track::factory()->create();
        $recentAudit = Audit::where('auditable_type', Track::class)
            ->where('auditable_id', $recentTrack->id)
            ->first();

        // Verify both audit logs exist
        $this->assertDatabaseHas('audits', ['id' => $oldAudit->id]);
        $this->assertDatabaseHas('audits', ['id' => $recentAudit->id]);

        // Run the cleanup command
        $this->artisan('audit:cleanup')
            ->expectsOutput('Deleting audit logs older than 90 days (before ' . Carbon::now()->subDays(90)->toDateString() . ')...')
            ->assertExitCode(0);

        // Verify old audit log was deleted
        $this->assertDatabaseMissing('audits', ['id' => $oldAudit->id]);

        // Verify recent audit log still exists
        $this->assertDatabaseHas('audits', ['id' => $recentAudit->id]);
    }

    public function test_it_keeps_audit_logs_newer_than_90_days(): void
    {
        $teacherRole = Role::create(['name' => 'teacher']);
        $user = User::factory()->withProfile()->create();
        $user->profile->roles()->attach($teacherRole);

        $this->actingAs($user);

        // Create tracks with audit logs at various ages
        $track1 = Track::factory()->create();
        $audit1 = Audit::where('auditable_type', Track::class)
            ->where('auditable_id', $track1->id)
            ->first();
        $audit1->created_at = Carbon::now()->subDays(30);
        $audit1->save();

        $track2 = Track::factory()->create();
        $audit2 = Audit::where('auditable_type', Track::class)
            ->where('auditable_id', $track2->id)
            ->first();
        $audit2->created_at = Carbon::now()->subDays(60);
        $audit2->save();

        $track3 = Track::factory()->create();
        $audit3 = Audit::where('auditable_type', Track::class)
            ->where('auditable_id', $track3->id)
            ->first();
        $audit3->created_at = Carbon::now()->subDays(89);
        $audit3->save();

        // Run the cleanup command
        $this->artisan('audit:cleanup')->assertExitCode(0);

        // Verify all recent audit logs still exist
        $this->assertDatabaseHas('audits', ['id' => $audit1->id]);
        $this->assertDatabaseHas('audits', ['id' => $audit2->id]);
        $this->assertDatabaseHas('audits', ['id' => $audit3->id]);
    }

    public function test_cleanup_command_reports_deleted_count(): void
    {
        $teacherRole = Role::create(['name' => 'teacher']);
        $user = User::factory()->withProfile()->create();
        $user->profile->roles()->attach($teacherRole);

        $this->actingAs($user);

        // Create 3 tracks with old audit logs
        for ($i = 0; $i < 3; $i++) {
            $track = Track::factory()->create();
            $audit = Audit::where('auditable_type', Track::class)
                ->where('auditable_id', $track->id)
                ->first();
            $audit->created_at = Carbon::now()->subDays(100);
            $audit->save();
        }

        // Run the cleanup command
        $this->artisan('audit:cleanup')
            ->expectsOutput('Successfully deleted 3 audit log(s).')
            ->assertExitCode(0);
    }
}
