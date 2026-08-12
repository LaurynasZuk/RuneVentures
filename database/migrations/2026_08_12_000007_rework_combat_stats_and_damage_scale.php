<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing encounters were created with the old tick/max-hit snapshot model.
        // Reset them so every new fight starts with the new interval + roll formulas.
        DB::table('combat_encounters')->delete();

        // RuneVentures uses a x10 HP / damage scale for finer combat balancing.
        DB::table('players')->update([
            'hitpoints' => DB::raw('hitpoints * 10'),
            'max_hitpoints' => DB::raw('max_hitpoints * 10'),
        ]);

        Schema::table('players', function (Blueprint $table) {
            $table->unsignedSmallInteger('hitpoints')->default(100)->change();
            $table->unsignedSmallInteger('max_hitpoints')->default(100)->change();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('attack_interval_ms')->nullable();
            $table->integer('attack_bonus')->default(0);
            $table->integer('strength_bonus')->default(0);
            $table->integer('defence_bonus')->default(0);

            $table->dropColumn(['attack_ticks', 'max_hit']);
        });

        Schema::table('combat_encounters', function (Blueprint $table) {
            $table->unsignedInteger('player_attack_interval_ms');
            $table->unsignedInteger('monster_attack_interval_ms');
            $table->unsignedInteger('player_attack_roll')->default(0);
            $table->unsignedInteger('player_defence_roll')->default(0);
            $table->unsignedInteger('monster_attack_roll')->default(0);
            $table->unsignedInteger('monster_defence_roll')->default(0);

            $table->dropColumn([
                'monster_attack_ticks',
                'player_attack_ticks',
                'player_next_attack_at',
                'monster_next_attack_at',
            ]);
        });
    }

    public function down(): void
    {
        DB::table('combat_encounters')->delete();

        Schema::table('combat_encounters', function (Blueprint $table) {
            $table->unsignedTinyInteger('monster_attack_ticks')->default(1);
            $table->unsignedTinyInteger('player_attack_ticks')->default(1);
            $table->timestamp('player_next_attack_at')->nullable();
            $table->timestamp('monster_next_attack_at')->nullable();

            $table->dropColumn([
                'player_attack_interval_ms',
                'monster_attack_interval_ms',
                'player_attack_roll',
                'player_defence_roll',
                'monster_attack_roll',
                'monster_defence_roll',
            ]);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->unsignedTinyInteger('attack_ticks')->nullable();
            $table->unsignedSmallInteger('max_hit')->nullable();

            $table->dropColumn([
                'attack_interval_ms',
                'attack_bonus',
                'strength_bonus',
                'defence_bonus',
            ]);
        });

        DB::table('players')->orderBy('id')->chunkById(100, function ($players) {
            foreach ($players as $player) {
                DB::table('players')->where('id', $player->id)->update([
                    'hitpoints' => max(1, (int) round($player->hitpoints / 10)),
                    'max_hitpoints' => max(1, (int) round($player->max_hitpoints / 10)),
                ]);
            }
        });

        Schema::table('players', function (Blueprint $table) {
            $table->unsignedSmallInteger('hitpoints')->default(10)->change();
            $table->unsignedSmallInteger('max_hitpoints')->default(10)->change();
        });
    }
};
