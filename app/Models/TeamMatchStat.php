<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamMatchStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_match_id',
        'team_id_in_match',
        'rank',
        'member_names',
        'team_signature',
        'total_kills',
        'total_points'
    ];

    protected $casts = [
        'member_names' => 'array'
    ];

    public function match ()
    {
        return $this->belongsTo(TournamentMatch::class , 'tournament_match_id');
    }
}