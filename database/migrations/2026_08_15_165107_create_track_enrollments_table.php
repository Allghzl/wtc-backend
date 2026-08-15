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
        Schema::create('track_enrollments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('track_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();

            $table->enum('status', [
                'active',
                'completed',
                'dropped',
                'paused',
            ])->default('active')->index();

            $table->timestamp('enrolled_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dropped_at')->nullable();

            $table->timestamps();

            $table->unique(['track_id', 'profile_id']);
            $table->index(['profile_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('track_enrollments');
    }
};
