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

        $world = $this->worldMap();

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
                'slug' => $player->location->slug,
                'region' => $player->location->region,
                'description' => $player->location->description,
                'connections' => $player->location->destinations->map(fn (Location $location) => [
                    'id' => $location->id,
                    'name' => $location->pivot->label,
                    'travelSeconds' => $location->pivot->travel_seconds,
                ])->values(),
            ],
            'worldMap' => [
                'nodes' => $world['nodes'],
                'edges' => $world['edges'],
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

    public function locationCategory(Request $request, Location $location, string $category): Response
    {
        $player = $this->player($request);
        abort_unless($player->location_id === $location->id, 403);

        $labels = [
            'monsters' => 'Monstrai',
            'npcs' => 'Personažai',
            'resources' => 'Resursai',
            'objects' => 'Objektai',
        ];

        abort_unless(isset($labels[$category]), 404);

        $content = $this->locationContent($location->slug);

        return Inertia::render('location-category', [
            'location' => [
                'id' => $location->id,
                'name' => $location->name,
                'region' => $location->region,
            ],
            'category' => [
                'key' => $category,
                'label' => $labels[$category],
            ],
            'items' => $content[$category],
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
        abort_unless(in_array($player->location->slug, ['starter-village'], true), 403);

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
        $start = Location::firstOrCreate(
            ['slug' => 'starter-village'],
            [
                'name' => 'Aldor Village',
                'description' => 'Pradinė vietovė prie senojo kelio į pajūrį.',
                'region' => 'Vakarinis kraštas',
            ],
        );

        $port = Location::firstOrCreate(
            ['slug' => 'port'],
            [
                'name' => 'Uostas',
                'description' => 'Pakrantės miestas, kuriame susitinka keliautojai, prekeiviai ir jūrininkai.',
                'region' => 'Vakarinis kraštas',
            ],
        );

        $start->destinations()->sync([
            $port->id => ['label' => 'Uostas', 'travel_seconds' => 4],
        ]);

        $port->destinations()->sync([
            $start->id => ['label' => 'Aldor Village', 'travel_seconds' => 4],
        ]);

        return $start;
    }

    private function worldMap(): array
    {
        $start = Location::where('slug', 'starter-village')->firstOrFail();
        $port = Location::where('slug', 'port')->firstOrFail();

        return [
            'nodes' => [
                [
                    'id' => $start->id,
                    'name' => $start->name,
                    'type' => 'Vietovė',
                    'x' => 170,
                    'y' => 150,
                ],
                [
                    'id' => $port->id,
                    'name' => $port->name,
                    'type' => 'Miestas',
                    'x' => 540,
                    'y' => 150,
                ],
            ],
            'edges' => [
                ['from' => $start->id, 'to' => $port->id],
            ],
        ];
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
            'port' => [
                'objects' => [
                    ['name' => 'Uosto vartai', 'detail' => 'Pagrindinis įėjimas į prieplaukos rajoną.'],
                ],
                'npcs' => [
                    ['name' => 'Uosto sargas', 'detail' => 'Prižiūri miesto prieplauką.'],
                ],
                'resources' => [
                    ['name' => 'Žvejybos vieta', 'detail' => 'Rami vieta prie medinio molo.'],
                ],
                'monsters' => [
                    ['name' => 'Uosto žiurkė', 'slug' => 'port-rat', 'level' => 2, 'xp' => 12],
                ],
            ],
            default => [
                'objects' => [
                    ['name' => 'Senas šulinys', 'detail' => 'Akmeninis šulinys prie pagrindinio kelio.'],
                ],
                'npcs' => [
                    ['name' => 'Kelio sargas', 'detail' => 'Stebi kelią į Uostą.'],
                ],
                'resources' => [
                    ['name' => 'Medis', 'action' => 'chop', 'requiredLevel' => 1],
                ],
                'monsters' => [
                    ['name' => 'Didžioji žiurkė', 'slug' => 'giant-rat', 'level' => 2, 'xp' => 12],
                ],
            ],
        };
    }
}
