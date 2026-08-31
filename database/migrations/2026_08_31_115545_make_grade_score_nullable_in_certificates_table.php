<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // nullable: tracks with no challenges get grade "-" and score null
            $table->decimal('grade_score', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->decimal('grade_score', 5, 2)->nullable(false)->change();
        });
    }
};
