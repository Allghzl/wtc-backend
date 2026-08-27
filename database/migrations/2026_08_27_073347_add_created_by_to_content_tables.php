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
        // Add created_by to tracks table
        Schema::table('tracks', function (Blueprint $table) {
            $table->foreignUuid('created_by')
                ->nullable()
                ->after('image_url')
                ->constrained('profiles')
                ->nullOnDelete();
        });

        // Add created_by to modules table
        Schema::table('modules', function (Blueprint $table) {
            $table->foreignUuid('created_by')
                ->nullable()
                ->after('track_id')
                ->constrained('profiles')
                ->nullOnDelete();
        });

        // Add created_by to lessons table
        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignUuid('created_by')
                ->nullable()
                ->after('module_id')
                ->constrained('profiles')
                ->nullOnDelete();
        });

        // Add created_by to challenges table
        Schema::table('challenges', function (Blueprint $table) {
            $table->foreignUuid('created_by')
                ->nullable()
                ->after('lesson_id')
                ->constrained('profiles')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });

        Schema::table('challenges', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
