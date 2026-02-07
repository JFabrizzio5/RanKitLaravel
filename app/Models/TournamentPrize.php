<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Tournament; // Added for relationship
use App\Models\User; // Added for relationship

class TournamentPrize extends Model
{
    protected $fillable = [
        'tournament_id',
        'user_id',
        'title',
        'amount',
        'currency',
        'status',
        'stripe_transfer_id',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function winner()
    {
        return $this->belongsTo(User::class , 'user_id');
    }
}