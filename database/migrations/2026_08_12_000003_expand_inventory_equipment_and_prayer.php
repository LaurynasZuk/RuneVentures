<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedSmallInteger('inventory_base_slots')->default(25);
            $table->unsignedTinyInteger('backpack_slots_unlocked')->default(1);
            $table->unsignedInteger('prayer_mana')->default(100);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('stack_limit')->nullable()->after('stackable');
            $table->string('equip_slot')->nullable()->after('stack_limit');
            $table->unsignedSmallInteger('inventory_slots_bonus')->default(0)->after('equip_slot');
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('slot')->nullable()->after('player_id');
            $table->dropUnique(['player_id', 'item_id']);
        });

        $playerIds = DB::table('inventory_items')->select('player_id')->distinct()->pluck('player_id');
        foreach ($playerIds as $playerId) {
            $slot = 1;
            foreach (DB::table('inventory_items')->where('player_id', $playerId)->orderBy('id')->get() as $row) {
                DB::table('inventory_items')->where('id', $row->id)->update(['slot' => $slot++]);
            }
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->unique(['player_id', 'slot']);
            $table->index(['player_id', 'item_id']);
        });

        Schema::create('player_backpacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot');
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->unique(['player_id', 'slot']);
        });

        Schema::create('player_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('slot');
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->unique(['player_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_equipment');
        Schema::dropIfExists('player_backpacks');

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropUnique(['player_id', 'slot']);
            $table->dropIndex(['player_id', 'item_id']);
            $table->dropColumn('slot');
            $table->unique(['player_id', 'item_id']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['stack_limit', 'equip_slot', 'inventory_slots_bonus']);
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['inventory_base_slots', 'backpack_slots_unlocked', 'prayer_mana']);
        });
    }
};
