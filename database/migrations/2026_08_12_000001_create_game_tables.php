<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('region')->default('Greenreach');
            $table->timestamps();
        });

        Schema::create('location_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('destination_id')->constrained('locations')->cascadeOnDelete();
            $table->string('label');
            $table->unsignedSmallInteger('travel_seconds')->default(3);
            $table->unique(['location_id', 'destination_id']);
        });

        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained();
            $table->string('name');
            $table->unsignedSmallInteger('hitpoints')->default(10);
            $table->unsignedSmallInteger('max_hitpoints')->default(10);
            $table->unsignedSmallInteger('prayer_points')->default(1);
            $table->timestamps();
        });

        Schema::create('player_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('skill');
            $table->unsignedBigInteger('xp')->default(0);
            $table->unique(['player_id', 'skill']);
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->default('package');
            $table->boolean('stackable')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unique(['player_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('items');
        Schema::dropIfExists('player_skills');
        Schema::dropIfExists('players');
        Schema::dropIfExists('location_connections');
        Schema::dropIfExists('locations');
    }
};
