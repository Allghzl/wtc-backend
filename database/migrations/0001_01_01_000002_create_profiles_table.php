<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        |
        | Identity & Authentication
        |
        */

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('puid')->nullable()->unique();
            $table->string('name')->unique();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->enum('provider', ['local', 'pinat'])->default('local')->index();
            $table->text('avatar')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Profiles
        |--------------------------------------------------------------------------
        |
        | Application Data
        |
        */

        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('study_class_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('display_name')->nullable();
            $table->integer('points')->default(0);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        /*
        |--------------------------------------------------------------------------
        | Sessions
        |--------------------------------------------------------------------------
        */

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('users');
    }
};
