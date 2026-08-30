<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_achievements', function (Blueprint $table) {
            if (!Schema::hasColumn('profile_achievements', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('achievement_id');
            }
            if (!Schema::hasColumn('profile_achievements', 'awarded_at')) {
                $table->timestamp('awarded_at')->nullable()->after('is_pinned');
            }
        });
    }

    public function down(): void
    {
        Schema::table('profile_achievements', function (Blueprint $table) {
            $table->dropColumnIfExists('is_pinned');
            $table->dropColumnIfExists('awarded_at');
        });
    }
};
