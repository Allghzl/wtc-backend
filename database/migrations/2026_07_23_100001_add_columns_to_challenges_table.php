<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->nullable()->after('type');
            $table->integer('order')->default(0)->after('difficulty');
            $table->integer('points')->default(0)->after('max_score')->comment('Points for user exp/leaderboarding');
            $table->unsignedInteger('allowed_attempts')->default(1)->after('points')->comment('Number of allowed attempts for the challenge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn(['difficulty', 'order', 'points']);
        });
    }
};
