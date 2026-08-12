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
        'attack_ticks',
        'max_hit',
        'inventory_slots_bonus',
    ];

    protected function casts(): array
    {
        return [
            'stackable' => 'boolean',
            'stack_limit' => 'integer',
            'attack_ticks' => 'integer',
            'max_hit' => 'integer',
            'inventory_slots_bonus' => 'integer',
        ];
    }
}
