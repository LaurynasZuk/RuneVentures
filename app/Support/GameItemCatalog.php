<?php

namespace App\Support;

final class GameItemCatalog
{
    private const METALS = [
        'bronze' => ['name' => 'Bronze', 'tier' => 5, 'level' => 1, 'f2p' => true],
        'iron' => ['name' => 'Iron', 'tier' => 10, 'level' => 10, 'f2p' => true],
        'steel' => ['name' => 'Steel', 'tier' => 20, 'level' => 20, 'f2p' => true],
        'black' => ['name' => 'Black', 'tier' => 25, 'level' => 25, 'f2p' => true],
        'mithril' => ['name' => 'Mithril', 'tier' => 30, 'level' => 30, 'f2p' => true],
        'adamant' => ['name' => 'Adamant', 'tier' => 40, 'level' => 40, 'f2p' => true],
        'rune' => ['name' => 'Rune', 'tier' => 50, 'level' => 50, 'f2p' => true],
        'dragon' => ['name' => 'Dragon', 'tier' => 60, 'level' => 60, 'f2p' => false],
    ];

    private const WEAPONS = [
        ['key' => 'dagger', 'name' => 'dagger', 'style' => 'stab', 'speed' => 'fastest', 'dual' => true],
        ['key' => 'sword', 'name' => 'sword', 'style' => 'stab', 'speed' => 'fast', 'dual' => true],
        ['key' => 'mace', 'name' => 'mace', 'style' => 'crush', 'speed' => 'fastest', 'dual' => true],
        ['key' => 'scimitar', 'name' => 'scimitar', 'style' => 'slash', 'speed' => 'fastest', 'dual' => true],
        ['key' => 'longsword', 'name' => 'longsword', 'style' => 'slash', 'speed' => 'fast', 'dual' => true],
        ['key' => 'warhammer', 'name' => 'warhammer', 'style' => 'crush', 'speed' => 'average', 'dual' => true],
        ['key' => 'battleaxe', 'name' => 'battleaxe', 'style' => 'slash', 'speed' => 'average', 'dual' => true],
        ['key' => 'claw', 'name' => 'claw', 'style' => 'slash', 'speed' => 'fastest', 'dual' => true],
        ['key' => 'hasta', 'name' => 'hasta', 'style' => 'stab', 'speed' => 'fastest', 'dual' => false],
        ['key' => 'spear', 'name' => 'spear', 'style' => 'stab', 'speed' => 'average', 'dual' => false, 'two_handed' => true],
        ['key' => '2h-sword', 'name' => '2h sword', 'style' => 'slash', 'speed' => 'average', 'dual' => false, 'two_handed' => true],
        ['key' => 'halberd', 'name' => 'halberd', 'style' => 'slash', 'speed' => 'average', 'dual' => false, 'two_handed' => true],
    ];

    private const ARMOUR = [
        ['key' => 'helm', 'name' => 'helm', 'slot' => 'head', 'stat_slot' => 'head'],
        ['key' => 'full-helm', 'name' => 'full helm', 'slot' => 'head', 'stat_slot' => 'head'],
        ['key' => 'chainbody', 'name' => 'chainbody', 'slot' => 'body', 'stat_slot' => 'body'],
        ['key' => 'platebody', 'name' => 'platebody', 'slot' => 'body', 'stat_slot' => 'body'],
        ['key' => 'platelegs', 'name' => 'platelegs', 'slot' => 'legs', 'stat_slot' => 'legs'],
        ['key' => 'plateskirt', 'name' => 'plateskirt', 'slot' => 'legs', 'stat_slot' => 'legs'],
        ['key' => 'sq-shield', 'name' => 'sq shield', 'slot' => 'shield', 'stat_slot' => 'shield'],
        ['key' => 'kiteshield', 'name' => 'kiteshield', 'slot' => 'shield', 'stat_slot' => 'shield'],
        ['key' => 'gauntlets', 'name' => 'gauntlets', 'slot' => 'hands', 'stat_slot' => 'hands'],
        ['key' => 'boots', 'name' => 'boots', 'slot' => 'feet', 'stat_slot' => 'feet'],
    ];

    public static function all(): array
    {
        return array_values([
            ...self::weapons(),
            ...self::armour(),
            ...self::tools(),
            ...self::ores(),
            ...self::bars(),
            ...self::wood(),
            ...self::fish(),
            ...self::prayer(),
        ]);
    }

    public static function bySlug(string $slug): ?array
    {
        foreach (self::all() as $item) {
            if ($item['slug'] === $slug) {
                return $item;
            }
        }

        return null;
    }

    private static function weapons(): array
    {
        $items = [];

        foreach (self::METALS as $metalKey => $metal) {
            foreach (self::WEAPONS as $weapon) {
                $twoHanded = (bool) ($weapon['two_handed'] ?? false);
                $items[] = self::weapon(
                    $metalKey,
                    $metal,
                    $weapon,
                    $twoHanded ? 'two_hand' : 'main_hand',
                );

                if ($weapon['dual']) {
                    $items[] = self::weapon($metalKey, $metal, $weapon, 'off_hand');
                }
            }
        }

        return $items;
    }

    private static function weapon(string $metalKey, array $metal, array $weapon, string $hand): array
    {
        $offHand = $hand === 'off_hand';
        $twoHanded = $hand === 'two_hand';
        $prefix = $offHand ? 'off hand ' : '';
        $slugPrefix = $offHand ? 'off-hand-' : '';
        $tier = (int) $metal['tier'];
        $speed = $weapon['speed'];

        return self::item(
            name: $metal['name'].' '.$prefix.$weapon['name'],
            slug: $metalKey.'-'.$slugPrefix.$weapon['key'],
            icon: 'weapon',
            category: 'weapon',
            tier: $tier,
            f2p: (bool) $metal['f2p'],
            stackable: false,
            stackLimit: 1,
            equipSlot: $offHand ? 'shield' : 'weapon',
            combatStyle: 'melee',
            attackIntervalMs: self::speedMs($speed),
            requiredSkill: 'attack',
            requiredLevel: (int) $metal['level'],
            gameData: [
                'source_system' => 'rs3',
                'metal' => $metalKey,
                'weapon_type' => $weapon['key'],
                'hand' => $hand,
                'two_handed' => $twoHanded,
                'attack_style' => $weapon['style'],
                'speed' => $speed,
                'rs3_accuracy' => self::weaponAccuracy($tier),
                'rs3_auto_damage' => self::weaponAutoDamage($tier, $speed, $hand),
                'rs3_ability_damage' => self::weaponAbilityDamage($tier, $hand),
            ],
        );
    }

    private static function armour(): array
    {
        $items = [];

        foreach (self::METALS as $metalKey => $metal) {
            foreach (self::ARMOUR as $piece) {
                $tier = (int) $metal['tier'];
                $isShield = $piece['stat_slot'] === 'shield';
                $isDragonPower = $metalKey === 'dragon' && ! $isShield;
                $armourTier = $isDragonPower ? $tier - 5 : $tier;

                $items[] = self::item(
                    name: $metal['name'].' '.$piece['name'],
                    slug: $metalKey.'-'.$piece['key'],
                    icon: 'armour',
                    category: 'armour',
                    tier: $tier,
                    f2p: (bool) $metal['f2p'],
                    stackable: false,
                    stackLimit: 1,
                    equipSlot: $piece['slot'],
                    requiredSkill: 'defence',
                    requiredLevel: (int) $metal['level'],
                    gameData: [
                        'source_system' => 'rs3',
                        'metal' => $metalKey,
                        'armour_type' => $isDragonPower ? 'power' : 'tank',
                        'armour_slot' => $piece['stat_slot'],
                        'rs3_armour' => self::armourValue($armourTier, $piece['stat_slot']),
                        'rs3_damage_bonus' => $isDragonPower
                            ? self::armourDamage($tier, $piece['stat_slot'])
                            : 0.0,
                    ],
                );
            }
        }

        return $items;
    }

    private static function tools(): array
    {
        $items = [];
        $pickaxes = [
            ['key' => 'bronze', 'name' => 'Bronze', 'level' => 1, 'min' => 3, 'avg' => 5, 'max' => 7, 'penetration' => 0, 'f2p' => true],
            ['key' => 'iron', 'name' => 'Iron', 'level' => 10, 'min' => 5, 'avg' => 10, 'max' => 15, 'penetration' => 5, 'f2p' => true],
            ['key' => 'steel', 'name' => 'Steel', 'level' => 20, 'min' => 10, 'avg' => 20, 'max' => 30, 'penetration' => 15, 'f2p' => true],
            ['key' => 'mithril', 'name' => 'Mithril', 'level' => 30, 'min' => 15, 'avg' => 30, 'max' => 45, 'penetration' => 30, 'f2p' => true],
            ['key' => 'adamant', 'name' => 'Adamant', 'level' => 40, 'min' => 20, 'avg' => 40, 'max' => 60, 'penetration' => 50, 'f2p' => true],
            ['key' => 'rune', 'name' => 'Rune', 'level' => 50, 'min' => 25, 'avg' => 50, 'max' => 75, 'penetration' => 75, 'f2p' => true],
            ['key' => 'dragon', 'name' => 'Dragon', 'level' => 60, 'min' => 33, 'avg' => 63, 'max' => 93, 'penetration' => 105, 'f2p' => false],
        ];

        foreach ($pickaxes as $pickaxe) {
            $items[] = self::item(
                name: $pickaxe['name'].' pickaxe',
                slug: $pickaxe['key'].'-pickaxe',
                icon: 'pickaxe',
                category: 'tool',
                tier: $pickaxe['level'],
                f2p: $pickaxe['f2p'],
                stackable: false,
                stackLimit: 1,
                equipSlot: 'weapon',
                combatStyle: 'melee',
                attackIntervalMs: 2400,
                requiredSkill: 'mining',
                requiredLevel: $pickaxe['level'],
                gameData: [
                    'source_system' => 'rs3',
                    'tool_type' => 'pickaxe',
                    'mining_min_damage' => $pickaxe['min'],
                    'mining_average_damage' => $pickaxe['avg'],
                    'mining_max_damage' => $pickaxe['max'],
                    'mining_penetration' => $pickaxe['penetration'],
                    'rs3_combat_accuracy' => 110,
                    'rs3_auto_damage' => 0.0,
                    'rs3_ability_damage' => 0.0,
                    'attack_style' => 'crush',
                ],
            );
        }

        $hatchets = [
            ['key' => 'bronze', 'name' => 'Bronze', 'tier' => 5, 'level' => 1, 'cutting' => 5, 'f2p' => true],
            ['key' => 'iron', 'name' => 'Iron', 'tier' => 10, 'level' => 10, 'cutting' => 10, 'f2p' => true],
            ['key' => 'steel', 'name' => 'Steel', 'tier' => 20, 'level' => 20, 'cutting' => 20, 'f2p' => true],
            ['key' => 'black', 'name' => 'Black', 'tier' => 25, 'level' => 25, 'cutting' => 25, 'f2p' => true],
            ['key' => 'mithril', 'name' => 'Mithril', 'tier' => 30, 'level' => 30, 'cutting' => 30, 'f2p' => true],
            ['key' => 'adamant', 'name' => 'Adamant', 'tier' => 40, 'level' => 40, 'cutting' => 40, 'f2p' => true],
            ['key' => 'rune', 'name' => 'Rune', 'tier' => 50, 'level' => 50, 'cutting' => 50, 'f2p' => true],
            ['key' => 'dragon', 'name' => 'Dragon', 'tier' => 60, 'level' => 60, 'cutting' => 63, 'f2p' => false],
        ];

        foreach ($hatchets as $hatchet) {
            $tier = $hatchet['tier'];
            $items[] = self::item(
                name: $hatchet['name'].' hatchet',
                slug: $hatchet['key'].'-hatchet',
                icon: 'hatchet',
                category: 'tool',
                tier: $tier,
                f2p: $hatchet['f2p'],
                stackable: false,
                stackLimit: 1,
                equipSlot: 'weapon',
                combatStyle: 'melee',
                attackIntervalMs: 3000,
                requiredSkill: 'woodcutting',
                requiredLevel: $hatchet['level'],
                gameData: [
                    'source_system' => 'rs3',
                    'tool_type' => 'hatchet',
                    'cutting_power_tier' => $hatchet['cutting'],
                    'rs3_combat_accuracy' => self::weaponAccuracy($tier),
                    'rs3_auto_damage' => self::floor1($tier * 6.125),
                    'rs3_ability_damage' => self::floor1($tier * 4.8),
                    'attack_style' => 'slash',
                    'speed' => 'fast',
                ],
            );
        }

        return $items;
    }

    private static function ores(): array
    {
        $ores = [
            ['copper-ore', 'Copper ore', 1, 40, 0, 0.66],
            ['tin-ore', 'Tin ore', 1, 40, 0, 0.66],
            ['iron-ore', 'Iron ore', 10, 120, 5, 0.68],
            ['coal', 'Coal', 20, 140, 15, 0.70],
            ['silver-ore', 'Silver ore', 20, 140, 15, null],
            ['mithril-ore', 'Mithril ore', 30, 240, 30, 0.72],
            ['gold-ore', 'Gold ore', 40, 200, 50, null],
            ['adamantite-ore', 'Adamantite ore', 40, 380, 50, 0.74],
            ['luminite', 'Luminite', 40, 380, 50, 0.74],
            ['runite-ore', 'Runite ore', 50, 600, 75, 0.76],
        ];

        return array_map(function (array $ore): array {
            [$slug, $name, $level, $durability, $hardness, $xpMultiplier] = $ore;
            $data = [
                'source_system' => 'rs3',
                'resource_type' => 'ore',
                'mining_level' => $level,
                'rock_durability' => $durability,
                'rock_hardness' => $hardness,
            ];

            if ($xpMultiplier !== null) {
                $data['mining_xp_multiplier'] = $xpMultiplier;
                $data['mining_xp_formula'] = 'damage * mining_xp_multiplier * 0.4';
            }

            return self::item(
                name: $name,
                slug: $slug,
                icon: 'ore',
                category: 'ore',
                f2p: true,
                stackable: true,
                stackLimit: 20,
                requiredSkill: 'mining',
                requiredLevel: $level,
                gameData: $data,
            );
        }, $ores);
    }

    private static function bars(): array
    {
        $bars = [
            ['bronze-bar', 'Bronze bar', 1, 1.0, 15.0, ['copper-ore' => 1, 'tin-ore' => 1]],
            ['iron-bar', 'Iron bar', 10, 2.0, 40.0, ['iron-ore' => 2]],
            ['silver-bar', 'Silver bar', 20, 3.0, null, ['silver-ore' => 1]],
            ['steel-bar', 'Steel bar', 20, 3.0, 75.0, ['iron-ore' => 1, 'coal' => 1]],
            ['mithril-bar', 'Mithril bar', 30, 5.0, 120.0, ['mithril-ore' => 1, 'coal' => 1]],
            ['gold-bar', 'Gold bar', 40, 7.0, null, ['gold-ore' => 1]],
            ['adamant-bar', 'Adamant bar', 40, 7.0, 170.0, ['adamantite-ore' => 1, 'luminite' => 1]],
            ['rune-bar', 'Rune bar', 50, 10.0, 240.0, ['runite-ore' => 1, 'luminite' => 1]],
        ];

        return array_map(fn (array $bar): array => self::item(
            name: $bar[1],
            slug: $bar[0],
            icon: 'bar',
            category: 'bar',
            f2p: true,
            stackable: true,
            stackLimit: 20,
            requiredSkill: 'smithing',
            requiredLevel: $bar[2],
            gameData: [
                'source_system' => 'rs3',
                'resource_type' => 'bar',
                'smithing_level' => $bar[2],
                'smelting_xp' => $bar[3],
                'forge_xp_per_bar' => $bar[4],
                'recipe' => $bar[5],
            ],
        ), $bars);
    }

    private static function wood(): array
    {
        $logs = [
            ['logs', 'Logs', 1, 25.0, 1, 40.0],
            ['oak-logs', 'Oak logs', 15, 37.5, 15, 60.0],
            ['willow-logs', 'Willow logs', 30, 67.5, 30, 90.0],
            ['maple-logs', 'Maple logs', 40, 100.0, 45, 135.0],
            ['yew-logs', 'Yew logs', 60, 175.0, 60, 202.5],
        ];

        return array_map(fn (array $log): array => self::item(
            name: $log[1],
            slug: $log[0],
            icon: 'logs',
            category: 'wood',
            f2p: true,
            stackable: true,
            stackLimit: 20,
            requiredSkill: 'woodcutting',
            requiredLevel: $log[2],
            gameData: [
                'source_system' => 'rs3',
                'resource_type' => 'wood',
                'woodcutting_level' => $log[2],
                'woodcutting_xp' => $log[3],
                'firemaking_level' => $log[4],
                'firemaking_xp' => $log[5],
            ],
        ), $logs);
    }

    private static function fish(): array
    {
        $fish = [
            ['shrimps', 'Shrimps', 1, 10.0, 1, 30.0, 200],
            ['crayfish', 'Crayfish', 1, 10.0, 1, 30.0, 200],
            ['minnow', 'Minnow', 1, 10.0, 1, 15.0, 150],
            ['sardine', 'Sardine', 5, 20.0, 1, 40.0, 200],
            ['herring', 'Herring', 10, 30.0, 5, 50.0, 200],
            ['anchovies', 'Anchovies', 15, 40.0, 1, 30.0, 200],
            ['trout', 'Trout', 20, 50.0, 15, 70.0, 300],
            ['pike', 'Pike', 25, 60.0, 20, 80.0, 500],
            ['salmon', 'Salmon', 30, 70.0, 25, 90.0, 625],
            ['tuna', 'Tuna', 35, 80.0, 30, 100.0, 750],
            ['lobster', 'Lobster', 40, 90.0, 40, 120.0, 1200],
            ['bass', 'Bass', 46, 100.0, 43, 130.0, 1300],
            ['swordfish', 'Swordfish', 50, 100.0, 45, 140.0, 1400],
        ];

        $items = [];

        foreach ($fish as $row) {
            [$key, $name, $fishingLevel, $fishingXp, $cookingLevel, $cookingXp, $heal] = $row;
            $rawSlug = 'raw-'.$key;

            $items[] = self::item(
                name: 'Raw '.strtolower($name),
                slug: $rawSlug,
                icon: 'fish',
                category: 'fish_raw',
                f2p: true,
                stackable: true,
                stackLimit: 20,
                requiredSkill: 'fishing',
                requiredLevel: $fishingLevel,
                gameData: [
                    'source_system' => 'rs3',
                    'resource_type' => 'fish',
                    'fishing_level' => $fishingLevel,
                    'fishing_xp' => $fishingXp,
                    'cooking_level' => $cookingLevel,
                    'cooking_xp' => $cookingXp,
                    'cooks_into' => $key,
                ],
            );

            $items[] = self::item(
                name: $name,
                slug: $key,
                icon: 'food',
                category: 'food',
                f2p: true,
                stackable: true,
                stackLimit: 20,
                gameData: [
                    'source_system' => 'rs3',
                    'resource_type' => 'cooked_fish',
                    'heal_amount' => $heal,
                    'cooking_level' => $cookingLevel,
                    'cooking_xp' => $cookingXp,
                    'raw_item' => $rawSlug,
                ],
            );
        }

        return $items;
    }

    private static function prayer(): array
    {
        $remains = [
            ['impious-ashes', 'Impious ashes', 'ashes', 4.0, 'scatter'],
            ['bones', 'Bones', 'bones', 4.5, 'bury'],
            ['wolf-bones', 'Wolf bones', 'bones', 4.5, 'bury'],
            ['burnt-bones', 'Burnt bones', 'bones', 4.5, 'bury'],
            ['monkey-bones', 'Monkey bones', 'bones', 5.0, 'bury'],
            ['bat-bones', 'Bat bones', 'bones', 5.3, 'bury'],
            ['accursed-ashes', 'Accursed ashes', 'ashes', 12.5, 'scatter'],
            ['big-bones', 'Big bones', 'bones', 15.0, 'bury'],
        ];

        return array_map(fn (array $remain): array => self::item(
            name: $remain[1],
            slug: $remain[0],
            icon: $remain[2],
            category: 'prayer',
            f2p: true,
            stackable: true,
            stackLimit: 20,
            gameData: [
                'source_system' => 'rs3',
                'resource_type' => $remain[2],
                'prayer_xp' => $remain[3],
                'prayer_action' => $remain[4],
            ],
        ), $remains);
    }

    private static function item(
        string $name,
        string $slug,
        string $icon,
        string $category,
        ?int $tier = null,
        bool $f2p = true,
        bool $stackable = false,
        ?int $stackLimit = 1,
        ?string $equipSlot = null,
        ?string $combatStyle = null,
        ?int $attackIntervalMs = null,
        ?string $requiredSkill = null,
        ?int $requiredLevel = null,
        array $gameData = [],
    ): array {
        return [
            'name' => $name,
            'slug' => $slug,
            'icon' => $icon,
            'category' => $category,
            'tier' => $tier,
            'f2p' => $f2p,
            'stackable' => $stackable,
            'stack_limit' => $stackLimit,
            'equip_slot' => $equipSlot,
            'combat_style' => $combatStyle,
            'attack_interval_ms' => $attackIntervalMs,
            'attack_bonus' => 0,
            'strength_bonus' => 0,
            'defence_bonus' => 0,
            'inventory_slots_bonus' => 0,
            'required_skill' => $requiredSkill,
            'required_level' => $requiredLevel,
            'game_data' => $gameData,
        ];
    }

    private static function speedMs(string $speed): int
    {
        return match ($speed) {
            'fastest' => 2400,
            'fast' => 3000,
            default => 3600,
        };
    }

    private static function weaponAccuracy(int $tier): int
    {
        return (int) floor(2.5 * ((($tier ** 3) / 1250) + (4 * $tier) + 40));
    }

    private static function weaponAbilityDamage(int $tier, string $hand): float
    {
        $multiplier = match ($hand) {
            'off_hand' => 4.8,
            'two_hand' => 14.4,
            default => 9.6,
        };

        return self::floor1($tier * $multiplier);
    }

    private static function weaponAutoDamage(int $tier, string $speed, string $hand): float
    {
        $multipliers = match ($hand) {
            'off_hand' => ['fastest' => 4.8, 'fast' => 6.125, 'average' => 7.45],
            'two_hand' => ['fastest' => 14.4, 'fast' => 18.375, 'average' => 22.35],
            default => ['fastest' => 9.6, 'fast' => 12.25, 'average' => 14.9],
        };

        return self::floor1($tier * $multipliers[$speed]);
    }

    private static function armourValue(int $tier, string $slot): float
    {
        $base = 2.5 * ((($tier ** 3) / 1250) + (4 * $tier) + 40);
        $multiplier = match ($slot) {
            'body' => 0.23,
            'legs' => 0.22,
            'hands', 'feet' => 0.05,
            default => 0.20,
        };

        return self::floor1($base * $multiplier);
    }

    private static function armourDamage(int $tier, string $slot): float
    {
        $multiplier = match ($slot) {
            'body' => 0.375,
            'legs' => 0.3125,
            'hands', 'feet' => 0.15625,
            default => 0.25,
        };

        return self::floor1($tier * $multiplier);
    }

    private static function floor1(float $value): float
    {
        return floor($value * 10) / 10;
    }
}
