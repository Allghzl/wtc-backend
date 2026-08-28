<?php

use App\Services\AuditUserIdBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection   = config('audit.drivers.database.connection') ?? config('database.default');
        $table        = config('audit.drivers.database.table', 'audits');
        $morphPrefix  = config('audit.user.morph_prefix', 'user');
        $legacyColumn = 'legacy_' . $morphPrefix . '_id';
        $uuidColumn   = $morphPrefix . '_id';

        Schema::connection($connection)->table($table, function (Blueprint $table) use ($morphPrefix, $legacyColumn, $uuidColumn) {
            // 0. Drop the auto-named composite index before renaming the column
            //    to avoid leaving an orphaned index on (legacy_user_id, user_type).
            $table->dropIndex('audits_user_id_user_type_index');

            // 1. Preserve the old numeric/string value under a legacy name.
            $table->renameColumn($uuidColumn, $legacyColumn);

            // 2. Add the new UUID-typed column.
            $table->uuid($uuidColumn)->nullable()->after($morphPrefix . '_type');

            // 3. Replace the old composite index with one on the new UUID column.
            $table->index([$uuidColumn, $morphPrefix . '_type'], 'audits_actor_lookup_index');
        });

        // 4. Backfill: copy legacy_user_id → user_id where the referenced user
        //    still exists. Unresolvable rows retain their legacy reference and get
        //    a null user_id (actorless) rather than a corrupt or misassociated value.
        app(AuditUserIdBackfill::class)->run($connection, $table, $morphPrefix);
    }

    public function down(): void
    {
        $connection   = config('audit.drivers.database.connection') ?? config('database.default');
        $table        = config('audit.drivers.database.table', 'audits');
        $morphPrefix  = config('audit.user.morph_prefix', 'user');
        $legacyColumn = 'legacy_' . $morphPrefix . '_id';
        $uuidColumn   = $morphPrefix . '_id';

        Schema::connection($connection)->table($table, function (Blueprint $table) use ($morphPrefix, $legacyColumn, $uuidColumn) {
            $table->dropIndex('audits_actor_lookup_index');
            $table->dropColumn($uuidColumn);
            $table->renameColumn($legacyColumn, $uuidColumn);
        });
    }
};
