<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            if (!Schema::hasColumn('achievements', 'trigger_type')) {
                $table->string('trigger_type')->default('manual')
                    ->after('description');
                    // values: manual, first_login, track_complete,
                    //         challenge_grade_a, certificate_earned,
                    //         points_milestone, streak_days
            }
            if (!Schema::hasColumn('achievements', 'trigger_config')) {
                $table->json('trigger_config')->nullable()->after('trigger_type');
            }
            if (!Schema::hasColumn('achievements', 'points_reward')) {
                $table->integer('points_reward')->default(0)->after('trigger_config');
            }
            if (!Schema::hasColumn('achievements', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('points_reward');
            }
            if (!Schema::hasColumn('achievements', 'badge_emoji')) {
                $table->string('badge_emoji', 16)->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropColumnIfExists('trigger_type');
            $table->dropColumnIfExists('trigger_config');
            $table->dropColumnIfExists('points_reward');
            $table->dropColumnIfExists('is_active');
            $table->dropColumnIfExists('badge_emoji');
        });
    }
};
