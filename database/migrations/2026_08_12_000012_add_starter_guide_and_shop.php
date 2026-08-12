<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->boolean('starter_weapon_claimed')->default(false);
        });

        Schema::create('shop_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('shop_key');
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('default_stock')->default(0);
            $table->unsignedInteger('sell_price')->default(0);
            $table->unsignedInteger('buy_price')->default(0);
            $table->timestamps();

            $table->unique(['shop_key', 'item_id']);
            $table->index('shop_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_stocks');

        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('starter_weapon_claimed');
        });
    }
};
