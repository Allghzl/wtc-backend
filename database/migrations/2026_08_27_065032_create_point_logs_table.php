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
        Schema::create('point_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('profile_id');
            $table->integer('points'); // Positive for addition, negative for subtraction
            $table->string('reason'); // e.g., 'lesson_completion', 'submission_graded'
            $table->json('metadata')->nullable(); // Additional context
            $table->integer('balance_after'); // Points balance after this transaction
            $table->timestamps();

            // Foreign key
            $table->foreign('profile_id')
                ->references('id')
                ->on('profiles')
                ->onDelete('cascade');

            // Indexes
            $table->index('profile_id');
            $table->index('reason');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_logs');
    }
};
