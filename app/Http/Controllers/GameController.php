<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\Player;
use App\Models\PlayerSkill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    public function show(Request $request): Response
    {
        $player = $this->player($request);
        $player->load(['location.destinations', 'skills', 'inventory.item']);

        return Inertia::render('game', [
            'player' => [
                'name' => $player->name,
                'hitpoints' => $player->hitpoints,
                'maxHitpoints' => $player->max_hitpoints,
                'prayerPoints' => $player->prayer_points,
                'combatLevel' => $this->combatLevel($player),
            ],
            'location' => [
                'id' => $player->location->id,
                'name' => $player->location->name,
                'region' => $player->location->region,
                'description' => $player->location->description,
                'connections' => $player->location->destinations->map(fn (Location $location) => [
                    'id' => $location->id,
                    'name' => $location->pivot->label,
                    'travelSeconds' => $location->pivot->travel_seconds,
                ]),
                'content' => $this->locationContent($player->location->slug),
            ],
            'skills' => $player->skills->mapWithKeys(fn (PlayerSkill $skill) => [$skill->skill => [
                'xp' => $skill->xp,
                'level' => $this->levelForXp($skill->xp, $skill->skill === 'hitpoints' ? 10 : 1),
            ]]),
            'inventory' => $player->inventory->map(fn (InventoryItem $slot) => [
                'id' => $slot->item->id,
                'name' => $slot->item->name,
                'icon' => $slot->item->icon,
                'quantity' => $slot->quantity,
            ]),
            'flash' => ['game' => session('game')],
        ]);
    }

    public function travel(Request $request, Location $location): RedirectResponse
    {
        $player = $this->player($request);
        abort_unless($player->location->destinations()->whereKey($location->id)->exists(), 403);

        $player->update(['location_id' => $location->id]);

        return back()->with('game', "Atvykai į {$location->name}.");
    }

    public function chop(Request $request): RedirectResponse
    {
        $player = $this->player($request);
        abort_unless(in_array($player->location->slug, ['starter-village', 'whispering-woods'], true), 403);

        DB::transaction(function () use ($player) {
            $this->grantXp($player, 'woodcutting', 25);
            $this->grantItem($player, 'logs', 'Logs', 'logs');
        });

        return back()->with('game', '+1 Logs · +25 Woodcutting XP');
    }

    public function attack(Request $request, string $monster): RedirectResponse
    {
        $player = $this->player($request);
        $monsterData = collect($this->locationContent($player->location->slug)['monsters'])
            ->firstWhere('slug', $monster);

        abort_unless($monsterData, 404);

        DB::transaction(function () use ($player, $monsterData) {
            $damageTaken = max(0, (int) $monsterData['level'] - 1);
            $remainingHp = max(1, $player->hitpoints - $damageTaken);
            $player->update(['hitpoints' => $remainingHp]);

            $this->grantXp($player, 'attack', (int) $monsterData['xp']);
            $this->grantXp($player, 'hitpoints', max(1, (int) floor($monsterData['xp'] / 3)));
            $this->grantItem($player, 'bones', 'Bones', 'bones');
        });

        return back()->with(
            'game',
            "Nugalėjai {$monsterData['name']} · +{$monsterData['xp']} Attack XP · +1 Bones",
        );
    }

    private function player(Request $request): Player
    {
        $start = $this->ensureWorld();

        $player = Player::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['location_id' => $start->id, 'name' => $request->user()->name],
        );

        foreach (['attack', 'strength', 'defence', 'hitpoints', 'ranged', 'magic', 'prayer', 'woodcutting', 'mining', 'fishing', 'cooking'] as $skill) {
            PlayerSkill::firstOrCreate(
                ['player_id' => $player->id, 'skill' => $skill],
                ['xp' => 0],
            );
        }

        return $player;
    }

    private function ensureWorld(): Location
    {
        $village = Location::firstOrCreate(
            ['slug' => 'starter-village'],
            [
                'name' => 'Aldor Village',
                'description' => 'A quiet frontier settlement where every adventure begins.',
                'region' => 'Greenreach',
            ],
        );

        $woods = Location::firstOrCreate(
            ['slug' => 'whispering-woods'],
            [
                'name' => 'Whispering Woods',
                'description' => 'Silver leaves whisper old secrets beneath a dim green canopy.',
                'region' => 'Greenreach',
            ],
        );

        $village->destinations()->syncWithoutDetaching([
            $woods->id => ['label' => 'Whispering Woods', 'travel_seconds' => 4],
        ]);
        $woods->destinations()->syncWithoutDetaching([
            $village->id => ['label' => 'Aldor Village', 'travel_seconds' => 4],
        ]);

        return $village;
    }

    private function grantXp(Player $player, string $skill, int $amount): void
    {
        $row = PlayerSkill::query()->lockForUpdate()->firstOrCreate(
            ['player_id' => $player->id, 'skill' => $skill],
            ['xp' => 0],
        );

        $row->increment('xp', $amount);
    }

    private function grantItem(Player $player, string $slug, string $name, string $icon): void
    {
        $item = Item::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'icon' => $icon, 'stackable' => true],
        );

        $slot = InventoryItem::firstOrCreate(
            ['player_id' => $player->id, 'item_id' => $item->id],
            ['quantity' => 0],
        );

        $slot->increment('quantity');
    }

    private function levelForXp(int $xp, int $startingLevel = 1): int
    {
        return min(99, max($startingLevel, $startingLevel + (int) floor(sqrt($xp / 100))));
    }

    private function combatLevel(Player $player): int
    {
        $skills = $player->skills->keyBy('skill');
        $level = fn (string $name, int $default = 1) => $this->levelForXp((int) ($skills->get($name)?->xp ?? 0), $default);

        $base = 0.25 * ($level('defence') + $level('hitpoints', 10) + floor($level('prayer') / 2));
        $melee = 0.325 * ($level('attack') + $level('strength'));
        $ranged = 0.325 * floor($level('ranged') * 1.5);
        $magic = 0.325 * floor($level('magic') * 1.5);

        return max(3, (int) floor($base + max($melee, $ranged, $magic)));
    }

    private function locationContent(string $slug): array
    {
        return match ($slug) {
            'whispering-woods' => [
                'objects' => [
                    ['name' => 'Abandoned shrine', 'detail' => 'Ancient runes cover the stone.'],
                ],
                'npcs' => [
                    ['name' => 'Elowen', 'detail' => 'Wandering herbalist'],
                ],
                'resources' => [
                    ['name' => 'Old tree', 'action' => 'chop', 'requiredLevel' => 1],
                ],
                'monsters' => [
                    ['name' => 'Forest spider', 'slug' => 'forest-spider', 'level' => 3, 'xp' => 18],
                ],
            ],
            default => [
                'objects' => [
                    ['name' => 'Village well', 'detail' => 'The water is cold and clear.'],
                    ['name' => 'Bank chest', 'detail' => 'Coming soon'],
                ],
                'npcs' => [
                    ['name' => 'Elder Rowan', 'detail' => 'Village elder'],
                    ['name' => 'Mara', 'detail' => 'General store keeper'],
                ],
                'resources' => [
                    ['name' => 'Tree', 'action' => 'chop', 'requiredLevel' => 1],
                ],
                'monsters' => [
                    ['name' => 'Giant rat', 'slug' => 'giant-rat', 'level' => 2, 'xp' => 12],
                ],
            ],
        };
    }
}
