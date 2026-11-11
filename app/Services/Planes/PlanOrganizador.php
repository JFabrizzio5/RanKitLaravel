<?php

namespace App\Services\Planes;

use App\Models\PlanPeriod;
use App\Models\User;
use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
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

        return $this->esExento($user)
            || $this->periodosVigentes($user)->isNotEmpty()
            || $this->graciaHasta($user) !== null;
    }

    /**
     * Hasta cuándo puede seguir administrando sus torneos sin plan vigente:
     * el último torneo creado con un plan (no reembolsado) + gracia_torneo_dias.
     * Null si no hay gracia. Así un torneo creado el día 29 del pase no se
     * queda sin panel al día siguiente.
     */
    public function graciaHasta(User $user): ?Carbon
    {
        $dias = (int) config('planes.gracia_torneo_dias', 0);
        if ($dias <= 0) {
            return null;
        }

        self::asegurarTablas();

        $ultimo = DB::table('plan_period_tournaments as t')
            ->join('plan_periods as p', 'p.id', '=', 't.plan_period_id')
            ->where('t.user_id', $user->id)
            ->whereNull('p.revoked_at')
            ->max('t.created_at');

        if (!$ultimo) {
            return null;
        }

        $hasta = Carbon::parse($ultimo)->addDays($dias);

        return $hasta->isFuture() ? $hasta : null;
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
                'administra' => true,
                'gracia_hasta' => null,
                'recarga' => null,
                'por_vencer' => null,
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

        // Solo importa cuando ya no tiene plan: con plan vigente entra de todos modos.
        $gracia = $periodos->isEmpty() ? $this->graciaHasta($user) : null;

        return [
            'exento' => false,
            'activo' => $periodos->isNotEmpty(),
            'administra' => $periodos->isNotEmpty() || $gracia !== null,
            'gracia_hasta' => $gracia?->toIso8601String(),
            // Tickets sin usar o Gestor Pro que se pierden pronto (aviso del front).
            'por_vencer' => ($aviso = $this->porVencer($periodos)) ? Arr::except($aviso, 'periodos') : null,
            // Lo que da y cuesta un pase Base: el botón "Recargar" del front.
            'recarga' => [
                'tickets' => (int) $this->catalogo->plan('base')['torneos'],
                'precio' => $this->catalogo->precio('base'),
            ],
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
     * Qué se le va a vencer en los próximos `aviso_vencimiento_dias`:
     *  - tickets sin usar de pases Base que terminan pronto, o
     *  - el Gestor Pro, si termina pronto y no se renueva (cortesía,
     *    renovación cancelada, pase de Mercado Pago).
     * No avisa de tickets que se pierden mientras tenga ilimitados más allá
     * de esa fecha. Devuelve también los ids de los periodos para no repetir
     * el correo (ver AvisosVencimiento).
     *
     * @param  \Illuminate\Support\Collection<int, PlanPeriod>  $periodos  vigentes del usuario
     * @return array{tickets: int|null, vence: string, dias: int, periodos: int[]}|null
     */
    public function porVencer($periodos): ?array
    {
        $dias = (int) config('planes.aviso_vencimiento_dias', 0);
        if ($dias <= 0 || $periodos->isEmpty()) {
            return null;
        }

        $limite = now()->addDays($dias);
        $finIlimitado = $periodos->filter(fn (PlanPeriod $p) => $p->esIlimitado())->max('ends_at');

        $pases = $periodos->filter(fn (PlanPeriod $p) => !$p->esIlimitado()
            && $p->ends_at <= $limite
            && $p->usados() < $p->tournaments_limit
            && (!$finIlimitado || $finIlimitado <= $p->ends_at));

        if ($pases->isNotEmpty()) {
            $vence = $pases->min('ends_at');

            return [
                'tickets' => (int) $pases->sum(fn (PlanPeriod $p) => $p->tournaments_limit - $p->usados()),
                'vence' => $vence->toIso8601String(),
                'dias' => $this->diasHasta($vence),
                'periodos' => $pases->pluck('id')->values()->all(),
            ];
        }

        $ilimitados = $periodos->filter(fn (PlanPeriod $p) => $p->esIlimitado());
        if ($finIlimitado && $finIlimitado <= $limite && !$ilimitados->contains(fn (PlanPeriod $p) => $p->renews)) {
            return [
                'tickets' => null,
                'vence' => $finIlimitado->toIso8601String(),
                'dias' => $this->diasHasta($finIlimitado),
                'periodos' => $ilimitados->pluck('id')->values()->all(),
            ];
        }

        return null;
    }

    /** Días de calendario en CDMX: lo que vence pasado mañana dice "en 2 días". */
    private function diasHasta(Carbon $fecha): int
    {
        $zona = 'America/Mexico_City';

        return (int) max(0, now($zona)->startOfDay()->diffInDays($fecha->copy()->timezone($zona)->startOfDay()));
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
                throw new SinCupoDeTorneos('Ya no tienes tickets de torneo. Recarga o cámbiate a Gestor Pro.');
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
