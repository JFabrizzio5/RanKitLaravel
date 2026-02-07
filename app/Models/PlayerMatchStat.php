<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerMatchStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_match_id',
        'player_name',
        'placement',
        'kills',
        'damage_done',
        'damage_taken',
        'extra_stats'
    ];

    protected $casts = [
        'extra_stats' => 'array'
    ];

    public function match ()
    {
        return $this->belongsTo(TournamentMatch::class , 'tournament_match_id');
    }
}