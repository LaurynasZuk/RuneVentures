<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\Player;
use App\Models\PlayerEquipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopGuideEquipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_newcomer_guide_gives_one_bronze_sword_only_once(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get(route('main'))->assertOk();

        $this->actingAs($user)
            ->post(route('game.npc.newcomer-guide'))
            ->assertRedirect();

        $player = Player::whereBelongsTo($user)->firstOrFail();
        $sword = Item::where('slug', 'bronze-sword')->firstOrFail();

        $this->assertTrue($player->fresh()->starter_weapon_claimed);
        $this->assertSame(1, InventoryItem::query()
            ->where('player_id', $player->id)
            ->where('item_id', $sword->id)
            ->sum('quantity'));

        $this->actingAs($user)
            ->post(route('game.npc.newcomer-guide'))
            ->assertRedirect();

        $this->assertSame(1, InventoryItem::query()
            ->where('player_id', $player->id)
            ->where('item_id', $sword->id)
            ->sum('quantity'));
    }

    public function test_coldbreeze_general_store_has_lumbridge_default_stock(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get(route('main'))->assertOk();

        $this->actingAs($user)
            ->get(route('game.shop'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('shop')
                ->where('shop.keeper', 'Pardavėjas')
                ->has('stock', 12));
    }

    public function test_equipping_two_handed_weapon_removes_shield_to_inventory(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get(route('main'))->assertOk();
        $player = Player::whereBelongsTo($user)->firstOrFail();

        $shield = Item::create([
            'name' => 'Test shield',
            'slug' => 'test-shield',
            'icon' => 'shield',
            'category' => 'armour',
            'stackable' => false,
            'stack_limit' => 1,
            'equip_slot' => 'shield',
            'game_data' => ['source_system' => 'osrs'],
        ]);

        $twoHanded = Item::create([
            'name' => 'Test 2h sword',
            'slug' => 'test-2h-sword',
            'icon' => 'weapon',
            'category' => 'weapon',
            'stackable' => false,
            'stack_limit' => 1,
            'equip_slot' => 'weapon',
            'combat_style' => 'melee',
            'game_data' => [
                'source_system' => 'osrs',
                'two_handed' => true,
            ],
        ]);

        InventoryItem::create([
            'player_id' => $player->id,
            'slot' => 1,
            'item_id' => $shield->id,
            'quantity' => 1,
        ]);
        InventoryItem::create([
            'player_id' => $player->id,
            'slot' => 2,
            'item_id' => $twoHanded->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('game.equipment.equip', ['slot' => 1]))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('game.equipment.equip', ['slot' => 2]))
            ->assertRedirect();

        $this->assertSame($twoHanded->id, PlayerEquipment::query()
            ->where('player_id', $player->id)
            ->where('slot', 'weapon')
            ->value('item_id'));
        $this->assertNull(PlayerEquipment::query()
            ->where('player_id', $player->id)
            ->where('slot', 'shield')
            ->value('item_id'));
        $this->assertTrue(InventoryItem::query()
            ->where('player_id', $player->id)
            ->where('item_id', $shield->id)
            ->exists());
    }
}
