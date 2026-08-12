<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Player;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NpcController extends Controller
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    public function newcomerGuide(Request $request): RedirectResponse
    {
        $player = Player::query()
            ->with(['location', 'backpacks.item'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_unless($player->location?->slug === 'starter-village', 403);

        if ($player->starter_weapon_claimed) {
            return back()->with('game', 'Naujokų gidas: pirmąjį Bronze sword jau gavai.');
        }

        $granted = DB::transaction(function () use ($player): bool {
            $locked = Player::query()
                ->with('backpacks.item')
                ->whereKey($player->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->starter_weapon_claimed) {
                return true;
            }

            $sword = Item::updateOrCreate(
                ['slug' => 'bronze-sword'],
                [
                    'name' => 'Bronze sword',
                    'icon' => 'weapon',
                    'category' => 'weapon',
                    'tier' => 1,
                    'f2p' => true,
                    'stackable' => false,
                    'stack_limit' => 1,
                    'equip_slot' => 'weapon',
                    'combat_style' => 'melee',
                    'attack_interval_ms' => 2400,
                    'attack_bonus' => 4,
                    'strength_bonus' => 5,
                    'defence_bonus' => 0,
                    'required_skill' => 'attack',
                    'required_level' => 1,
                    'game_data' => [
                        'source_system' => 'osrs',
                        'weapon_type' => 'sword',
                        'hand' => 'one_hand',
                        'two_handed' => false,
                        'attack_style' => 'stab',
                        'value' => 26,
                        'tradeable' => true,
                        'attack_bonuses' => [
                            'stab' => 4,
                            'slash' => 3,
                            'crush' => -2,
                            'magic' => 0,
                            'ranged' => 0,
                        ],
                        'defence_bonuses' => [
                            'stab' => 0,
                            'slash' => 2,
                            'crush' => 1,
                            'magic' => 0,
                            'ranged' => 0,
                        ],
                        'melee_strength' => 5,
                    ],
                ],
            );

            if (! $this->inventory->add($locked, $sword, 1)) {
                return false;
            }

            $locked->update(['starter_weapon_claimed' => true]);

            return true;
        });

        return back()->with(
            'game',
            $granted
                ? 'Naujokų gidas davė Bronze sword.'
                : 'Inventorius pilnas. Atlaisvink vietą ir grįžk pas Naujokų gidą.',
        );
    }
}
