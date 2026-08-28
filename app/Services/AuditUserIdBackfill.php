<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Backfills the UUID user_id column on the audits table from the legacy_user_id
 * column that was preserved during the forward migration.
 *
 * For each audit row where user_id is null and legacy_user_id is set, this service
 * looks up the matching user by UUID. Rows whose legacy_user_id does not match any
 * current user (unresolvable) are left actorless: user_id stays null and
 * legacy_user_id is retained for reference.
 */
class AuditUserIdBackfill
{
    /**
     * Run the backfill and return the number of rows updated.
     *
     * Uses portable query-builder chunking so no raw SQL driver assumptions are
     * required. Safe to call on an empty table (fresh install): chunk yields
     * nothing and returns 0.
     *
     * @param  string  $connection  Database connection name (from audit config)
     * @param  string  $table       Audit table name (from audit config)
     * @param  string  $morphPrefix User morph prefix (from audit config, default "user")
     * @return int     Number of audit rows whose user_id was successfully backfilled
     */
    public function run(string $connection, string $table, string $morphPrefix): int
    {
        $legacyColumn = 'legacy_' . $morphPrefix . '_id';
        $uuidColumn   = $morphPrefix . '_id';
        $filled       = 0;

        DB::connection($connection)
            ->table($table)
            ->whereNull($uuidColumn)
            ->whereNotNull($legacyColumn)
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($connection, $table, $uuidColumn, $legacyColumn, &$filled) {
                // Collect all distinct legacy IDs present in this chunk.
                $legacyIds = $rows->pluck($legacyColumn)->filter()->unique()->values()->all();

                if (empty($legacyIds)) {
                    return;
                }

                // Resolve which legacy IDs actually correspond to live users.
                // pluck('id', 'id') gives an associative map for O(1) lookup.
                $existing = DB::connection($connection)
                    ->table('users')
                    ->whereIn('id', $legacyIds)
                    ->pluck('id', 'id')
                    ->all();

                foreach ($rows as $row) {
                    $legacyId = $row->{$legacyColumn};

                    if ($legacyId !== null && array_key_exists($legacyId, $existing)) {
                        DB::connection($connection)
                            ->table($table)
                            ->where('id', $row->id)
                            ->update([$uuidColumn => $legacyId]);

                        $filled++;
                    }
                    // Unresolvable row: user_id remains null, legacy_user_id is
                    // preserved so the audit record is retained as actorless rather
                    // than lost.
                }
            });

        return $filled;
    }
}
