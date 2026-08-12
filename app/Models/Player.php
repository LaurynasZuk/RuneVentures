<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    protected $fillable = ['user_id', 'location_id', 'name', 'hitpoints', 'max_hitpoints', 'prayer_points'];

    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function skills(): HasMany { return $this->hasMany(PlayerSkill::class); }
    public function inventory(): HasMany { return $this->hasMany(InventoryItem::class); }
}
