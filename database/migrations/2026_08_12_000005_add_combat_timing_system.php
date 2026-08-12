<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('combat_style')->nullable()->after('equip_slot');
            $table->unsignedTinyInteger('attack_ticks')->nullable()->after('combat_style');
            $table->unsignedSmallInteger('max_hit')->nullable()->after('attack_ticks');
        });

        Schema::create('combat_encounters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('monster_slug');
            $table->string('monster_name');
            $table->unsignedSmallInteger('monster_level');
            $table->unsignedSmallInteger('monster_hp');
            $table->unsignedSmallInteger('monster_max_hp');
            $table->unsignedTinyInteger('monster_attack_ticks');
            $table->unsignedSmallInteger('monster_max_hit');
            $table->string('player_style')->default('melee');
            $table->unsignedTinyInteger('player_attack_ticks');
            $table->unsignedSmallInteger('player_max_hit');
            $table->timestamp('player_next_attack_at')->nullable();
            $table->timestamp('monster_next_attack_at')->nullable();
            $table->string('status')->default('active');
            $table->string('last_event')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combat_encounters');

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['combat_style', 'attack_ticks', 'max_hit']);
        });
    }
};
