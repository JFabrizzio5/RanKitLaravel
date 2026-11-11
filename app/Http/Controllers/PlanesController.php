<?php

namespace App\Http\Controllers;

use App\Services\Planes\CatalogoPlanes;
use App\Services\Planes\PasarelaMercadoPago;
use App\Services\Planes\PasarelaStripe;
use App\Services\Planes\PlanOrganizador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;

/**
 * Página de planes, inicio del cobro y regreso de cada pasarela.
 */
class PlanesController extends Controller
{
    public function __construct(
        private CatalogoPlanes $catalogo,
        private PlanOrganizador $planes,
    ) {
    }

    public function index(Request $request)
    {
        return Inertia::render('Planes/Index', [
            'planes' => array_values(array_map(fn (array $p) => [
                'clave' => $p['clave'],
                'nombre' => $p['nombre'],
                'precio' => $p['precio'],
                'torneos' => $p['torneos'],
                'dias' => $p['dias'],
                'recurrente' => $p['recurrente'],
            ], $this->catalogo->todos())),
            'pasarelas' => $this->catalogo->pasarelasDisponibles(),
            'resultado' => $request->query('pago'),
            'cancelado' => $request->boolean('cancelado'),
            'motivo' => $request->query('motivo'),
            // Desde el menú de la cuenta: qué tarjeta resaltar ("mas" = otro pase Base).
            'quiero' => in_array($request->query('quiero'), ['mas', 'pro'], true) ? $request->query('quiero') : null,
            'graciaDias' => (int) config('planes.gracia_torneo_dias', 0),
        ]);
    }

    public function pagar(Request $request, string $plan, string $pasarela, PasarelaStripe $stripe, PasarelaMercadoPago $mercadoPago)
    {
        $user = $request->user();

        if (!$this->catalogo->existe($plan) || !in_array($pasarela, $this->catalogo->pasarelasDisponibles(), true)) {
            return $this->errorDePago(['pago' => 'Ese método de pago no está disponible.']);
        }

        $resumen = $this->planes->resumen($user);
        if ($resumen['exento']) {
            return $this->errorDePago(['pago' => 'Tu cuenta no necesita plan: tiene torneos ilimitados.']);
        }
        if ($resumen['ilimitado'] && $resumen['renueva']) {
            return $this->errorDePago(['pago' => 'Ya tienes Gestor Pro y se renueva solo cada mes.']);
        }
        if ($resumen['ilimitado'] && $plan === 'base') {
            return $this->errorDePago(['pago' => 'Ya tienes torneos ilimitados con Gestor Pro.']);
        }
        // Con la renovación cancelada la suscripción sigue viva hasta fin de mes:
        // otra compra abriría una segunda suscripción y se cobrarían las dos.
        if ($plan === 'pro' && collect($resumen['periodos'])->contains(fn (array $p) => $p['suscripcion'])) {
            return $this->errorDePago(['pago' => 'Ya tienes una suscripción a Gestor Pro: reanuda la renovación en vez de pagar otra.']);
        }

        try {
            $url = $pasarela === 'stripe'
                ? $stripe->iniciarCobro($user, $plan)
                : $mercadoPago->iniciarCobro($user, $plan);
        } catch (Throwable $e) {
            Log::error('Planes: no se pudo iniciar el cobro', ['pasarela' => $pasarela, 'plan' => $plan, 'error' => $e->getMessage()]);

            return $this->errorDePago(['pago' => 'No pudimos abrir el pago. Intenta de nuevo en un momento.']);
        }

        // Inertia::location hace la redirección completa a la pasarela.
        return Inertia::location($url);
    }

    public function retornoStripe(Request $request, PasarelaStripe $stripe)
    {
        $sessionId = (string) $request->query('session_id');
        $resultado = 'fallido';

        if ($sessionId !== '') {
            try {
                $resultado = $stripe->confirmarSesion($sessionId);
            } catch (Throwable $e) {
                // Si falla la consulta, el webhook lo activa después.
                Log::warning('Planes: no se pudo confirmar la sesión de Stripe', ['error' => $e->getMessage()]);
                $resultado = 'pendiente';
            }
        }

        return redirect()->route('planes.index', ['pago' => $resultado]);
    }

    public function retornoMercadoPago(Request $request, PasarelaMercadoPago $mercadoPago)
    {
        // Mercado Pago regresa con payment_id (o collection_id) y status.
        $pagoId = (string) ($request->query('payment_id') ?: $request->query('collection_id'));

        if ($pagoId === '' || $pagoId === 'null') {
            return redirect()->route('planes.index', ['cancelado' => 1]);
        }

        $resultado = $mercadoPago->procesarPago($pagoId);

        // Si no se pudo consultar, el webhook lo activa después.
        return redirect()->route('planes.index', ['pago' => $resultado === 'error' ? 'pendiente' : $resultado]);
    }

    public function cancelarRenovacion(Request $request, PasarelaStripe $stripe)
    {
        return $this->cambiarRenovacion($request, $stripe, false);
    }

    public function reanudarRenovacion(Request $request, PasarelaStripe $stripe)
    {
        return $this->cambiarRenovacion($request, $stripe, true);
    }

    private function cambiarRenovacion(Request $request, PasarelaStripe $stripe, bool $renovar)
    {
        $periodo = $this->planes->periodosVigentes($request->user())
            ->where('provider', 'stripe')
            ->whereNotNull('provider_subscription_id')
            ->sortByDesc('ends_at')
            ->first();

        if (!$periodo) {
            return back()->withErrors(['pago' => 'No tienes una suscripción con renovación automática.']);
        }

        try {
            $renovar
                ? $stripe->reanudarRenovacion($periodo->provider_subscription_id)
                : $stripe->cancelarRenovacion($periodo->provider_subscription_id);
        } catch (Throwable $e) {
            Log::error('Planes: no se pudo cambiar la renovación', ['error' => $e->getMessage()]);

            return back()->withErrors(['pago' => 'No pudimos actualizar tu suscripción. Intenta de nuevo.']);
        }

        return back();
    }

    /**
     * Los cobros también se abren desde el menú de la cuenta, el perfil y el
     * panel ("Recargar"); los errores se muestran en /planes, que es donde
     * se leen, y no en la página de donde vino.
     */
    private function errorDePago(array $errores)
    {
        return redirect()->route('planes.index')->withErrors($errores);
    }
}
