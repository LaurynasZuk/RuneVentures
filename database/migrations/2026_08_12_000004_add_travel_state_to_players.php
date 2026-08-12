<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->foreignId('travel_destination_id')
                ->nullable()
                ->after('location_id')
                ->constrained('locations')
                ->nullOnDelete();
            $table->timestamp('travel_ends_at')->nullable()->after('travel_destination_id');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropConstrainedForeignId('travel_destination_id');
            $table->dropColumn('travel_ends_at');
        });
    }
};
