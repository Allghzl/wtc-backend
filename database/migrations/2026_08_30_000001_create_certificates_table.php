<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('track_id')->constrained('tracks')->cascadeOnDelete();
            $table->uuid('certificate_number')->unique();
            $table->string('grade', 3); // A+, A, B+, B, C+, C, D
            $table->decimal('grade_score', 5, 2);
            $table->enum('status', ['issued', 'update_available'])->default('issued');
            $table->timestamp('issued_at');
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->unique(['profile_id', 'track_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
