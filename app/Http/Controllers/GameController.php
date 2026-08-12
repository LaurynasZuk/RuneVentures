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
                'level' => $this->levelForXp($skill->xp),
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
            $skill = PlayerSkill::query()->lockForUpdate()->firstOrCreate(
                ['player_id' => $player->id, 'skill' => 'woodcutting'],
                ['xp' => 0],
            );
            $skill->increment('xp', 25);

            $logs = Item::firstOrCreate(['slug' => 'logs'], ['name' => 'Logs', 'icon' => 'logs', 'stackable' => true]);
            $slot = InventoryItem::firstOrCreate(
                ['player_id' => $player->id, 'item_id' => $logs->id],
                ['quantity' => 0],
            );
            $slot->increment('quantity');
        });

        return back()->with('game', '+1 Logs · +25 Woodcutting XP');
    }

    private function player(Request $request): Player
    {
        $start = Location::firstOrCreate(
            ['slug' => 'starter-village'],
            ['name' => 'Aldor Village', 'description' => 'A quiet frontier settlement where every adventure begins.', 'region' => 'Greenreach'],
        );

        return Player::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['location_id' => $start->id, 'name' => $request->user()->name],
        );
    }

    private function levelForXp(int $xp): int
    {
        return min(99, 1 + (int) floor(sqrt($xp / 100)));
    }

    private function locationContent(string $slug): array
    {
        return match ($slug) {
            'whispering-woods' => [
                'objects' => [['name' => 'Abandoned shrine', 'detail' => 'Ancient runes cover the stone.']],
                'npcs' => [['name' => 'Elowen', 'detail' => 'Wandering herbalist']],
                'resources' => [['name' => 'Old tree', 'action' => 'chop', 'requiredLevel' => 1]],
                'monsters' => [['name' => 'Forest spider', 'level' => 3]],
            ],
            default => [
                'objects' => [['name' => 'Village well', 'detail' => 'The water is cold and clear.'], ['name' => 'Bank chest', 'detail' => 'Coming soon']],
                'npcs' => [['name' => 'Elder Rowan', 'detail' => 'Village elder'], ['name' => 'Mara', 'detail' => 'General store keeper']],
                'resources' => [['name' => 'Tree', 'action' => 'chop', 'requiredLevel' => 1]],
                'monsters' => [['name' => 'Giant rat', 'level' => 2]],
            ],
        };
    }
}
