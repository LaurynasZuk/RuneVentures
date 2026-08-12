<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('combat_encounters', function (Blueprint $table) {
            $table->json('combat_log')->nullable();
            $table->json('loot')->nullable();
            $table->unsignedBigInteger('ended_at_ms')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('combat_encounters', function (Blueprint $table) {
            $table->dropColumn(['combat_log', 'loot', 'ended_at_ms']);
        });
    }
};
