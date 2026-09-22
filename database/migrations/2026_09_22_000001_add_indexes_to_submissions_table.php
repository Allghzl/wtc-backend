<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            // Single-column index — used by teacher dashboard COUNT queries and
            // the paginated submission list which filter solely on status.
            $table->index('status', 'submissions_status_index');

            // Composite index — covers the hot path in ProgressService and
            // areChallengesCompleted: WHERE profile_id = ? AND challenge_id IN (...)
            // AND status IN ('graded', 'reviewed').
            $table->index(
                ['profile_id', 'challenge_id', 'status'],
                'submissions_profile_challenge_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex('submissions_status_index');
            $table->dropIndex('submissions_profile_challenge_status_index');
        });
    }
};
