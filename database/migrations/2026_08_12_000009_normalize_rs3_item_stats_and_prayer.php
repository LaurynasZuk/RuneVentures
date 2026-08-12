<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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

    private function encodeGameData(array $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
    }
};
