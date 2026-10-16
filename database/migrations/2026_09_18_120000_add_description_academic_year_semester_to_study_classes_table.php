<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_classes', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('academic_year', 20)->nullable()->after('description');
            $table->string('semester', 20)->nullable()->after('academic_year');
        });
    }

    public function down(): void
    {
        Schema::table('study_classes', function (Blueprint $table) {
            $table->dropColumn(['description', 'academic_year', 'semester']);
        });
    }
};
