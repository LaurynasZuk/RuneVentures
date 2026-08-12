<?php

namespace App\Services;

class CombatCalculator
{
    private const EQUIPMENT_BASE = 64;
    private const EFFECTIVE_LEVEL_BASE = 8;
    private const DAMAGE_SCALE = 10;

    public function levelForXp(int $xp, int $startingLevel = 1): int
    {
        return min(99, max($startingLevel, $startingLevel + (int) floor(sqrt($xp / 100))));
    }

    public function effectiveLevel(int $level, int $stanceBonus = 0): int
    {
        return max(1, $level + $stanceBonus + self::EFFECTIVE_LEVEL_BASE);
    }

    public function attackRoll(int $level, int $equipmentAttackBonus, int $stanceBonus = 0): int
    {
        $equipmentFactor = max(0, $equipmentAttackBonus + self::EQUIPMENT_BASE);

        return $this->effectiveLevel($level, $stanceBonus) * $equipmentFactor;
    }

    public function defenceRoll(int $level, int $equipmentDefenceBonus, int $stanceBonus = 0): int
    {
        $equipmentFactor = max(0, $equipmentDefenceBonus + self::EQUIPMENT_BASE);

        return $this->effectiveLevel($level, $stanceBonus) * $equipmentFactor;
    }

    public function maxHit(int $strengthLevel, int $equipmentStrengthBonus, int $stanceBonus = 0): int
    {
        $effectiveStrength = $this->effectiveLevel($strengthLevel, $stanceBonus);
        $equipmentFactor = max(0, $equipmentStrengthBonus + self::EQUIPMENT_BASE);

        // OSRS-style max-hit relationship, but calculated directly on RuneVentures'
        // x10 damage scale. This preserves tenths of an old 1-point hit as real
        // integer damage instead of rounding first and multiplying afterwards.
        return max(1, (int) floor(
            (($effectiveStrength * $equipmentFactor) + 320)
            / (640 / self::DAMAGE_SCALE),
        ));
    }

    public function hitChance(int $attackRoll, int $defenceRoll): float
    {
        if ($attackRoll > $defenceRoll) {
            return 1 - (($defenceRoll + 2) / (2 * ($attackRoll + 1)));
        }

        if ($defenceRoll <= 0) {
            return $attackRoll > 0 ? 1.0 : 0.0;
        }

        return $attackRoll / (2 * ($defenceRoll + 1));
    }

    public function rollHit(int $attackRoll, int $defenceRoll): bool
    {
        $chance = max(0.0, min(1.0, $this->hitChance($attackRoll, $defenceRoll)));

        return random_int(1, 1_000_000) <= (int) round($chance * 1_000_000);
    }

    public function rollDamage(int $maxHit): int
    {
        return random_int(0, max(0, $maxHit));
    }
}
