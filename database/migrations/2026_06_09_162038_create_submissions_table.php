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
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUuid('profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status');
            $table->timestamp('submitted_at')
                ->nullable()
                ->comment('Actual submission time, different from created_at for drafts');
            $table->json('submitted_content')->nullable();
            $table->string('file_path')->nullable();
            $table->integer('auto_score')->nullable();
            $table->integer('manual_score')->nullable();
            $table->text('feedback')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(
                ['challenge_id', 'profile_id', 'attempt_number'],
                'submissions_unique_attempt'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
