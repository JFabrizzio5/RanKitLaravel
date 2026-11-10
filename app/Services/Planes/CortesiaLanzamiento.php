<?php

namespace App\Services\Planes;

use App\Models\PlanPeriod;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Cortesía única al lanzar los planes: los organizadores con rol admin que ya
 * tenían torneos (y entraban gratis al panel) reciben Gestor Pro por
 * config('planes.cortesia_lanzamiento.dias') días, contados desde el deploy.
 *
 * Corre una sola vez, cuando PlanOrganizador::asegurarTablas() crea las
 * tablas (la primera visita después de desplegar). Es idempotente por la
 * referencia "cortesia-lanzamiento:{user_id}".
 *
 * ⚠️ Cualquier cosa que llame asegurarTablas() contra producción antes del
 * deploy (p. ej. tinker) arranca el conteo de los 30 días en ese momento.
 */
class CortesiaLanzamiento
{
    /** Tablas de torneos de los tres paneles (dueño en user_id). */
    private const TABLAS_TORNEOS = ['tournaments', 'lol_test_tournaments', 'football_tournaments'];

    public static function aplicar(): void
    {
        $dias = (int) config('planes.cortesia_lanzamiento.dias', 0);
        $plan = (string) config('planes.cortesia_lanzamiento.plan', 'pro');
        if ($dias <= 0) {
            return;
        }

        $planes = app(PlanOrganizador::class);
        $limite = config("planes.planes.$plan.torneos");
        $otorgados = [];

        foreach (self::candidatos() as $user) {
            if ($planes->esExento($user)) {
                continue;
            }

            // No pasa por registrarPago(): este código corre dentro de
            // asegurarTablas() y registrarPago() la vuelve a llamar.
            PlanPeriod::firstOrCreate(
                ['provider_payment_id' => 'cortesia-lanzamiento:'.$user->id],
                [
                    'user_id' => $user->id,
                    'plan' => $plan,
                    'provider' => 'manual',
                    'tournaments_limit' => $limite,
                    'starts_at' => now(),
                    'ends_at' => now()->addDays($dias),
                    'amount' => 0,
                    'currency' => 'MXN',
                    'notes' => "Cortesía de lanzamiento ($dias días)",
                ],
            );
            $otorgados[] = $user->email;
        }

        if ($otorgados) {
            Log::info('Planes: cortesía de lanzamiento otorgada', ['dias' => $dias, 'plan' => $plan, 'usuarios' => $otorgados]);
        }
    }

    /** Admins dueños de al menos un torneo en cualquiera de los paneles. */
    private static function candidatos()
    {
        $tablas = array_filter(self::TABLAS_TORNEOS, fn (string $t) => Schema::hasTable($t));
        if (!$tablas) {
            return collect();
        }

        return User::where('role', User::ROLE_ADMIN)
            ->where(function ($q) use ($tablas) {
                foreach ($tablas as $tabla) {
                    $q->orWhereExists(fn ($sub) => $sub->from($tabla)->whereColumn("$tabla.user_id", 'users.id'));
                }
            })
            ->get();
    }
}
