<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_progress', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('profile_puid')
                ->constrained('profiles', 'puid')
                ->cascadeOnDelete();

            $table->foreignId('track_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['profile_puid', 'track_id', 'module_id', 'lesson_id'],
                'student_progress_unique_scope'
            );
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('profile_puid')
                ->nullable()
                ->constrained('profiles', 'puid')
                ->nullOnDelete();

            $table->string('event');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();

            $table->timestamps();

            $table->index(['event', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('challenge_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('profile_puid')
                ->constrained('profiles', 'puid')
                ->cascadeOnDelete();

            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('attempt_number');
            $table->integer('score')->nullable();
            $table->string('status')->default('pending');

            $table->timestamps();

            $table->unique(
                ['profile_puid', 'challenge_id', 'attempt_number'],
                'challenge_attempts_unique_number'
            );
        });

        Schema::create('leaderboard_snapshots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('track_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('challenge_id')->nullable()->constrained()->nullOnDelete();

            $table->json('rankings');
            $table->timestamp('captured_at');

            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();

            $table->timestamps();
        });

        Schema::create('notification_receivers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();

            $table->foreignUuid('profile_puid')
                ->constrained('profiles', 'puid')
                ->cascadeOnDelete();

            $table->string('channel')->default('in_app');
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['notification_id', 'profile_puid', 'channel'],
                'notification_receivers_unique_channel'
            );
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('profile_puid')
                ->nullable()
                ->constrained('profiles', 'puid')
                ->nullOnDelete();

            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('assessment_templates', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->unsignedInteger('rubric_version')->default(1);

            $table->timestamps();
        });

        Schema::create('assessment_components', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assessment_template_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->unsignedTinyInteger('weight');
            $table->json('rubric')->nullable();

            $table->timestamps();
        });

        Schema::create('assessment_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assessment_template_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('submission_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->integer('auto_grade')->nullable();
            $table->integer('mentor_review')->nullable();
            $table->integer('final_score')->nullable();

            $table->json('breakdown')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_results');
        Schema::dropIfExists('assessment_components');
        Schema::dropIfExists('assessment_templates');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notification_receivers');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('leaderboard_snapshots');
        Schema::dropIfExists('challenge_attempts');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('student_progress');
    }
};
