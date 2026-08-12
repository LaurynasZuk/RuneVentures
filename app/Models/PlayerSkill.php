<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerSkill extends Model
{
    public $timestamps = false;
    protected $fillable = ['player_id', 'skill', 'xp'];
}
