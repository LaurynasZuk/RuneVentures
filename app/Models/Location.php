<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Location extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'region'];

    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'location_connections', 'location_id', 'destination_id')
            ->withPivot(['label', 'travel_seconds']);
    }
}
