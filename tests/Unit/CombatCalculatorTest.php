<?php

namespace Tests\Unit;

use App\Services\CombatCalculator;
use PHPUnit\Framework\TestCase;

class CombatCalculatorTest extends TestCase
{
    public function test_attack_and_defence_rolls_use_level_and_equipment_bonus(): void
    {
        $calculator = new CombatCalculator();

        $this->assertSame(6032, $calculator->attackRoll(50, 40));
        $this->assertSame(6032, $calculator->defenceRoll(50, 40));
        $this->assertGreaterThan(
            $calculator->attackRoll(50, 40),
            $calculator->attackRoll(51, 40),
        );
        $this->assertGreaterThan(
            $calculator->attackRoll(50, 40),
            $calculator->attackRoll(50, 41),
        );
    }

    public function test_strength_uses_the_x10_damage_scale_before_rounding(): void
    {
        $calculator = new CombatCalculator();

        $this->assertSame(99, $calculator->maxHit(50, 40));
        $this->assertSame(100, $calculator->maxHit(50, 41));
        $this->assertSame(14, $calculator->maxHit(1, 0));
    }

    public function test_equal_attack_and_defence_rolls_are_about_fifty_percent_accuracy(): void
    {
        $calculator = new CombatCalculator();
        $roll = 6032;

        $this->assertEqualsWithDelta(0.5, $calculator->hitChance($roll, $roll), 0.001);
    }
}
