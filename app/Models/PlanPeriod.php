<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un periodo pagado (o regalado) de plan de organizador. Cada pago es un
 * renglón: un pase Base, un mes de Pro o una renovación automática de Stripe.
 */
class PlanPeriod extends Model
{
    protected $table = 'plan_periods';

    protected $fillable = [
        'user_id',
        'plan',
        'provider',
        'tournaments_limit',
        'starts_at',
        'ends_at',
        'amount',
        'currency',
        'provider_payment_id',
        'provider_subscription_id',
        'renews',
        'revoked_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'revoked_at' => 'datetime',
            'renews' => 'boolean',
            'amount' => 'float',
            'tournaments_limit' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function torneos(): HasMany
    {
        return $this->hasMany(PlanPeriodTournament::class);
    }

    /** Torneos que ya se descontaron (requiere withCount('torneos')). */
    public function usados(): int
    {
        return (int) ($this->torneos_count ?? $this->torneos()->count());
    }

    public function tieneCupo(): bool
    {
        return $this->esIlimitado() || $this->usados() < $this->tournaments_limit;
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }

    public function esIlimitado(): bool
    {
        return $this->tournaments_limit === null;
    }
}
