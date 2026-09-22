<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_roles', function (Blueprint $table) {
            // Composite index — used by all 5 whereDoesntHave('roles', ...) calls
            // across TeacherDashboardService, StudentProgressController,
            // LeaderboardController, and SubmissionService.
            // Lets the NOT EXISTS subquery resolve in one index range scan.
            $table->index(
                ['profile_id', 'role_id'],
                'profile_roles_profile_role_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('profile_roles', function (Blueprint $table) {
            $table->dropIndex('profile_roles_profile_role_index');
        });
    }
};
