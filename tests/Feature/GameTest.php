<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Player;
use App\Models\PlayerSkill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_player_can_open_the_game(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('game')
                ->where('location.name', 'Aldor Village')
                ->where('player.name', $user->name));
    }

    public function test_chopping_grants_logs_and_woodcutting_xp(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get(route('dashboard'));

        $this->actingAs($user)->post(route('game.chop'))->assertRedirect();

        $player = Player::whereBelongsTo($user)->firstOrFail();
        $this->assertSame(25, PlayerSkill::where('player_id', $player->id)->where('skill', 'woodcutting')->value('xp'));
        $this->assertSame(1, InventoryItem::where('player_id', $player->id)->value('quantity'));
    }

    public function test_player_can_only_travel_through_a_location_connection(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get(route('dashboard'));
        $destination = Location::create([
            'name' => 'Hidden Valley',
            'slug' => 'hidden-valley',
            'description' => 'Not connected.',
        ]);

        $this->actingAs($user)->post(route('game.travel', $destination))->assertForbidden();
    }
}
