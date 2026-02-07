<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'game',
        'twitch_channel',
        'is_private',
        'access_code',
        'rules',
        'prizes',
        'scoring_format', // JSON
        'entry_fee',
        'currency',
        'table_name'
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'scoring_format' => 'array',
        'entry_fee' => 'integer' // Cents
    ];

    // -- Relationships --

    public function owner()
    {
        return $this->belongsTo(User::class , 'user_id');
    }

    public function matches()
    {
        return $this->hasMany(TournamentMatch::class);
    }

    public function registrations()
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    public function prizes()
    {
        return $this->hasMany(TournamentPrize::class);
    }
}