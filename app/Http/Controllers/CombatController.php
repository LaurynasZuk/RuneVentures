<?php

namespace App\Http\Controllers;

use App\Models\CombatEncounter;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\Player;
use App\Models\PlayerBackpack;
use App\Models\PlayerSkill;
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
        ],
        'goblin' => [
            'always' => [
                ['slug' => 'bones', 'name' => 'Bones', 'icon' => 'bones', 'stack_limit' => 20, 'quantity' => 1],
            ],
            'weighted' => [
                ['weight' => 3, 'slug' => 'bronze-sq-shield', 'name' => 'Bronze sq shield', 'icon' => 'shield', 'stack_limit' => 1, 'quantity' => 1],
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
                ['weight' => 5, 'slug' => 'goblin-mail', 'name' => 'Goblin mail', 'icon' => 'armour', 'stack_limit' => 1, 'quantity' => 1],
                ['weight' => 3, 'slug' => 'chefs-hat', 'name' => "Chef's hat", 'icon' => 'hat', 'stack_limit' => 1, 'quantity' => 1],
                ['weight' => 2, 'slug' => 'beer', 'name' => 'Beer', 'icon' => 'drink', 'stack_limit' => 1, 'quantity' => 1],
                ['weight' => 1, 'slug' => 'brass-necklace', 'name' => 'Brass necklace', 'icon' => 'necklace', 'stack_limit' => 1, 'quantity' => 1],
                ['weight' => 1, 'slug' => 'air-talisman', 'name' => 'Air talisman', 'icon' => 'talisman', 'stack_limit' => 1, 'quantity' => 1],
                ['weight' => 44],
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
                'monster_attack_interval_ms' => max(250, (int) $monsterData['attack_interval_ms']),
                'player_attack_roll' => $playerAttackRoll,
                'player_defence_roll' => $playerDefenceRoll,
                'monster_attack_roll' => $monsterAttackRoll,
                'monster_defence_roll' => $monsterDefenceRoll,
                'player_next_attack_ms' => null,
                'monster_next_attack_ms' => null,
                'status' => 'ready',
                'last_event' => 'Paspausk Hit, kad pradėtum kovą.',
                'combat_log' => [],
                'loot' => ['received' => [], 'lost' => []],
                'ended_at_ms' => null,
            ],
        );

        return redirect()->route('game.combat');
    }

    public function hit(Request $request): RedirectResponse
    {
        $player = $this->player($request);

        DB::transaction(function () use ($player): void {
            $encounter = CombatEncounter::query()
                ->where('player_id', $player->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($encounter->status !== 'ready') {
                return;
            }

            $nowMs = $this->nowMs();
            $encounter->status = 'active';
            $encounter->player_next_attack_ms = $nowMs + $encounter->player_attack_interval_ms;
            $encounter->monster_next_attack_ms = $nowMs + $encounter->monster_attack_interval_ms;
            $encounter->last_event = 'Kova prasidėjo.';
            $encounter->combat_log = [];
            $encounter->loot = ['received' => [], 'lost' => []];
            $encounter->ended_at_ms = null;
            $encounter->save();
        });

        return redirect()->route('game.combat');
    }

    public function show(Request $request): Response
    {
        $player = $this->player($request);
        $encounter = CombatEncounter::where('player_id', $player->id)->first();

        if ($encounter) {
            $encounter = $this->process($player, $encounter);
            $player->refresh()->load('skills');
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
        $player->refresh()->load('skills');

        return response()->json([
            'combat' => $this->payload($player, $encounter),
        ]);
    }

    public function leave(Request $request): RedirectResponse
    {
        $player = $this->player($request);
        $nowMs = $this->nowMs();

        CombatEncounter::query()
            ->where('player_id', $player->id)
            ->whereIn('status', ['ready', 'active'])
            ->update([
                'status' => 'fled',
                'player_next_attack_ms' => null,
                'monster_next_attack_ms' => null,
                'ended_at_ms' => $nowMs,
                'last_event' => 'Pasitraukei iš kovos.',
            ]);

        return redirect()->route('main');
    }

    private function player(Request $request): Player
    {
        return Player::query()
            ->with(['location', 'skills'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    private function process(Player $player, CombatEncounter $encounter): CombatEncounter
    {
        if ($encounter->status !== 'active') {
            return $encounter;
        }

        return DB::transaction(function () use ($player, $encounter) {
            $lockedPlayer = Player::query()
                ->with('skills')
                ->whereKey($player->id)
                ->lockForUpdate()
                ->firstOrFail();
            $locked = CombatEncounter::query()
                ->whereKey($encounter->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'active') {
                return $locked;
            }

            $nowMs = $this->nowMs();
            $iterations = 0;

            while ($locked->status === 'active' && $iterations < 100) {
                $playerAt = $locked->player_next_attack_ms;
                $monsterAt = $locked->monster_next_attack_ms;
                $playerDue = $playerAt !== null && $playerAt <= $nowMs;
                $monsterDue = $monsterAt !== null && $monsterAt <= $nowMs;

                if (! $playerDue && ! $monsterDue) {
                    break;
                }

                $playerActsFirst = $playerDue && (! $monsterDue || $playerAt <= $monsterAt);

                if ($playerActsFirst) {
                    $hit = $this->calculator->rollHit(
                        $locked->player_attack_roll,
                        $locked->monster_defence_roll,
                    );
                    $damage = $hit
                        ? $this->calculator->rollDamage($locked->player_max_hit)
                        : 0;
                    $xp = $damage > 0
                        ? $this->awardCombatXp($lockedPlayer, $locked->player_style, $damage)
                        : [];

                    $locked->monster_hp = max(0, $locked->monster_hp - $damage);
                    $locked->last_event = $hit
                        ? "{$lockedPlayer->name} padarė {$damage} žalos."
                        : "{$lockedPlayer->name} nepataikė.";
                    $locked->player_next_attack_ms = $playerAt + $locked->player_attack_interval_ms;
                    $this->appendCombatLog($locked, 'player', $damage, $hit, $xp, $playerAt);

                    if ($locked->monster_hp <= 0) {
                        $locked->status = 'won';
                        $locked->player_next_attack_ms = null;
                        $locked->monster_next_attack_ms = null;
                        $locked->ended_at_ms = $nowMs;
                        $locked->loot = $this->awardMonsterDrops($lockedPlayer, $locked->monster_slug);
                        $locked->last_event = "{$locked->monster_name} nugalėtas.";
                    }
                } else {
                    $hit = $this->calculator->rollHit(
                        $locked->monster_attack_roll,
                        $locked->player_defence_roll,
                    );
                    $damage = $hit
                        ? $this->calculator->rollDamage($locked->monster_max_hit)
                        : 0;
                    $remainingHp = max(0, $lockedPlayer->hitpoints - $damage);

                    $lockedPlayer->hitpoints = $remainingHp;
                    $lockedPlayer->save();
                    $locked->last_event = $hit
                        ? "{$locked->monster_name} padarė {$damage} žalos."
                        : "{$locked->monster_name} nepataikė.";
                    $locked->monster_next_attack_ms = $monsterAt + $locked->monster_attack_interval_ms;
                    $this->appendCombatLog($locked, 'monster', $damage, $hit, [], $monsterAt);

                    if ($remainingHp <= 0) {
                        $locked->status = 'lost';
                        $locked->player_next_attack_ms = null;
                        $locked->monster_next_attack_ms = null;
                        $locked->ended_at_ms = $nowMs;
                        $locked->loot = ['received' => [], 'lost' => []];
                        $locked->last_event = "{$lockedPlayer->name} nugalėtas.";
                    }
                }

                $locked->save();
                $iterations++;
            }

            return $locked->fresh();
        });
    }

    private function payload(Player $player, CombatEncounter $encounter): array
    {
        $prayerLevel = $this->skillLevel($player, 'prayer');

        return [
            'status' => $encounter->status,
            'serverNowMs' => $this->nowMs(),
            'resultReadyAtMs' => $encounter->ended_at_ms !== null
                ? $encounter->ended_at_ms + 2000
                : null,
            'damageScale' => 10,
            'style' => $encounter->player_style,
            'player' => [
                'name' => $player->name,
                'level' => $this->combatLevel($player),
                'hp' => $player->hitpoints,
                'maxHp' => $player->max_hitpoints,
                'mana' => $player->prayer_mana,
                'maxMana' => max(100, $prayerLevel * 100),
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
            'combatLog' => array_values($encounter->combat_log ?? []),
            'loot' => $encounter->loot ?? ['received' => [], 'lost' => []],
            'lastEvent' => $encounter->last_event,
        ];
    }

    private function appendCombatLog(
        CombatEncounter $encounter,
        string $actor,
        int $damage,
        bool $hit,
        array $xp,
        int $atMs,
    ): void {
        $log = $encounter->combat_log ?? [];
        $last = $log === [] ? null : $log[array_key_last($log)];

        $log[] = [
            'id' => ((int) ($last['id'] ?? 0)) + 1,
            'actor' => $actor,
            'damage' => $damage,
            'hit' => $hit,
            'xp' => $xp,
            'atMs' => $atMs,
        ];

        $encounter->combat_log = array_slice($log, -60);
    }

    private function awardCombatXp(Player $player, string $style, int $damage): array
    {
        if ($damage <= 0) {
            return [];
        }

        $awards = match ($style) {
            'ranged' => [
                ['skill' => 'ranged', 'amount' => max(1, (int) floor($damage * 0.4))],
                ['skill' => 'hitpoints', 'amount' => max(1, (int) floor($damage * 0.133))],
            ],
            'magic' => [
                ['skill' => 'magic', 'amount' => max(1, (int) floor($damage * 0.4))],
                ['skill' => 'hitpoints', 'amount' => max(1, (int) floor($damage * 0.133))],
            ],
            default => [
                ['skill' => 'attack', 'amount' => max(1, (int) floor($damage * 0.2))],
                ['skill' => 'strength', 'amount' => max(1, (int) floor($damage * 0.2))],
                ['skill' => 'hitpoints', 'amount' => max(1, (int) floor($damage * 0.133))],
            ],
        };

        foreach ($awards as $award) {
            $this->grantSkillXp($player->id, $award['skill'], $award['amount']);
        }

        return $awards;
    }

    private function grantSkillXp(int $playerId, string $skill, int $amount): void
    {
        $row = PlayerSkill::query()
            ->where('player_id', $playerId)
            ->where('skill', $skill)
            ->lockForUpdate()
            ->first();

        if (! $row) {
            $row = PlayerSkill::create([
                'player_id' => $playerId,
                'skill' => $skill,
                'xp' => 0,
            ]);
        }

        $row->increment('xp', $amount);
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

        return ['received' => $received, 'lost' => $lost];
    }

    private function awardDrop(Player $player, array $drop, array &$received, array &$lost): void
    {
        $quantity = (int) ($drop['quantity'] ?? 1);
        $entry = [
            'slug' => $drop['slug'],
            'name' => $drop['name'],
            'quantity' => $quantity,
            'icon' => $drop['icon'] ?? 'package',
        ];

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
            ->sum(fn ($slot) => (int) ($slot->item?->inventory_slots_bonus ?? 0));

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
        $player->loadMissing('equipment.item');

        return [
            'attack' => $player->equipment->sum(fn ($slot) => (int) ($slot->item?->attack_bonus ?? 0)),
            'strength' => $player->equipment->sum(fn ($slot) => (int) ($slot->item?->strength_bonus ?? 0)),
            'defence' => $player->equipment->sum(fn ($slot) => (int) ($slot->item?->defence_bonus ?? 0)),
        ];
    }

    private function skillLevel(Player $player, string $skill): int
    {
        $player->loadMissing('skills');
        $row = $player->skills->firstWhere('skill', $skill);

        return $this->calculator->levelForXp(
            (int) ($row?->xp ?? 0),
            $skill === 'hitpoints' ? 10 : 1,
        );
    }

    private function combatLevel(Player $player): int
    {
        $player->loadMissing('skills');
        $skills = $player->skills->keyBy('skill');
        $level = fn (string $name, int $default = 1) => $this->calculator->levelForXp(
            (int) ($skills->get($name)?->xp ?? 0),
            $default,
        );

        $base = 0.25 * ($level('defence') + $level('hitpoints', 10) + floor($level('prayer') / 2));
        $melee = 0.325 * ($level('attack') + $level('strength'));
        $ranged = 0.325 * floor($level('ranged') * 1.5);
        $magic = 0.325 * floor($level('magic') * 1.5);

        return max(3, (int) floor($base + max($melee, $ranged, $magic)));
    }

    private function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
