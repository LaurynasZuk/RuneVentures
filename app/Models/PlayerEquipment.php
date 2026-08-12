<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerEquipment extends Model
{
    public $timestamps = false;

    protected $table = 'player_equipment';

    protected $fillable = ['player_id', 'slot', 'item_id'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
