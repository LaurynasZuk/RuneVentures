<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    protected $fillable = [
        'user_id',
        'location_id',
        'travel_destination_id',
        'travel_ends_at',
        'name',
        'hitpoints',
        'max_hitpoints',
        'prayer_points',
        'prayer_mana',
        'inventory_base_slots',
        'backpack_slots_unlocked',
    ];

    protected function casts(): array
    {
        return [
            'hitpoints' => 'integer',
            'max_hitpoints' => 'integer',
            'prayer_points' => 'integer',
            'prayer_mana' => 'integer',
            'inventory_base_slots' => 'integer',
            'backpack_slots_unlocked' => 'integer',
            'travel_ends_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function travelDestination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'travel_destination_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(PlayerSkill::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(InventoryItem::class)->orderBy('slot');
    }

    public function backpacks(): HasMany
    {
        return $this->hasMany(PlayerBackpack::class)->orderBy('slot');
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(PlayerEquipment::class)->orderBy('slot');
    }
}
