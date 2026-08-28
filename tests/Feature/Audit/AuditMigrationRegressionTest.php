<?php

namespace Tests\Feature\Audit;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditUserIdBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

/**
 * Regression coverage for the forward-migration actor-history defect.
 *
 * Context: 2026_08_28_000000_change_audit_user_id_to_uuid.php renames the
 * original user_id column to legacy_user_id, adds a new UUID user_id, then
 * calls AuditUserIdBackfill to copy matching values across.
 *
 * These tests create representative legacy state (legacy_user_id set, user_id
 * null) directly via the query builder — bypassing the ORM so they are
 * decoupled from how the Auditing library records events — and verify that the
 * backfill service resolves the UUID actor relation and leaves unresolvable rows
 * actorless rather than corrupted or misassociated.
 */
class AuditMigrationRegressionTest extends TestCase
{
    use RefreshDatabase;

    private string $connection;
    private string $table;
    private string $morphPrefix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection   = config('audit.drivers.database.connection') ?? config('database.default');
        $this->table        = config('audit.drivers.database.table', 'audits');
        $this->morphPrefix  = config('audit.user.morph_prefix', 'user');
    }

    /**
     * Insert a raw audit row simulating the post-rename, pre-backfill state:
     * legacy_user_id is set, user_id is null.
     */
    private function insertLegacyAuditRow(string $legacyUserId, ?string $userType = null): int
    {
        return DB::connection($this->connection)->table($this->table)->insertGetId([
            'legacy_user_id'   => $legacyUserId,
            'user_id'          => null,
            'user_type'        => $userType ?? User::class,
            'event'            => 'created',
            'auditable_type'   => 'App\\Models\\Track',
            'auditable_id'     => 1,
            'old_values'       => '[]',
            'new_values'       => '{"title":"Test"}',
            'url'              => null,
            'ip_address'       => null,
            'user_agent'       => null,
            'tags'             => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function test_backfill_resolves_resolvable_legacy_rows_to_uuid_user_id(): void
    {
        $user = User::factory()->withProfile()->create();

        $auditId = $this->insertLegacyAuditRow($user->id);

        $updated = app(AuditUserIdBackfill::class)->run(
            $this->connection, $this->table, $this->morphPrefix
        );

        $this->assertSame(1, $updated);

        $row = DB::connection($this->connection)->table($this->table)->find($auditId);
        $this->assertSame($user->id, $row->user_id);
        // Legacy reference is retained for reversibility.
        $this->assertSame($user->id, $row->legacy_user_id);
    }

    public function test_backfill_leaves_unresolvable_rows_actorless_with_legacy_reference(): void
    {
        $ghostId = '00000000-0000-0000-0000-000000000001'; // no matching user

        $auditId = $this->insertLegacyAuditRow($ghostId);

        $updated = app(AuditUserIdBackfill::class)->run(
            $this->connection, $this->table, $this->morphPrefix
        );

        $this->assertSame(0, $updated);

        $row = DB::connection($this->connection)->table($this->table)->find($auditId);
        // user_id must stay null — no corrupt or misassociated actor.
        $this->assertNull($row->user_id);
        // Legacy reference is preserved so the audit row is not silently lost.
        $this->assertSame($ghostId, $row->legacy_user_id);
    }

    public function test_backfill_returns_zero_on_fresh_empty_table(): void
    {
        // Simulates a fresh install: no legacy rows exist.
        $updated = app(AuditUserIdBackfill::class)->run(
            $this->connection, $this->table, $this->morphPrefix
        );

        $this->assertSame(0, $updated);
    }

    public function test_backfill_handles_mixed_resolvable_and_unresolvable_rows(): void
    {
        $userA = User::factory()->withProfile()->create();
        $userB = User::factory()->withProfile()->create();
        $ghostId = '00000000-0000-0000-0000-000000000002';

        $idA    = $this->insertLegacyAuditRow($userA->id);
        $idB    = $this->insertLegacyAuditRow($userB->id);
        $idGhost = $this->insertLegacyAuditRow($ghostId);

        $updated = app(AuditUserIdBackfill::class)->run(
            $this->connection, $this->table, $this->morphPrefix
        );

        $this->assertSame(2, $updated);

        $rowA    = DB::connection($this->connection)->table($this->table)->find($idA);
        $rowB    = DB::connection($this->connection)->table($this->table)->find($idB);
        $rowGhost = DB::connection($this->connection)->table($this->table)->find($idGhost);

        $this->assertSame($userA->id, $rowA->user_id);
        $this->assertSame($userB->id, $rowB->user_id);
        $this->assertNull($rowGhost->user_id);
        $this->assertSame($ghostId, $rowGhost->legacy_user_id);
    }

    public function test_actor_user_relation_resolves_after_backfill(): void
    {
        $user = User::factory()->withProfile()->create();

        $this->insertLegacyAuditRow($user->id);

        app(AuditUserIdBackfill::class)->run(
            $this->connection, $this->table, $this->morphPrefix
        );

        $audit = Audit::with('user.profile')->latest('id')->firstOrFail();

        // The user() morphTo relation must resolve to the correct User model.
        $this->assertNotNull($audit->user);
        $this->assertSame($user->id, $audit->user->id);
        // The profile chain used by AuditLogResource must also be intact.
        $this->assertNotNull($audit->user->profile);
    }

    public function test_profile_id_filter_works_after_backfill(): void
    {
        $teacherRole = Role::create(['name' => 'teacher']);
        $userA = User::factory()->withProfile()->create();
        $userB = User::factory()->withProfile()->create();
        $userA->profile->roles()->attach($teacherRole);
        $userB->profile->roles()->attach($teacherRole);

        $idA = $this->insertLegacyAuditRow($userA->id);
        $idB = $this->insertLegacyAuditRow($userB->id);

        app(AuditUserIdBackfill::class)->run(
            $this->connection, $this->table, $this->morphPrefix
        );

        // Replicates the whereHas filter in AuditLogController::index().
        $results = Audit::whereHas('user.profile', function ($q) use ($userA) {
            $q->where('id', $userA->profile->id);
        })->pluck('id')->all();

        $this->assertContains($idA, $results);
        $this->assertNotContains($idB, $results);
    }

    public function test_backfill_skips_rows_where_user_id_already_set(): void
    {
        $user = User::factory()->withProfile()->create();

        // Row already has user_id set (already migrated — should not be double-processed).
        DB::connection($this->connection)->table($this->table)->insert([
            'legacy_user_id'   => $user->id,
            'user_id'          => $user->id,
            'user_type'        => User::class,
            'event'            => 'updated',
            'auditable_type'   => 'App\\Models\\Track',
            'auditable_id'     => 1,
            'old_values'       => '[]',
            'new_values'       => '{}',
            'url'              => null,
            'ip_address'       => null,
            'user_agent'       => null,
            'tags'             => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $updated = app(AuditUserIdBackfill::class)->run(
            $this->connection, $this->table, $this->morphPrefix
        );

        // Already-migrated row must not count as a newly updated row.
        $this->assertSame(0, $updated);
    }
}
