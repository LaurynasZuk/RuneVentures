<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\Player;
use App\Models\PlayerEquipment;
use App\Services\CombatCalculator;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EquipmentController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly CombatCalculator $calculator,
    ) {
    }

    public function equip(Request $request, int $slot): RedirectResponse
    {
        $player = $this->player($request);

        $message = DB::transaction(function () use ($player, $slot): string {
            $stack = InventoryItem::query()
                ->with('item')
                ->where('player_id', $player->id)
                ->where('slot', $slot)
                ->lockForUpdate()
                ->first();

            if (! $stack) {
                return 'Daikto šiame inventoriaus slote nėra.';
            }

            $item = $stack->item;
            if (! $item->equip_slot) {
                return 'Šio daikto negalima ekipuoti.';
            }

            if (! $this->meetsRequirement($player, $item)) {
                return "Reikia {$item->required_skill} {$item->required_level} lygio.";
            }

            $equipment = PlayerEquipment::query()
                ->with('item')
                ->where('player_id', $player->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('slot');

            $targetSlot = $this->resolveTargetSlot($item, $equipment);
            $target = $equipment->get($targetSlot);
            if (! $target) {
                return 'Netinkamas equipment slotas.';
            }

            $displaced = [];
            if ($target->item) {
                $displaced[$target->slot] = $target->item;
            }

            $twoHanded = (bool) (($item->game_data ?? [])['two_handed'] ?? false);

            if ($targetSlot === 'weapon' && $twoHanded) {
                $shield = $equipment->get('shield');
                if ($shield?->item) {
                    $displaced['shield'] = $shield->item;
                }
            }

            if ($targetSlot === 'shield') {
                $weapon = $equipment->get('weapon');
                $weaponTwoHanded = (bool) (($weapon?->item?->game_data ?? [])['two_handed'] ?? false);
                if ($weapon?->item && $weaponTwoHanded) {
                    $displaced['weapon'] = $weapon->item;
                }
            }

            $sourceFreesSlot = $stack->quantity <= 1;
            $freeAfterSource = $this->inventory->freeSlots($player) + ($sourceFreesSlot ? 1 : 0);
            if ($freeAfterSource < count($displaced)) {
                return 'Inventoriuje nepakanka vietos nuimamai įrangai.';
            }

            if ($stack->quantity <= 1) {
                $stack->delete();
            } else {
                $stack->decrement('quantity');
            }

            foreach ($displaced as $equipmentSlot => $displacedItem) {
                $equipment->get($equipmentSlot)?->update(['item_id' => null]);

                if (! $this->inventory->add($player, $displacedItem, 1)) {
                    throw new \RuntimeException('Could not return displaced equipment to inventory.');
                }
            }

            if ($targetSlot === 'weapon' && $twoHanded) {
                $equipment->get('shield')?->update(['item_id' => null]);
            }

            if ($targetSlot === 'shield') {
                $weapon = $equipment->get('weapon');
                $weaponTwoHanded = (bool) (($weapon?->item?->game_data ?? [])['two_handed'] ?? false);
                if ($weaponTwoHanded) {
                    $weapon?->update(['item_id' => null]);
                }
            }

            $target->update(['item_id' => $item->id]);

            return "Ekipuota: {$item->name}.";
        });

        return back()->with('game', $message);
    }

    public function unequip(Request $request, string $slot): RedirectResponse
    {
        $player = $this->player($request);

        $message = DB::transaction(function () use ($player, $slot): string {
            $equipment = PlayerEquipment::query()
                ->with('item')
                ->where('player_id', $player->id)
                ->where('slot', $slot)
                ->lockForUpdate()
                ->first();

            if (! $equipment?->item) {
                return 'Šis equipment slotas tuščias.';
            }

            $item = $equipment->item;
            if (! $this->inventory->add($player, $item, 1)) {
                return 'Inventorius pilnas.';
            }

            $equipment->update(['item_id' => null]);

            return "Nuimta: {$item->name}.";
        });

        return back()->with('game', $message);
    }

    private function player(Request $request): Player
    {
        return Player::query()
            ->with(['skills', 'backpacks.item'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    private function resolveTargetSlot(Item $item, $equipment): string
    {
        $logicalSlot = $item->equip_slot;
        $candidates = match ($logicalSlot) {
            'ring' => ['ring', 'ring2'],
            'trinket' => ['trinket1', 'trinket2'],
            default => [$logicalSlot],
        };

        foreach ($candidates as $candidate) {
            if ($equipment->get($candidate) && ! $equipment->get($candidate)->item) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    private function meetsRequirement(Player $player, Item $item): bool
    {
        if (! $item->required_skill || ! $item->required_level) {
            return true;
        }

        $xp = (int) ($player->skills->firstWhere('skill', $item->required_skill)?->xp ?? 0);
        $startingLevel = $item->required_skill === 'hitpoints' ? 10 : 1;

        return $this->calculator->levelForXp($xp, $startingLevel) >= $item->required_level;
    }
}
