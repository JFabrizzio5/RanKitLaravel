<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un torneo descontado de un periodo de plan. */
class PlanPeriodTournament extends Model
{
    protected $table = 'plan_period_tournaments';

    public const UPDATED_AT = null;

    protected $fillable = ['plan_period_id', 'user_id', 'game', 'tournament_id'];
}
