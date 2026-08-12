<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Item;
use App\Models\Location;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $village = Location::firstOrCreate(['slug' => 'starter-village'], [
            'name' => 'Aldor Village',
            'description' => 'A quiet frontier settlement where every adventure begins.',
            'region' => 'Greenreach',
        ]);
        $woods = Location::firstOrCreate(['slug' => 'whispering-woods'], [
            'name' => 'Whispering Woods',
            'description' => 'Silver leaves whisper old secrets beneath a dim green canopy.',
            'region' => 'Greenreach',
        ]);
        $village->destinations()->syncWithoutDetaching([$woods->id => ['label' => 'Whispering Woods', 'travel_seconds' => 4]]);
        $woods->destinations()->syncWithoutDetaching([$village->id => ['label' => 'Aldor Village', 'travel_seconds' => 4]]);

        Item::firstOrCreate(['slug' => 'logs'], ['name' => 'Logs', 'icon' => 'logs', 'stackable' => true]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);
    }
}
