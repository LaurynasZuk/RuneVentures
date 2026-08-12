<?php

namespace Tests\Feature;

use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_catalog_is_seeded_with_rs3_progression_and_resources(): void
    {
        $this->assertSame(315, Item::query()->count());

        $runeLongsword = Item::where('slug', 'rune-longsword')->firstOrFail();
        $this->assertTrue($runeLongsword->f2p);
        $this->assertSame(50, $runeLongsword->tier);
        $this->assertSame(850, $runeLongsword->game_data['rs3_accuracy']);
        $this->assertSame(612.5, $runeLongsword->game_data['rs3_auto_damage']);
        $this->assertSame(480.0, (float) $runeLongsword->game_data['rs3_ability_damage']);
        $this->assertSame(3000, $runeLongsword->attack_interval_ms);

        $blackLongsword = Item::where('slug', 'black-longsword')->firstOrFail();
        $this->assertSame(306.3, $blackLongsword->game_data['rs3_auto_damage']);

        $dragonDagger = Item::where('slug', 'dragon-dagger')->firstOrFail();
        $this->assertFalse($dragonDagger->f2p);
        $this->assertSame(1132, $dragonDagger->game_data['rs3_accuracy']);
        $this->assertSame(576.0, (float) $dragonDagger->game_data['rs3_auto_damage']);
        $this->assertSame(576.0, (float) $dragonDagger->game_data['rs3_ability_damage']);
    }

    public function test_armour_uses_rs3_armour_damage_and_life_point_values(): void
    {
        $runePlatebody = Item::where('slug', 'rune-platebody')->firstOrFail();
        $this->assertSame('tank', $runePlatebody->game_data['armour_type']);
        $this->assertSame(195.5, $runePlatebody->game_data['rs3_armour']);
        $this->assertSame(750, $runePlatebody->game_data['rs3_life_points']);
        $this->assertSame(0.0, (float) $runePlatebody->game_data['rs3_damage_bonus']);

        $dragonFullHelm = Item::where('slug', 'dragon-full-helm')->firstOrFail();
        $this->assertSame('power', $dragonFullHelm->game_data['armour_type']);
        $this->assertSame(196.6, $dragonFullHelm->game_data['rs3_armour']);
        $this->assertSame(15.0, (float) $dragonFullHelm->game_data['rs3_damage_bonus']);
        $this->assertSame(0, $dragonFullHelm->game_data['rs3_life_points']);

        $dragonPlatelegs = Item::where('slug', 'dragon-platelegs')->firstOrFail();
        $this->assertSame(216.2, $dragonPlatelegs->game_data['rs3_armour']);
        $this->assertSame(18.7, $dragonPlatelegs->game_data['rs3_damage_bonus']);
    }

    public function test_tools_resources_food_and_prayer_values_are_available(): void
    {
        $dragonPickaxe = Item::where('slug', 'dragon-pickaxe')->firstOrFail();
        $this->assertSame(33, $dragonPickaxe->game_data['mining_min_damage']);
        $this->assertSame(63, $dragonPickaxe->game_data['mining_average_damage']);
        $this->assertSame(93, $dragonPickaxe->game_data['mining_max_damage']);
        $this->assertSame(105, $dragonPickaxe->game_data['mining_penetration']);

        $adamantHatchet = Item::where('slug', 'adamant-hatchet')->firstOrFail();
        $this->assertSame(40, $adamantHatchet->game_data['cutting_power_tier']);
        $this->assertSame(628, $adamantHatchet->game_data['rs3_combat_accuracy']);
        $this->assertSame(245.0, (float) $adamantHatchet->game_data['rs3_auto_damage']);
        $this->assertSame(192.0, (float) $adamantHatchet->game_data['rs3_ability_damage']);

        $runite = Item::where('slug', 'runite-ore')->firstOrFail();
        $this->assertSame(50, $runite->game_data['mining_level']);
        $this->assertSame(600, $runite->game_data['rock_durability']);
        $this->assertSame(75, $runite->game_data['rock_hardness']);

        $swordfish = Item::where('slug', 'swordfish')->firstOrFail();
        $this->assertSame(1400, $swordfish->game_data['heal_amount']);
        $this->assertSame(140.0, (float) $swordfish->game_data['cooking_xp']);

        $dragonBones = Item::where('slug', 'dragon-bones')->firstOrFail();
        $this->assertTrue($dragonBones->f2p);
        $this->assertSame(72.0, (float) $dragonBones->game_data['prayer_xp']);

        $ancientBones = Item::where('slug', 'ancient-bones')->firstOrFail();
        $this->assertSame(200.0, (float) $ancientBones->game_data['prayer_xp']);
        $this->assertTrue($ancientBones->game_data['limited_quest_reward']);
    }
}
