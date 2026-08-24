<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Make allowed_attempts nullable to support unlimited attempts.
     * When allowed_attempts is NULL, students can submit unlimited times.
     */
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->unsignedInteger('allowed_attempts')
                ->nullable()
                ->default(null)
                ->change()
                ->comment('Number of allowed attempts for the challenge. NULL = unlimited attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->unsignedInteger('allowed_attempts')
                ->default(1)
                ->change()
                ->comment('Number of allowed attempts for the challenge');
        });
    }
};
