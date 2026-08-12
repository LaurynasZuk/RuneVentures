<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeWeaponStats();
        $this->normalizeArmourStats();
        $this->seedPrayerRemains();
    }

    public function down(): void
    {
        DB::table('items')->whereIn('slug', [
            'baby-dragon-bones',
            'dragon-bones',
            'ancient-bones',
        ])->delete();
    }

    private function normalizeWeaponStats(): void
    {
        DB::table('items')
            ->where('category', 'weapon')
            ->orderBy('id')
            ->chunkById(100, function ($items): void {
                foreach ($items as $item) {
                    $data = $this->decodeGameData($item->game_data);
                    $tier = (int) $item->tier;
                    $speed = (string) ($data['speed'] ?? 'average');
                    $hand = (string) ($data['hand'] ?? 'main_hand');

                    $autoMultipliers = match ($hand) {
                        'off_hand' => [
                            'fastest' => 4.8,
                            'fast' => 6.125,
                            'average' => 7.45,
                        ],
                        'two_hand' => [
                            'fastest' => 14.4,
                            'fast' => 18.375,
                            'average' => 22.35,
                        ],
                        default => [
                            'fastest' => 9.6,
                            'fast' => 12.25,
                            'average' => 14.9,
                        ],
                    };

                    $abilityMultiplier = match ($hand) {
                        'off_hand' => 4.8,
                        'two_hand' => 14.4,
                        default => 9.6,
                    };

                    $data['rs3_accuracy'] = $this->accuracyForTier($tier);
                    $data['rs3_auto_damage'] = round(
                        $tier * ($autoMultipliers[$speed] ?? $autoMultipliers['average']),
                        1,
                    );
                    $data['rs3_ability_damage'] = round($tier * $abilityMultiplier, 1);

                    DB::table('items')->where('id', $item->id)->update([
                        'game_data' => $this->encodeGameData($data),
                    ]);
                }
            });
    }

    private function normalizeArmourStats(): void
    {
        DB::table('items')
            ->where('category', 'armour')
            ->orderBy('id')
            ->chunkById(100, function ($items): void {
                foreach ($items as $item) {
                    $data = $this->decodeGameData($item->game_data);
                    $tier = (int) $item->tier;
                    $slot = (string) ($data['armour_slot'] ?? 'head');
                    $type = (string) ($data['armour_type'] ?? 'tank');
                    $armourTier = $type === 'power' ? max(1, $tier - 5) : $tier;

                    $baseArmour = 2.5 * (
                        (($armourTier ** 3) / 1250)
                        + (4 * $armourTier)
                        + 40
                    );
                    $slotMultiplier = match ($slot) {
                        'body' => 0.23,
                        'legs' => 0.22,
                        'hands', 'feet' => 0.05,
                        default => 0.20,
                    };

                    $data['rs3_armour_tier'] = $armourTier;
                    $data['rs3_damage_tier'] = $type === 'power' ? $tier : null;
                    $data['rs3_armour'] = round($baseArmour * $slotMultiplier, 1);
                    $data['rs3_life_points'] = $type === 'tank'
                        ? $this->tankLifePoints($tier, $slot)
                        : 0;

                    DB::table('items')->where('id', $item->id)->update([
                        'game_data' => $this->encodeGameData($data),
                    ]);
                }
            });
    }

    private function seedPrayerRemains(): void
    {
        $now = now();
        $items = [
            [
                'slug' => 'baby-dragon-bones',
                'name' => 'Baby dragon bones',
                'xp' => 30.0,
                'special' => false,
            ],
            [
                'slug' => 'dragon-bones',
                'name' => 'Dragon bones',
                'xp' => 72.0,
                'special' => false,
            ],
            [
                'slug' => 'ancient-bones',
                'name' => 'Ancient bones',
                'xp' => 200.0,
                'special' => true,
            ],
        ];

        foreach ($items as $item) {
            $exists = DB::table('items')->where('slug', $item['slug'])->exists();
            $payload = [
                'name' => $item['name'],
                'icon' => 'bones',
                'category' => 'prayer',
                'tier' => null,
                'f2p' => true,
                'stackable' => true,
                'stack_limit' => 20,
                'equip_slot' => null,
                'combat_style' => null,
                'attack_interval_ms' => null,
                'attack_bonus' => 0,
                'strength_bonus' => 0,
                'defence_bonus' => 0,
                'inventory_slots_bonus' => 0,
                'required_skill' => null,
                'required_level' => null,
                'game_data' => $this->encodeGameData([
                    'source_system' => 'rs3',
                    'resource_type' => 'bones',
                    'prayer_xp' => $item['xp'],
                    'prayer_action' => 'bury',
                    'limited_quest_reward' => $item['special'],
                ]),
                'updated_at' => $now,
            ];

            if (! $exists) {
                $payload['created_at'] = $now;
            }

            DB::table('items')->updateOrInsert(
                ['slug' => $item['slug']],
                $payload,
            );
        }
    }

    private function accuracyForTier(int $tier): int
    {
        return (int) floor(2.5 * (
            (($tier ** 3) / 1250)
            + (4 * $tier)
            + 40
        ));
    }

    private function tankLifePoints(int $tier, string $slot): int
    {
        return match ($slot) {
            'body', 'legs' => $tier * 15,
            'hands', 'feet' => $tier * 5,
            'shield' => 0,
            default => $tier * 10,
        };
    }

    private function decodeGameData(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    private function encodeGameData(array $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
    }
};
