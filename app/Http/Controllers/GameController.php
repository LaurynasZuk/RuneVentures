<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\Player;
use App\Models\PlayerBackpack;
use App\Models\PlayerEquipment;
use App\Models\PlayerSkill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    private const EQUIPMENT_SLOTS = [
        'head',
        'cape',
        'neck',
        'ammo',
        'weapon',
        'body',
        'shield',
        'legs',
        'hands',
        'feet',
        'ring',
    ];

    private const SKILLS = [
        'attack',
        'hitpoints',
        'mining',
        'strength',
        'agility',
        'smithing',
        'defence',
        'herblore',
        'fishing',
        'ranged',
        'thieving',
        'cooking',
        'prayer',
        'crafting',
        'firemaking',
        'magic',
        'fletching',
        'woodcutting',
        'runecraft',
        'slayer',
        'farming',
        'construction',
        'hunter',
        'sailing',
    ];

    public function show(Request $request): Response
    {
        $player = $this->player($request);
        $this->finishTravelIfReady($player);

        $player->refresh()->load([
            'location.destinations',
            'travelDestination',
            'skills',
            'inventory.item',
            'backpacks.item',
            'equipment.item',
        ]);

        $world = $this->worldMap();
        $prayerLevel = $this->skillLevel($player, 'prayer');
        $maxPrayerMana = $prayerLevel * 100;
        $prayerMana = min($player->prayer_mana, $maxPrayerMana);

        if ($player->prayer_mana !== $prayerMana) {
            $player->update(['prayer_mana' => $prayerMana]);
        }

        $backpackBonus = $player->backpacks
            ->take($player->backpack_slots_unlocked)
            ->sum(fn (PlayerBackpack $slot) => (int) ($slot->item?->inventory_slots_bonus ?? 0));
        $inventoryCapacity = $player->inventory_base_slots + $backpackBonus;

        $equipment = collect(self::EQUIPMENT_SLOTS)->mapWithKeys(function (string $slot) use ($player) {
            $row = $player->equipment->firstWhere('slot', $slot);

            return [$slot => $row?->item ? [
                'id' => $row->item->id,
                'name' => $row->item->name,
                'icon' => $row->item->icon,
            ] : null];
        });

        $backpacks = collect(range(1, 5))->map(function (int $slot) use ($player) {
            $row = $player->backpacks->firstWhere('slot', $slot);

            return [
                'slot' => $slot,
                'unlocked' => $slot <= $player->backpack_slots_unlocked,
                'item' => $row?->item ? [
                    'id' => $row->item->id,
                    'name' => $row->item->name,
                    'icon' => $row->item->icon,
                    'slotsBonus' => (int) $row->item->inventory_slots_bonus,
                ] : null,
            ];
        });

        $travel = null;
        if ($player->travel_destination_id && $player->travel_ends_at && $player->travelDestination) {
            $travel = [
                'destinationId' => $player->travelDestination->id,
                'destinationName' => $player->travelDestination->name,
                'endsAt' => $player->travel_ends_at->toIso8601String(),
                'remainingSeconds' => max(
                    0,
                    $player->travel_ends_at->getTimestamp() - now()->getTimestamp(),
                ),
            ];
        }

        return Inertia::render('game', [
            'player' => [
                'name' => $player->name,
                'hitpoints' => $player->hitpoints,
                'maxHitpoints' => $player->max_hitpoints,
                'prayerMana' => $prayerMana,
                'maxPrayerMana' => $maxPrayerMana,
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
                    'name' => $location->name,
                    'travelSeconds' => (int) $location->pivot->travel_seconds,
                ])->values(),
            ],
            'worldMap' => [
                'nodes' => $world['nodes'],
                'edges' => $world['edges'],
            ],
            'travel' => $travel,
            'skills' => $player->skills->mapWithKeys(fn (PlayerSkill $skill) => [$skill->skill => [
                'xp' => $skill->xp,
                'level' => $this->levelForXp($skill->xp, $skill->skill === 'hitpoints' ? 10 : 1),
            ]]),
            'inventory' => $player->inventory->map(fn (InventoryItem $stack) => [
                'slot' => $stack->slot,
                'id' => $stack->item->id,
                'name' => $stack->item->name,
                'icon' => $stack->item->icon,
                'quantity' => $stack->quantity,
                'stackLimit' => $stack->item->stackable ? $stack->item->stack_limit : 1,
            ])->values(),
            'inventoryCapacity' => $inventoryCapacity,
            'backpacks' => $backpacks,
            'equipment' => $equipment,
            'equipmentSlots' => self::EQUIPMENT_SLOTS,
            'flash' => ['game' => session('game')],
        ]);
    }

    public function locationCategory(Request $request, Location $location, string $category): Response
    {
        $player = $this->player($request);
        $this->finishTravelIfReady($player);
        $player->refresh();

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
        $this->finishTravelIfReady($player);
        $player->refresh()->load('location.destinations');

        if ($this->isTraveling($player)) {
            return back()->with('game', 'Kelionė jau vyksta.');
        }

        $destination = $player->location->destinations->firstWhere('id', $location->id);
        abort_unless($destination, 403);

        $travelSeconds = max(1, (int) $destination->pivot->travel_seconds);

        $player->update([
            'travel_destination_id' => $destination->id,
            'travel_ends_at' => now()->addSeconds($travelSeconds),
        ]);

        return back()->with(
            'game',
            "Kelionė į {$destination->name} prasidėjo · {$travelSeconds} s.",
        );
    }

    public function chop(Request $request): RedirectResponse
    {
        $player = $this->player($request);
        $this->finishTravelIfReady($player);
        $player->refresh();

        if ($this->isTraveling($player)) {
            return back()->with('game', 'Veiksmai negalimi kelionės metu.');
        }

        abort_unless(in_array($player->location->slug, ['starter-village'], true), 403);

        $added = DB::transaction(function () use ($player) {
            if (! $this->grantItem($player, 'logs', 'Logs', 'logs', 20)) {
                return false;
            }

            $this->grantXp($player, 'woodcutting', 25);

            return true;
        });

        return back()->with('game', $added
            ? '+1 Logs · +25 Woodcutting XP'
            : 'Inventorius pilnas.');
    }

    public function attack(Request $request, string $monster): RedirectResponse
    {
        $player = $this->player($request);
        $this->finishTravelIfReady($player);
        $player->refresh();

        if ($this->isTraveling($player)) {
            return back()->with('game', 'Veiksmai negalimi kelionės metu.');
        }

        $monsterData = collect($this->locationContent($player->location->slug)['monsters'])
            ->firstWhere('slug', $monster);

        abort_unless($monsterData, 404);

        $added = DB::transaction(function () use ($player, $monsterData) {
            if (! $this->grantItem($player, 'bones', 'Bones', 'bones', 20)) {
                return false;
            }

            $damageTaken = max(0, (int) $monsterData['level'] - 1);
            $remainingHp = max(1, $player->hitpoints - $damageTaken);
            $player->update(['hitpoints' => $remainingHp]);

            $this->grantXp($player, 'attack', (int) $monsterData['xp']);
            $this->grantXp($player, 'hitpoints', max(1, (int) floor($monsterData['xp'] / 3)));

            return true;
        });

        if (! $added) {
            return back()->with('game', 'Inventorius pilnas.');
        }

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
            [
                'location_id' => $start->id,
                'name' => $request->user()->name,
                'prayer_mana' => 100,
                'inventory_base_slots' => 25,
                'backpack_slots_unlocked' => 1,
            ],
        );

        foreach (self::SKILLS as $skill) {
            PlayerSkill::firstOrCreate(
                ['player_id' => $player->id, 'skill' => $skill],
                ['xp' => 0],
            );
        }

        foreach (range(1, 5) as $slot) {
            PlayerBackpack::firstOrCreate([
                'player_id' => $player->id,
                'slot' => $slot,
            ]);
        }

        foreach (self::EQUIPMENT_SLOTS as $slot) {
            PlayerEquipment::firstOrCreate([
                'player_id' => $player->id,
                'slot' => $slot,
            ]);
        }

        return $player;
    }

    private function ensureWorld(): Location
    {
        $lumbridge = Location::updateOrCreate(
            ['slug' => 'starter-village'],
            [
                'name' => 'Lumbridge',
                'description' => 'Pagrindinė pradinė vietovė ir kelių sankirta.',
                'region' => 'Central lands',
            ],
        );

        $eastPort = Location::updateOrCreate(
            ['slug' => 'port'],
            [
                'name' => 'East Port',
                'description' => 'Rytinis uostas prie pakrantės prekybos kelio.',
                'region' => 'Eastern coast',
            ],
        );

        $northEastPlains = Location::updateOrCreate(
            ['slug' => 'north-east-plains'],
            [
                'name' => 'North East Plains',
                'description' => 'Atviros lygumos į šiaurės rytus nuo Lumbridge.',
                'region' => 'North eastern lands',
            ],
        );

        $southWestSwamp = Location::updateOrCreate(
            ['slug' => 'south-west-swamp'],
            [
                'name' => 'South West Swamp',
                'description' => 'Drėgna pelkė pietvakarių keliuose.',
                'region' => 'South western lands',
            ],
        );

        $lumbridge->destinations()->sync([
            $eastPort->id => ['label' => $eastPort->name, 'travel_seconds' => 5],
            $northEastPlains->id => ['label' => $northEastPlains->name, 'travel_seconds' => 6],
            $southWestSwamp->id => ['label' => $southWestSwamp->name, 'travel_seconds' => 5],
        ]);

        $eastPort->destinations()->sync([
            $lumbridge->id => ['label' => $lumbridge->name, 'travel_seconds' => 5],
            $northEastPlains->id => ['label' => $northEastPlains->name, 'travel_seconds' => 4],
        ]);

        $northEastPlains->destinations()->sync([
            $lumbridge->id => ['label' => $lumbridge->name, 'travel_seconds' => 6],
            $eastPort->id => ['label' => $eastPort->name, 'travel_seconds' => 4],
            $southWestSwamp->id => ['label' => $southWestSwamp->name, 'travel_seconds' => 7],
        ]);

        $southWestSwamp->destinations()->sync([
            $lumbridge->id => ['label' => $lumbridge->name, 'travel_seconds' => 5],
            $northEastPlains->id => ['label' => $northEastPlains->name, 'travel_seconds' => 7],
        ]);

        return $lumbridge;
    }

    private function worldMap(): array
    {
        $locations = Location::whereIn('slug', [
            'starter-village',
            'port',
            'north-east-plains',
            'south-west-swamp',
        ])->get()->keyBy('slug');

        $lumbridge = $locations->get('starter-village');
        $eastPort = $locations->get('port');
        $northEastPlains = $locations->get('north-east-plains');
        $southWestSwamp = $locations->get('south-west-swamp');

        $nodes = [
            ['id' => $lumbridge->id, 'name' => $lumbridge->name, 'x' => 170, 'y' => 155],
            ['id' => $eastPort->id, 'name' => $eastPort->name, 'x' => 525, 'y' => 155],
            ['id' => $northEastPlains->id, 'name' => $northEastPlains->name, 'x' => 425, 'y' => 345],
            ['id' => $southWestSwamp->id, 'name' => $southWestSwamp->name, 'x' => 165, 'y' => 515],
        ];

        $ids = collect($nodes)->pluck('id')->all();
        $edges = DB::table('location_connections')
            ->whereIn('location_id', $ids)
            ->whereIn('destination_id', $ids)
            ->get()
            ->map(function ($connection) {
                $from = min((int) $connection->location_id, (int) $connection->destination_id);
                $to = max((int) $connection->location_id, (int) $connection->destination_id);

                return ['from' => $from, 'to' => $to];
            })
            ->unique(fn (array $edge) => $edge['from'].'-'.$edge['to'])
            ->values();

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    private function finishTravelIfReady(Player $player): void
    {
        if (! $player->travel_destination_id || ! $player->travel_ends_at) {
            return;
        }

        if (now()->lt($player->travel_ends_at)) {
            return;
        }

        $destinationId = $player->travel_destination_id;

        if (! Location::whereKey($destinationId)->exists()) {
            $player->update([
                'travel_destination_id' => null,
                'travel_ends_at' => null,
            ]);

            return;
        }

        $player->update([
            'location_id' => $destinationId,
            'travel_destination_id' => null,
            'travel_ends_at' => null,
        ]);
    }

    private function isTraveling(Player $player): bool
    {
        return $player->travel_destination_id !== null
            && $player->travel_ends_at !== null
            && now()->lt($player->travel_ends_at);
    }

    private function grantXp(Player $player, string $skill, int $amount): void
    {
        $row = PlayerSkill::query()->lockForUpdate()->firstOrCreate(
            ['player_id' => $player->id, 'skill' => $skill],
            ['xp' => 0],
        );

        $row->increment('xp', $amount);
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

        if ($item->stack_limit !== $stackLimit || $item->stackable !== ($stackLimit !== 1)) {
            $item->update([
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

    private function skillLevel(Player $player, string $skill): int
    {
        $row = $player->skills->firstWhere('skill', $skill);

        return $this->levelForXp((int) ($row?->xp ?? 0), $skill === 'hitpoints' ? 10 : 1);
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
            'north-east-plains' => [
                'objects' => [
                    ['name' => 'Kelio ženklas', 'detail' => 'Senas ženklas lygumų sankryžoje.'],
                ],
                'npcs' => [
                    ['name' => 'Keliautojas', 'detail' => 'Poilsiauja prie kelio.'],
                ],
                'resources' => [
                    ['name' => 'Laukinės žolelės', 'detail' => 'Auga atvirose lygumose.'],
                ],
                'monsters' => [
                    ['name' => 'Lygumų vilkas', 'slug' => 'plains-wolf', 'level' => 3, 'xp' => 18],
                ],
            ],
            'south-west-swamp' => [
                'objects' => [
                    ['name' => 'Senas lieptas', 'detail' => 'Pelkėje pūvantis medinis lieptas.'],
                ],
                'npcs' => [
                    ['name' => 'Pelkės atsiskyrėlis', 'detail' => 'Gyvena prie sausos salelės.'],
                ],
                'resources' => [
                    ['name' => 'Pelkės augalas', 'detail' => 'Auga sekliame vandenyje.'],
                ],
                'monsters' => [
                    ['name' => 'Pelkės žiurkė', 'slug' => 'swamp-rat', 'level' => 3, 'xp' => 16],
                ],
            ],
            default => [
                'objects' => [
                    ['name' => 'Senas šulinys', 'detail' => 'Akmeninis šulinys prie pagrindinio kelio.'],
                ],
                'npcs' => [
                    ['name' => 'Kelio sargas', 'detail' => 'Stebi pagrindinius kelius.'],
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
