<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_class_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_class_id')
                ->constrained('study_classes')
                ->cascadeOnDelete();
            $table->foreignId('track_id')
                ->constrained('tracks')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['study_class_id', 'track_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_class_tracks');
    }
};
