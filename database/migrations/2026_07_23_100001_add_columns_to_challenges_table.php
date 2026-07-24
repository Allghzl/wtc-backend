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
            $table->text('description')->nullable()->after('title');
            $table->text('instructions')->nullable()->after('content');
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->nullable()->after('instructions');
            $table->integer('order')->default(0)->after('difficulty');
            $table->integer('points')->default(0)->after('max_score')->comment('Points for gamification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn(['description', 'instructions', 'difficulty', 'order', 'points']);
        });
    }
};
