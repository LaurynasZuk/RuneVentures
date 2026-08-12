<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CombatEncounter extends Model
{
    protected $fillable = [
        'player_id',
        'location_id',
        'monster_slug',
        'monster_name',
        'monster_level',
        'monster_hp',
        'monster_max_hp',
        'monster_attack_ticks',
        'monster_max_hit',
        'player_style',
        'player_attack_ticks',
        'player_max_hit',
        'player_next_attack_at',
        'monster_next_attack_at',
        'status',
        'last_event',
    ];

    protected function casts(): array
    {
        return [
            'monster_level' => 'integer',
            'monster_hp' => 'integer',
            'monster_max_hp' => 'integer',
            'monster_attack_ticks' => 'integer',
            'monster_max_hit' => 'integer',
            'player_attack_ticks' => 'integer',
            'player_max_hit' => 'integer',
            'player_next_attack_at' => 'datetime',
            'monster_next_attack_at' => 'datetime',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
