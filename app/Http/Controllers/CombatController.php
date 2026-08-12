<?php

namespace App\Http\Controllers;

use App\Models\CombatEncounter;
use App\Models\Player;
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
        $monsterMaxHit = $this->calculator->maxHit(
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

        return redirect()->route('dashboard');
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
                        $locked->last_event = "Nugalėjai {$locked->monster_name}.";
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
                        // Full death/respawn rules will replace this temporary floor.
                        // 10 HP on the x10 scale equals the previous 1 HP safety floor.
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
