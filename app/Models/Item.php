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
        'inventory_slots_bonus',
    ];
}
