<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'match_id',
        'game_session_id',
        'game_mode',
        'map_name',
        'custom_code',
        'raw_data'
    ];

    protected $casts = [
        'raw_data' => 'array'
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function playerStats()
    {
        return $this->hasMany(PlayerMatchStat::class);
    }

    public function teamStats()
    {
        return $this->hasMany(TeamMatchStat::class);
    }
}