<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopStock extends Model
{
    protected $fillable = [
        'shop_key',
        'item_id',
        'stock',
        'default_stock',
        'sell_price',
        'buy_price',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'default_stock' => 'integer',
            'sell_price' => 'integer',
            'buy_price' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
