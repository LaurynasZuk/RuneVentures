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
        'monster_max_hit',
        'player_style',
        'player_max_hit',
        'player_attack_interval_ms',
        'monster_attack_interval_ms',
        'player_attack_roll',
        'player_defence_roll',
        'monster_attack_roll',
        'monster_defence_roll',
        'player_next_attack_ms',
        'monster_next_attack_ms',
        'status',
        'last_event',
    ];

    protected function casts(): array
    {
        return [
            'monster_level' => 'integer',
            'monster_hp' => 'integer',
            'monster_max_hp' => 'integer',
            'monster_max_hit' => 'integer',
            'player_max_hit' => 'integer',
            'player_attack_interval_ms' => 'integer',
            'monster_attack_interval_ms' => 'integer',
            'player_attack_roll' => 'integer',
            'player_defence_roll' => 'integer',
            'monster_attack_roll' => 'integer',
            'monster_defence_roll' => 'integer',
            'player_next_attack_ms' => 'integer',
            'monster_next_attack_ms' => 'integer',
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
