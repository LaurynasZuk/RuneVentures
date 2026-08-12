<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'stackable',
        'stack_limit',
        'equip_slot',
        'combat_style',
        'attack_interval_ms',
        'attack_bonus',
        'strength_bonus',
        'defence_bonus',
        'inventory_slots_bonus',
    ];

    protected function casts(): array
    {
        return [
            'stackable' => 'boolean',
            'stack_limit' => 'integer',
            'attack_interval_ms' => 'integer',
            'attack_bonus' => 'integer',
            'strength_bonus' => 'integer',
            'defence_bonus' => 'integer',
            'inventory_slots_bonus' => 'integer',
        ];
    }
}
