<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('players')->pluck('id') as $playerId) {
            DB::table('player_equipment')->updateOrInsert(
                [
                    'player_id' => $playerId,
                    'slot' => 'belt',
                ],
                [
                    'item_id' => null,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('player_equipment')
            ->where('slot', 'belt')
            ->delete();
    }
};
