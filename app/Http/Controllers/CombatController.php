<?php

namespace App\Http\Controllers;

use App\Models\CombatEncounter;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CombatController extends Controller
{
    private const TICK_MS = 600;

    private const STYLE_DEFAULTS = [
        'melee' => ['attack_ticks' => 5, 'max_hit' => 2],
        'ranged' => ['attack_ticks' => 3, 'max_hit' => 1],
        'magic' => ['attack_ticks' => 7, 'max_hit' => 3],
    ];

    private const MONSTERS = [
        'giant-rat' => [
            'location' => 'starter-village',
            'name' => 'Didžioji žiurkė',
            'level' => 2,
            'hp' => 6,
            'attack_ticks' => 4,
            'max_hit' => 1,
        ],
        'port-rat' => [
            'location' => 'port',
            'name' => 'Uosto žiurkė',
            'level' => 2,
            'hp' => 5,
            'attack_ticks' => 3,
            'max_hit' => 1,
        ],
        'plains-wolf' => [
            'location' => 'north-east-plains',
            'name' => 'Lygumų vilkas',
            'level' => 3,
            'hp' => 10,
            'attack_ticks' => 4,
            'max_hit' => 2,
        ],
        'swamp-rat' => [
            'location' => 'south-west-swamp',
            'name' => 'Pelkės žiurkė',
            'level' => 3,
            'hp' => 8,
            'attack_ticks' => 5,
            'max_hit' => 2,
        ],
    ];

    public function start(Request $request, string $monster): RedirectResponse
    {
        $player = Player::query()
            ->with(['location', 'equipment.item'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $monsterData = self::MONSTERS[$monster] ?? null;
        abort_unless($monsterData && $monsterData['location'] === $player->location->slug, 404);

        $weapon = $player->equipment->firstWhere('slot', 'weapon')?->item;
        $style = in_array($weapon?->combat_style, ['melee', 'ranged', 'magic'], true)
            ? $weapon->combat_style
            : 'melee';
        $defaults = self::STYLE_DEFAULTS[$style];
        $playerAttackTicks = max(1, (int) ($weapon?->attack_ticks ?? $defaults['attack_ticks']));
        $playerMaxHit = max(1, (int) ($weapon?->max_hit ?? $defaults['max_hit']));
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
                'monster_attack_ticks' => $monsterData['attack_ticks'],
                'monster_max_hit' => $monsterData['max_hit'],
                'player_style' => $style,
                'player_attack_ticks' => $playerAttackTicks,
                'player_max_hit' => $playerMaxHit,
                'player_next_attack_at' => null,
                'monster_next_attack_at' => null,
                'player_next_attack_ms' => $nowMs,
                'monster_next_attack_ms' => $nowMs + ($monsterData['attack_ticks'] * self::TICK_MS),
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
                'player_next_attack_at' => null,
                'monster_next_attack_at' => null,
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
                $locked->monster_next_attack_ms = $nowMs + ($locked->monster_attack_ticks * self::TICK_MS);
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
                    $damage = random_int(0, $locked->player_max_hit);
                    $locked->monster_hp = max(0, $locked->monster_hp - $damage);
                    $locked->last_event = $damage > 0
                        ? "Pataikei {$damage}."
                        : 'Nepataikei.';
                    $locked->player_next_attack_ms = $playerAt + (
                        $locked->player_attack_ticks * self::TICK_MS
                    );

                    if ($locked->monster_hp <= 0) {
                        $locked->status = 'won';
                        $locked->player_next_attack_ms = null;
                        $locked->monster_next_attack_ms = null;
                        $locked->last_event = "Nugalėjai {$locked->monster_name}.";
                    }
                } else {
                    $damage = random_int(0, $locked->monster_max_hit);
                    $remainingHp = $lockedPlayer->hitpoints - $damage;

                    if ($remainingHp <= 0) {
                        $lockedPlayer->hitpoints = 1;
                        $locked->status = 'lost';
                        $locked->player_next_attack_ms = null;
                        $locked->monster_next_attack_ms = null;
                        $locked->last_event = "{$locked->monster_name} tave nugalėjo.";
                    } else {
                        $lockedPlayer->hitpoints = $remainingHp;
                        $locked->last_event = $damage > 0
                            ? "{$locked->monster_name} pataikė {$damage}."
                            : "{$locked->monster_name} nepataikė.";
                        $locked->monster_next_attack_ms = $monsterAt + (
                            $locked->monster_attack_ticks * self::TICK_MS
                        );
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
            'tickMs' => self::TICK_MS,
            'style' => $encounter->player_style,
            'player' => [
                'hp' => $player->hitpoints,
                'maxHp' => $player->max_hitpoints,
                'attackTicks' => $encounter->player_attack_ticks,
                'attackSeconds' => ($encounter->player_attack_ticks * self::TICK_MS) / 1000,
                'maxHit' => $encounter->player_max_hit,
                'nextAttackAtMs' => $encounter->player_next_attack_ms,
            ],
            'monster' => [
                'slug' => $encounter->monster_slug,
                'name' => $encounter->monster_name,
                'level' => $encounter->monster_level,
                'hp' => $encounter->monster_hp,
                'maxHp' => $encounter->monster_max_hp,
                'attackTicks' => $encounter->monster_attack_ticks,
                'attackSeconds' => ($encounter->monster_attack_ticks * self::TICK_MS) / 1000,
                'maxHit' => $encounter->monster_max_hit,
                'nextAttackAtMs' => $encounter->monster_next_attack_ms,
            ],
            'lastEvent' => $encounter->last_event,
        ];
    }

    private function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
