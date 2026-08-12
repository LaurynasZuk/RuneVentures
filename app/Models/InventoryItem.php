<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['player_id', 'slot', 'item_id', 'quantity'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
