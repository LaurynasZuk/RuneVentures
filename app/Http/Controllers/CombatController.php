<?php

namespace App\Http\Controllers;

use App\Models\CombatEncounter;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\Player;
use App\Models\PlayerBackpack;
use App\Services\CombatCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CombatController extends Controller
{
    private const STYLE_DEFAULTS = [
        'melee' => [
            'attack_interval_ms' => 3000,
            'accuracy_skill' => 'attack',
            'damage_skill' => 'strength',
        ],
        'ranged' => [
            'attack_interval_ms' => 2000,
            'accuracy_skill' => 'ranged',
            'damage_skill' => 'ranged',
        ],
        'magic' => [
            'attack_interval_ms' => 4000,
            'accuracy_skill' => 'magic',
            'damage_skill' => 'magic',
        ],
    ];

    private const MONSTERS = [
        'chicken' => [
            'location' => 'starter-village',
            'name' => 'Chicken',
            'level' => 1,
            'hp' => 30,
            'attack_level' => 1,
            'strength_level' => 1,
            'defence_level' => 1,
            'attack_bonus' => 0,
            'strength_bonus' => 0,
            'defence_bonus' => -42,
            'max_hit' => 0,
            'attack_interval_ms' => 2400,
            'drop_table' => 'chicken',
        ],
        'goblin' => [
            'location' => 'starter-village',
            'name' => 'Goblin',
            'level' => 2,
            'hp' => 50,
            'attack_level' => 1,
            'strength_level' => 1,
            'defence_level' => 1,
            'attack_bonus' => 0,
            'strength_bonus' => 0,
            'defence_bonus' => -15,
            'max_hit' => 10,
            'attack_interval_ms' => 3600,
            'drop_table' => 'goblin',
        ],
        'duck' => [
            'location' => 'starter-village',
            'name' => 'Duck',
            'level' => 1,
            'hp' => 30,
            'attack_level' => 1,
            'strength_level' => 1,
            'defence_level' => 1,
            'attack_bonus' => 0,
            'strength_bonus' => 0,
            'defence_bonus' => -42,
            'max_hit' => 0,
            'attack_interval_ms' => 2400,
            'drop_table' => 'chicken',
        ],
        'giant-rat' => [
            'location' => 'starter-village',
            'name' => 'Didžioji žiurkė',
            'level' => 2,
            'hp' => 60,
            'attack_level' => 2,
            'strength_level' => 2,
            'defence_level' => 2,
            'attack_bonus' => 0,
            'strength_bonus' => 0,
            'defence_bonus' => 0,
            'attack_interval_ms' => 2400,
        ],
        'port-rat' => [
            'location' => 'port',
            'name' => 'Uosto žiurkė',
            'level' => 2,
            'hp' => 50,
            'attack_level' => 2,
            'strength_level' => 2,
            'defence_level' => 1,
            'attack_bonus' => 0,
            'strength_bonus' => 0,
            'defence_bonus' => 0,
            'attack_interval_ms' => 1800,
        ],
        'plains-wolf' => [
            'location' => 'north-east-plains',
            'name' => 'Lygumų vilkas',
            'level' => 3,
            'hp' => 100,
            'attack_level' => 3,
            'strength_level' => 3,
            'defence_level' => 3,
            'attack_bonus' => 0,
            'strength_bonus' => 0,
            'defence_bonus' => 0,
            'attack_interval_ms' => 2400,
        ],
        'swamp-rat' => [
            'location' => 'south-west-swamp',
            'name' => 'Pelkės žiurkė',
            'level' => 3,
            'hp' => 80,
            'attack_level' => 3,
            'strength_level' => 3,
            'defence_level' => 2,
            'attack_bonus' => 0,
            'strength_bonus' => 0,
            'defence_bonus' => 0,
            'attack_interval_ms' => 3000,
        ],
    ];

    private const DROP_TABLES = [
        'chicken' => [
            'always' => [
                ['slug' => 'bones', 'name' => 'Bones', 'icon' => 'bones', 'stack_limit' => 20, 'quantity' => 1],
                ['slug' => 'raw-chicken', 'name' => 'Raw chicken', 'icon' => 'raw-chicken', 'stack_limit' => 1, 'quantity' => 1],
            ],
            'weighted' => [
                ['weight' => 64, 'slug' => 'feather', 'name' => 'Feather', 'icon' => 'feather', 'stack_limit' => null, 'quantity' => 5],
                ['weight' => 32, 'slug' => 'feather', 'name' => 'Feather', 'icon' => 'feather', 'stack_limit' => null, 'quantity' => 15],
                ['weight' => 32],
            ],
            'tertiary' => [
                ['one_in' => 300, 'slug' => 'clue-scroll-beginner', 'name' => 'Clue scroll (beginner)', 'icon' => 'scroll', 'stack_limit' => 1, 'quantity' => 1],
            ],
        ],
        'goblin' => [
            'always' => [
                ['slug' => 'bones', 'name' => 'Bones', 'icon' => 'bones', 'stack_limit' => 20, 'quantity' => 1],
            ],
            'weighted' => [
                ['weight' => 3, 'slug' => 'bronze-sq-shield', 'name' => 'Bronze sq shield', 'icon' => 'shield', 'stack_limit' => 1, 'quantity' => 1],
                ['weight' => 4, 'slug' => 'bronze-spear', 'name' => 'Bronze spear', 'icon' => 'spear', 'stack_limit' => 1, 'quantity' => 1],
                ['weight' => 5, 'slug' => 'body-rune', 'name' => 'Body rune', 'icon' => 'rune', 'stack_limit' => null, 'quantity' => 7],
                ['weight' => 6, 'slug' => 'water-rune', 'name' => 'Water rune', 'icon' => 'rune', 'stack_limit' => null, 'quantity' => 6],
                ['weight' => 3, 'slug' => 'earth-rune', 'name' => 'Earth rune', 'icon' => 'rune', 'stack_limit' => null, 'quantity' => 4],
                ['weight' => 3, 'slug' => 'bronze-bolts', 'name' => 'Bronze bolts', 'icon' => 'ammo', 'stack_limit' => null, 'quantity' => 8],
                ['weight' => 28, 'slug' => 'coins', 'name' => 'Coins', 'icon' => 'coins', 'stack_limit' => null, 'quantity' => 5],
                ['weight' => 3, 'slug' => 'coins', 'name' => 'Coins', 'icon' => 'coins', 'stack_limit' => null, 'quantity' => 9],
                ['weight' => 3, 'slug' => 'coins', 'name' => 'Coins', 'icon' => 'coins', 'stack_limit' => null, 'quantity' => 15],
                ['weight' => 2, 'slug' => 'coins', 'name' => 'Coins', 'icon' => 'coins', 'stack_limit' => null, 'quantity' => 20],
                ['weight' => 1, 'slug' => 'coins', 'name' => 'Coins', 'icon' => 'coins', 'stack_limit' => null, 'quantity' => 1],
                ['weight' => 15, 'slug' => 'hammer', 'name' => 'Hammer', 'icon' => 'hammer', 'stack_limit' => 1, 'quantity' => 1],
                ['weight' => 2, 'slug' => 'goblin-book', 'name' => 'Goblin book', 'icon' => 'book', 'stack_limit' => 1, 'quantity' => 1],
                ['weight' => 5, 'slug' => 'goblin-mail', 'name' => 'Goblin mail', 'icon' => 'armour', 'stack_limit' => 1, 'quantity' => 1],
                ['weight' => 3, 'slug' => 'chefs-hat', 'name' => "Chef's hat", 'icon' => 'hat', 'stack_limit' => 1, 'quantity' => 1],
                ['weight' => 2, 'slug' => 'beer', 'name' => 'Beer', 'icon' => 'drink', 'stack_limit' => 1, 'quantity' => 1],
                ['weight' => 1, 'slug' => 'brass-necklace', 'name' => 'Brass necklace', 'icon' => 'necklace', 'stack_limit' => 1, 'quantity' => 1],
                ['weight' => 1, 'slug' => 'air-talisman', 'name' => 'Air talisman', 'icon' => 'talisman', 'stack_limit' => 1, 'quantity' => 1],
                ['weight' => 38],
            ],
            'tertiary' => [
                ['one_in' => 35, 'slug' => 'ensouled-goblin-head', 'name' => 'Ensouled goblin head', 'icon' => 'skull', 'stack_limit' => 1, 'quantity' => 1],
                ['one_in' => 64, 'slug' => 'clue-scroll-beginner', 'name' => 'Clue scroll (beginner)', 'icon' => 'scroll', 'stack_limit' => 1, 'quantity' => 1],
                ['one_in' => 128, 'slug' => 'clue-scroll-easy', 'name' => 'Clue scroll (easy)', 'icon' => 'scroll', 'stack_limit' => 1, 'quantity' => 1],
                ['one_in' => 5000, 'slug' => 'goblin-champion-scroll', 'name' => 'Goblin champion scroll', 'icon' => 'scroll', 'stack_limit' => 1, 'quantity' => 1],
            ],
        ],
    ];

    public function __construct(private readonly CombatCalculator $calculator)
    {
    }

    public function start(Request $request, string $monster): RedirectResponse
    {
        $player = Player::query()
            ->with(['location', 'skills', 'equipment.item'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $monsterData = self::MONSTERS[$monster] ?? null;
        abort_unless($monsterData && $monsterData['location'] === $player->location->slug, 404);

        $weapon = $player->equipment->firstWhere('slot', 'weapon')?->item;
        $style = in_array($weapon?->combat_style, ['melee', 'ranged', 'magic'], true)
            ? $weapon->combat_style
            : 'melee';
        $defaults = self::STYLE_DEFAULTS[$style];
        $equipment = $this->equipmentBonuses($player);

        $attackLevel = $this->skillLevel($player, $defaults['accuracy_skill']);
        $damageLevel = $this->skillLevel($player, $defaults['damage_skill']);
        $defenceLevel = $this->skillLevel($player, 'defence');

        $playerAttackRoll = $this->calculator->attackRoll($attackLevel, $equipment['attack']);
        $playerDefenceRoll = $this->calculator->defenceRoll($defenceLevel, $equipment['defence']);
        $playerMaxHit = $this->calculator->maxHit($damageLevel, $equipment['strength']);
        $playerAttackIntervalMs = max(
            250,
            (int) ($weapon?->attack_interval_ms ?? $defaults['attack_interval_ms']),
        );

        $monsterAttackRoll = $this->calculator->attackRoll(
            $monsterData['attack_level'],
            $monsterData['attack_bonus'],
        );
        $monsterDefenceRoll = $this->calculator->defenceRoll(
            $monsterData['defence_level'],
            $monsterData['defence_bonus'],
        );
        $monsterMaxHit = array_key_exists('max_hit', $monsterData)
            ? (int) $monsterData['max_hit']
            : $this->calculator->maxHit(
                $monsterData['strength_level'],
                $monsterData['strength_bonus'],
            );
        $monsterAttackIntervalMs = max(250, (int) $monsterData['attack_interval_ms']);
        $nowMs = $this->nowMs();

        CombatEncounter::updateOrCreate(
            ['player_id' => $player->id],
            [
                'location_id' => $player->location_id,
                'monster_slug' => $monster,
                'monster_name' => $monsterData['name'],
                'monster_level' => $monsterData['level'],
                'monster_hp' => $monsterData['hp'],
                'monster_max_hp' => $monsterData['hp'],
                'monster_max_hit' => $monsterMaxHit,
                'player_style' => $style,
                'player_max_hit' => $playerMaxHit,
                'player_attack_interval_ms' => $playerAttackIntervalMs,
                'monster_attack_interval_ms' => $monsterAttackIntervalMs,
                'player_attack_roll' => $playerAttackRoll,
                'player_defence_roll' => $playerDefenceRoll,
                'monster_attack_roll' => $monsterAttackRoll,
                'monster_defence_roll' => $monsterDefenceRoll,
                'player_next_attack_ms' => $nowMs,
                'monster_next_attack_ms' => $nowMs + $monsterAttackIntervalMs,
                'status' => 'active',
                'last_event' => "Kova su {$monsterData['name']} prasidėjo.",
            ],
        );

        return redirect()->route('game.combat');
    }

    public function show(Request $request): Response
    {
        $player = $this->player($request);
        $encounter = CombatEncounter::where('player_id', $player->id)->first();

        if ($encounter) {
            $encounter = $this->process($player, $encounter);
            $player->refresh();
        }

        return Inertia::render('combat', [
            'combat' => $encounter ? $this->payload($player, $encounter) : null,
        ]);
    }

    public function state(Request $request): JsonResponse
    {
        $player = $this->player($request);
        $encounter = CombatEncounter::where('player_id', $player->id)->first();

        if (! $encounter) {
            return response()->json(['combat' => null]);
        }

        $encounter = $this->process($player, $encounter);
        $player->refresh();

        return response()->json([
            'combat' => $this->payload($player, $encounter),
        ]);
    }

    public function leave(Request $request): RedirectResponse
    {
        $player = $this->player($request);

        CombatEncounter::query()
            ->where('player_id', $player->id)
            ->where('status', 'active')
            ->update([
                'status' => 'fled',
                'player_next_attack_ms' => null,
                'monster_next_attack_ms' => null,
                'last_event' => 'Pasitraukei iš kovos.',
            ]);

        return redirect()->route('main');
    }

    private function player(Request $request): Player
    {
        return Player::query()
            ->with('location')
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    private function process(Player $player, CombatEncounter $encounter): CombatEncounter
    {
        if ($encounter->status !== 'active') {
            return $encounter;
        }

        return DB::transaction(function () use ($player, $encounter) {
            $lockedPlayer = Player::query()->whereKey($player->id)->lockForUpdate()->firstOrFail();
            $locked = CombatEncounter::query()->whereKey($encounter->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'active') {
                return $locked;
            }

            $nowMs = $this->nowMs();

            if ($locked->player_next_attack_ms === null) {
                $locked->player_next_attack_ms = $nowMs;
            }

            if ($locked->monster_next_attack_ms === null) {
                $locked->monster_next_attack_ms = $nowMs + $locked->monster_attack_interval_ms;
            }

            $iterations = 0;

            while ($locked->status === 'active' && $iterations < 100) {
                $playerAt = $locked->player_next_attack_ms;
                $monsterAt = $locked->monster_next_attack_ms;

                $playerDue = $playerAt !== null && $playerAt <= $nowMs;
                $monsterDue = $monsterAt !== null && $monsterAt <= $nowMs;

                if (! $playerDue && ! $monsterDue) {
                    break;
                }

                $playerActsFirst = $playerDue && (
                    ! $monsterDue || $playerAt <= $monsterAt
                );

                if ($playerActsFirst) {
                    $hit = $this->calculator->rollHit(
                        $locked->player_attack_roll,
                        $locked->monster_defence_roll,
                    );
                    $damage = $hit
                        ? $this->calculator->rollDamage($locked->player_max_hit)
                        : 0;

                    $locked->monster_hp = max(0, $locked->monster_hp - $damage);
                    $locked->last_event = $hit
                        ? "Pataikei {$damage}."
                        : 'Nepataikei.';
                    $locked->player_next_attack_ms = $playerAt + $locked->player_attack_interval_ms;

                    if ($locked->monster_hp <= 0) {
                        $locked->status = 'won';
                        $locked->player_next_attack_ms = null;
                        $locked->monster_next_attack_ms = null;

                        $loot = $this->awardMonsterDrops($lockedPlayer, $locked->monster_slug);
                        $lootText = $this->formatLoot($loot['received']);
                        $lostText = $this->formatLoot($loot['lost']);

                        $locked->last_event = "Nugalėjai {$locked->monster_name}.";

                        if ($lootText !== '') {
                            $locked->last_event .= " Laimikis: {$lootText}.";
                        }

                        if ($lostText !== '') {
                            $locked->last_event .= " Netilpo į inventorių: {$lostText}.";
                        }
                    }
                } else {
                    $hit = $this->calculator->rollHit(
                        $locked->monster_attack_roll,
                        $locked->player_defence_roll,
                    );
                    $damage = $hit
                        ? $this->calculator->rollDamage($locked->monster_max_hit)
                        : 0;
                    $remainingHp = $lockedPlayer->hitpoints - $damage;

                    if ($remainingHp <= 0) {
                        $lockedPlayer->hitpoints = 10;
                        $locked->status = 'lost';
                        $locked->player_next_attack_ms = null;
                        $locked->monster_next_attack_ms = null;
                        $locked->last_event = "{$locked->monster_name} tave nugalėjo.";
                    } else {
                        $lockedPlayer->hitpoints = $remainingHp;
                        $locked->last_event = $hit
                            ? "{$locked->monster_name} pataikė {$damage}."
                            : "{$locked->monster_name} nepataikė.";
                        $locked->monster_next_attack_ms = $monsterAt + $locked->monster_attack_interval_ms;
                    }

                    $lockedPlayer->save();
                }

                $locked->save();
                $iterations++;
            }

            return $locked->fresh();
        });
    }

    private function payload(Player $player, CombatEncounter $encounter): array
    {
        return [
            'status' => $encounter->status,
            'serverNowMs' => $this->nowMs(),
            'damageScale' => 10,
            'style' => $encounter->player_style,
            'player' => [
                'hp' => $player->hitpoints,
                'maxHp' => $player->max_hitpoints,
                'attackIntervalMs' => $encounter->player_attack_interval_ms,
                'attackSeconds' => $encounter->player_attack_interval_ms / 1000,
                'maxHit' => $encounter->player_max_hit,
                'attackRoll' => $encounter->player_attack_roll,
                'defenceRoll' => $encounter->player_defence_roll,
                'hitChance' => $this->calculator->hitChance(
                    $encounter->player_attack_roll,
                    $encounter->monster_defence_roll,
                ),
                'nextAttackAtMs' => $encounter->player_next_attack_ms,
            ],
            'monster' => [
                'slug' => $encounter->monster_slug,
                'name' => $encounter->monster_name,
                'level' => $encounter->monster_level,
                'hp' => $encounter->monster_hp,
                'maxHp' => $encounter->monster_max_hp,
                'attackIntervalMs' => $encounter->monster_attack_interval_ms,
                'attackSeconds' => $encounter->monster_attack_interval_ms / 1000,
                'maxHit' => $encounter->monster_max_hit,
                'attackRoll' => $encounter->monster_attack_roll,
                'defenceRoll' => $encounter->monster_defence_roll,
                'hitChance' => $this->calculator->hitChance(
                    $encounter->monster_attack_roll,
                    $encounter->player_defence_roll,
                ),
                'nextAttackAtMs' => $encounter->monster_next_attack_ms,
            ],
            'lastEvent' => $encounter->last_event,
        ];
    }

    private function awardMonsterDrops(Player $player, string $monsterSlug): array
    {
        $dropTableKey = self::MONSTERS[$monsterSlug]['drop_table'] ?? null;
        $table = $dropTableKey ? (self::DROP_TABLES[$dropTableKey] ?? null) : null;

        if (! $table) {
            return ['received' => [], 'lost' => []];
        }

        $received = [];
        $lost = [];

        foreach ($table['always'] ?? [] as $drop) {
            $this->awardDrop($player, $drop, $received, $lost);
        }

        $weighted = $table['weighted'] ?? [];
        if ($weighted !== []) {
            $totalWeight = array_sum(array_column($weighted, 'weight'));
            $roll = random_int(1, max(1, $totalWeight));
            $cursor = 0;

            foreach ($weighted as $drop) {
                $cursor += (int) $drop['weight'];

                if ($roll <= $cursor) {
                    if (isset($drop['slug'])) {
                        $this->awardDrop($player, $drop, $received, $lost);
                    }
                    break;
                }
            }
        }

        foreach ($table['tertiary'] ?? [] as $drop) {
            if (random_int(1, (int) $drop['one_in']) === 1) {
                $this->awardDrop($player, $drop, $received, $lost);
            }
        }

        return ['received' => $received, 'lost' => $lost];
    }

    private function awardDrop(Player $player, array $drop, array &$received, array &$lost): void
    {
        $quantity = (int) ($drop['quantity'] ?? 1);
        $entry = ['name' => $drop['name'], 'quantity' => $quantity];

        $added = $this->grantItem(
            $player,
            $drop['slug'],
            $drop['name'],
            $drop['icon'] ?? 'package',
            array_key_exists('stack_limit', $drop) ? $drop['stack_limit'] : 1,
            $quantity,
        );

        if ($added) {
            $received[] = $entry;
        } else {
            $lost[] = $entry;
        }
    }

    private function formatLoot(array $loot): string
    {
        return collect($loot)
            ->map(fn (array $drop) => $drop['quantity'] > 1
                ? "{$drop['quantity']}× {$drop['name']}"
                : $drop['name'])
            ->implode(', ');
    }

    private function grantItem(
        Player $player,
        string $slug,
        string $name,
        string $icon,
        ?int $stackLimit = null,
        int $amount = 1,
    ): bool {
        $item = Item::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'icon' => $icon,
                'stackable' => $stackLimit !== 1,
                'stack_limit' => $stackLimit,
            ],
        );

        if (
            $item->name !== $name
            || $item->icon !== $icon
            || $item->stack_limit !== $stackLimit
            || $item->stackable !== ($stackLimit !== 1)
        ) {
            $item->update([
                'name' => $name,
                'icon' => $icon,
                'stackable' => $stackLimit !== 1,
                'stack_limit' => $stackLimit,
            ]);
        }

        $remaining = $amount;
        $limit = $item->stackable ? $item->stack_limit : 1;
        $capacity = $this->inventoryCapacity($player);

        $existingStacks = InventoryItem::query()
            ->where('player_id', $player->id)
            ->where('item_id', $item->id)
            ->orderBy('slot')
            ->lockForUpdate()
            ->get();

        foreach ($existingStacks as $stack) {
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
            $slot = $this->firstFreeInventorySlot($player->id, $capacity);
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

    private function inventoryCapacity(Player $player): int
    {
        $player->loadMissing('backpacks.item');

        $bonus = $player->backpacks
            ->take($player->backpack_slots_unlocked)
            ->sum(fn (PlayerBackpack $slot) => (int) ($slot->item?->inventory_slots_bonus ?? 0));

        return (int) $player->inventory_base_slots + $bonus;
    }

    private function firstFreeInventorySlot(int $playerId, int $capacity): ?int
    {
        $used = InventoryItem::query()
            ->where('player_id', $playerId)
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

    private function equipmentBonuses(Player $player): array
    {
        return [
            'attack' => $player->equipment->sum(fn ($slot) => (int) ($slot->item?->attack_bonus ?? 0)),
            'strength' => $player->equipment->sum(fn ($slot) => (int) ($slot->item?->strength_bonus ?? 0)),
            'defence' => $player->equipment->sum(fn ($slot) => (int) ($slot->item?->defence_bonus ?? 0)),
        ];
    }

    private function skillLevel(Player $player, string $skill): int
    {
        $row = $player->skills->firstWhere('skill', $skill);

        return $this->calculator->levelForXp((int) ($row?->xp ?? 0));
    }

    private function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
