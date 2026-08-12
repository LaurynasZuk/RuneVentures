<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\Player;
use App\Models\PlayerBackpack;

class InventoryService
{
    public function capacity(Player $player): int
    {
        $player->loadMissing('backpacks.item');

        $bonus = $player->backpacks
            ->take($player->backpack_slots_unlocked)
            ->sum(fn (PlayerBackpack $slot) => (int) ($slot->item?->inventory_slots_bonus ?? 0));

        return (int) $player->inventory_base_slots + $bonus;
    }

    public function freeSlots(Player $player): int
    {
        $used = InventoryItem::query()
            ->where('player_id', $player->id)
            ->whereNotNull('slot')
            ->count();

        return max(0, $this->capacity($player) - $used);
    }

    public function add(Player $player, Item $item, int $amount = 1): bool
    {
        if ($amount <= 0) {
            return true;
        }

        $limit = $item->stackable ? $item->stack_limit : 1;
        $stacks = InventoryItem::query()
            ->where('player_id', $player->id)
            ->where('item_id', $item->id)
            ->orderBy('slot')
            ->lockForUpdate()
            ->get();

        $stackSpace = 0;
        if ($limit === null && $stacks->isNotEmpty()) {
            $stackSpace = $amount;
        } elseif ($limit !== null) {
            $stackSpace = $stacks->sum(fn (InventoryItem $stack) => max(0, $limit - $stack->quantity));
        }

        $remainingAfterStacks = max(0, $amount - $stackSpace);
        $slotsNeeded = $remainingAfterStacks === 0
            ? 0
            : ($limit === null ? 1 : (int) ceil($remainingAfterStacks / max(1, $limit)));

        if ($slotsNeeded > $this->freeSlots($player)) {
            return false;
        }

        $remaining = $amount;

        foreach ($stacks as $stack) {
            if ($remaining <= 0) {
                break;
            }

            if ($limit === null) {
                $stack->increment('quantity', $remaining);
                return true;
            }

            $space = max(0, $limit - $stack->quantity);
            if ($space === 0) {
                continue;
            }

            $toAdd = min($space, $remaining);
            $stack->increment('quantity', $toAdd);
            $remaining -= $toAdd;
        }

        while ($remaining > 0) {
            $slot = $this->firstFreeSlot($player);
            if ($slot === null) {
                return false;
            }

            $quantity = $limit === null ? $remaining : min($limit, $remaining);

            InventoryItem::create([
                'player_id' => $player->id,
                'slot' => $slot,
                'item_id' => $item->id,
                'quantity' => $quantity,
            ]);

            $remaining -= $quantity;
        }

        return true;
    }

    public function coins(Player $player): int
    {
        $coin = Item::where('slug', 'coins')->first();
        if (! $coin) {
            return 0;
        }

        return (int) InventoryItem::query()
            ->where('player_id', $player->id)
            ->where('item_id', $coin->id)
            ->sum('quantity');
    }

    public function addCoins(Player $player, int $amount): bool
    {
        if ($amount <= 0) {
            return true;
        }

        $coin = Item::updateOrCreate(
            ['slug' => 'coins'],
            [
                'name' => 'Coins',
                'icon' => 'coins',
                'category' => 'currency',
                'f2p' => true,
                'stackable' => true,
                'stack_limit' => null,
            ],
        );

        return $this->add($player, $coin, $amount);
    }

    public function removeCoins(Player $player, int $amount): bool
    {
        if ($amount <= 0) {
            return true;
        }

        $coin = Item::where('slug', 'coins')->first();
        if (! $coin) {
            return false;
        }

        $stacks = InventoryItem::query()
            ->where('player_id', $player->id)
            ->where('item_id', $coin->id)
            ->orderBy('slot')
            ->lockForUpdate()
            ->get();

        if ($stacks->sum('quantity') < $amount) {
            return false;
        }

        $remaining = $amount;
        foreach ($stacks as $stack) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, $stack->quantity);
            $remaining -= $take;

            if ($take === $stack->quantity) {
                $stack->delete();
            } else {
                $stack->decrement('quantity', $take);
            }
        }

        return true;
    }

    private function firstFreeSlot(Player $player): ?int
    {
        $capacity = $this->capacity($player);
        $used = InventoryItem::query()
            ->where('player_id', $player->id)
            ->whereNotNull('slot')
            ->pluck('slot')
            ->map(fn ($slot) => (int) $slot)
            ->all();

        for ($slot = 1; $slot <= $capacity; $slot++) {
            if (! in_array($slot, $used, true)) {
                return $slot;
            }
        }

        return null;
    }
}
