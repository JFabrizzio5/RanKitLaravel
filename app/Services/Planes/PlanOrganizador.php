<?php

namespace App\Services\Planes;

use App\Models\PlanPeriod;
use App\Models\User;
use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reglas del plan de organizador: quién entra a los paneles, cuántos torneos
 * le quedan y cómo se registra un pago. Las pasarelas solo llaman a
 * registrarPago(); nadie más escribe en plan_periods.
 */
class PlanOrganizador
{
    public function __construct(private CatalogoPlanes $catalogo)
    {
    }

    /**
     * Mismo patrón que ensureDatabaseIsReady(): en producción no se corren
     * migraciones, así que las tablas se crean la primera vez que se usan.
     */
    public static function asegurarTablas(): void
    {
        if (Schema::hasTable('plan_period_tournaments')) {
            return;
        }

        if (!Schema::hasTable('plan_periods')) {
            Schema::create('plan_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('plan', 20);
                $table->string('provider', 20); // stripe | mercadopago | manual
                $table->unsignedInteger('tournaments_limit')->nullable(); // null = ilimitado
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable()->index();
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('currency', 3)->default('MXN');
                // Llave de idempotencia: el webhook y el regreso del pago pueden
                // llegar los dos (o repetirse) y solo debe quedar un periodo.
                $table->string('provider_payment_id')->unique();
                $table->string('provider_subscription_id')->nullable()->index();
                $table->boolean('renews')->default(false);
                $table->timestamp('revoked_at')->nullable();
                $table->string('notes')->nullable();
                $table->timestamps();
            });
        }

        // Antes de crear la última tabla: si la cortesía falla, la siguiente
        // visita no encuentra plan_period_tournaments y la vuelve a intentar.
        CortesiaLanzamiento::aplicar();

        Schema::create('plan_period_tournaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_period_id')->constrained('plan_periods')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('game', 20); // fortnite | lol | football
            $table->unsignedBigInteger('tournament_id');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function esExento(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $exentos = array_map('strtolower', config('planes.exentos', []));

        return $user->isSuperAdmin() || in_array(strtolower((string) $user->email), $exentos, true);
    }

    public function puedeEntrar(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->esExento($user) || $this->periodosVigentes($user)->isNotEmpty();
    }

    /** @return \Illuminate\Support\Collection<int, PlanPeriod> */
    public function periodosVigentes(User $user)
    {
        self::asegurarTablas();

        return PlanPeriod::vigentes()
            ->where('user_id', $user->id)
            ->withCount('torneos')
            ->orderBy('ends_at')
            ->get();
    }

    /**
     * Lo que ve el front (prop compartida "plan").
     */
    public function resumen(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        if ($this->esExento($user)) {
            return [
                'exento' => true,
                'activo' => true,
                'ilimitado' => true,
                'plan' => null,
                'torneos_restantes' => null,
                'vence' => null,
                'renueva' => false,
                'periodos' => [],
            ];
        }

        $periodos = $this->periodosVigentes($user);
        $ilimitado = $periodos->contains(fn (PlanPeriod $p) => $p->esIlimitado());
        $restantes = $periodos
            ->reject(fn (PlanPeriod $p) => $p->esIlimitado())
            ->sum(fn (PlanPeriod $p) => max(0, $p->tournaments_limit - $p->usados()));

        return [
            'exento' => false,
            'activo' => $periodos->isNotEmpty(),
            'ilimitado' => $ilimitado,
            'plan' => $ilimitado ? 'pro' : ($periodos->isNotEmpty() ? 'base' : null),
            'torneos_restantes' => $ilimitado ? null : $restantes,
            'vence' => $periodos->max('ends_at')?->toIso8601String(),
            'renueva' => $periodos->contains(fn (PlanPeriod $p) => $p->renews),
            'periodos' => $periodos->map(fn (PlanPeriod $p) => [
                'id' => $p->id,
                'plan' => $p->plan,
                'proveedor' => $p->provider,
                'limite' => $p->tournaments_limit,
                'usados' => $p->usados(),
                'inicio' => $p->starts_at?->toIso8601String(),
                'vence' => $p->ends_at?->toIso8601String(),
                'renueva' => $p->renews,
                'suscripcion' => $p->provider_subscription_id,
            ])->values()->all(),
        ];
    }

    /**
     * Crea un torneo descontándolo del plan. $crear inserta el torneo y
     * devuelve su id; corre dentro de la misma transacción que el descuento
     * para que dos altas simultáneas no se pasen del límite.
     *
     * Borrar el torneo NO devuelve el cupo: en LoL y fútbol se puede borrar un
     * torneo ya jugado, y eso sería un torneo gratis.
     *
     * @throws SinCupoDeTorneos
     */
    public function crearTorneo(User $user, string $juego, Closure $crear): int
    {
        if ($this->esExento($user)) {
            return (int) $crear();
        }

        self::asegurarTablas();

        return DB::transaction(function () use ($user, $juego, $crear) {
            $periodos = PlanPeriod::vigentes()
                ->where('user_id', $user->id)
                ->withCount('torneos')
                ->orderBy('ends_at')
                ->lockForUpdate()
                ->get();

            if ($periodos->isEmpty()) {
                throw new SinCupoDeTorneos('Necesitas un plan activo para crear torneos.');
            }

            // Primero un periodo ilimitado; si no, el pase que vence antes.
            $periodo = $periodos->first(fn (PlanPeriod $p) => $p->esIlimitado())
                ?? $periodos->first(fn (PlanPeriod $p) => $p->tieneCupo());

            if (!$periodo) {
                throw new SinCupoDeTorneos('Ya usaste los torneos de tu plan. Compra otro pase o cámbiate a Gestor Pro.');
            }

            $id = (int) $crear();

            $periodo->torneos()->create([
                'user_id' => $user->id,
                'game' => $juego,
                'tournament_id' => $id,
            ]);

            return $id;
        });
    }

    /**
     * Da de alta un periodo pagado. Idempotente por $referencia: si el pago ya
     * se registró (webhook repetido, o webhook y regreso del checkout), devuelve
     * el mismo periodo.
     */
    public function registrarPago(
        int $userId,
        string $plan,
        string $proveedor,
        string $referencia,
        float $monto,
        ?Carbon $inicio = null,
        ?Carbon $fin = null,
        ?string $suscripcion = null,
        bool $renueva = false,
        ?string $notas = null,
    ): PlanPeriod {
        self::asegurarTablas();

        if ($existente = PlanPeriod::where('provider_payment_id', $referencia)->first()) {
            return $existente;
        }

        $def = $this->catalogo->plan($plan);
        $inicio ??= now();
        $fin ??= $inicio->copy()->addDays($def['dias']);

        try {
            return PlanPeriod::create([
                'user_id' => $userId,
                'plan' => $plan,
                'provider' => $proveedor,
                'tournaments_limit' => $def['torneos'],
                'starts_at' => $inicio,
                'ends_at' => $fin,
                'amount' => $monto,
                'currency' => 'MXN',
                'provider_payment_id' => $referencia,
                'provider_subscription_id' => $suscripcion,
                'renews' => $renueva,
                'notes' => $notas,
            ]);
        } catch (UniqueConstraintViolationException) {
            // El webhook y el regreso del checkout llegaron al mismo tiempo.
            return PlanPeriod::where('provider_payment_id', $referencia)->firstOrFail();
        }
    }

    /** Termina ya el periodo de un pago (reembolso o contracargo). */
    public function revocarPago(string $referencia): void
    {
        self::asegurarTablas();

        PlanPeriod::where('provider_payment_id', $referencia)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'renews' => false]);
    }

    /** Termina ya todos los periodos vigentes de un usuario (superadmin). */
    public function revocarTodo(User $user): int
    {
        self::asegurarTablas();

        return PlanPeriod::vigentes()
            ->where('user_id', $user->id)
            ->update(['revoked_at' => now(), 'renews' => false]);
    }

    /** Marca si una suscripción de Stripe se va a renovar o ya se canceló. */
    public function marcarRenovacion(string $suscripcion, bool $renueva): void
    {
        self::asegurarTablas();

        PlanPeriod::where('provider_subscription_id', $suscripcion)->update(['renews' => $renueva]);
    }
}
