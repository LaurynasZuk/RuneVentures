<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('combat_encounters', function (Blueprint $table) {
            $table->unsignedBigInteger('player_next_attack_ms')->nullable()->after('player_next_attack_at');
            $table->unsignedBigInteger('monster_next_attack_ms')->nullable()->after('monster_next_attack_at');
        });
    }

    public function down(): void
    {
        Schema::table('combat_encounters', function (Blueprint $table) {
            $table->dropColumn(['player_next_attack_ms', 'monster_next_attack_ms']);
        });
    }
};
