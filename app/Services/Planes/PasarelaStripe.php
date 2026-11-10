<?php

namespace App\Services\Planes;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;

/**
 * Cobros con Stripe vía Cashier (ya instalado). Base es pago único (Stripe
 * ofrece tarjeta y OXXO si están activos en el dashboard); Pro es suscripción
 * mensual con tarjeta.
 *
 * Los precios van inline (price_data): no hay que dar de alta productos en
 * el dashboard de Stripe; cambiar config/planes.php basta.
 *
 * Los payloads de Stripe llegan como arreglos (webhook) o se convierten con
 * toArray() (consultas a la API), así que todo se maneja como arreglo.
 */
class PasarelaStripe
{
    public function __construct(
        private CatalogoPlanes $catalogo,
        private PlanOrganizador $planes,
    ) {
    }

    /** Crea la sesión de Checkout y devuelve la URL a donde mandar al usuario. */
    public function iniciarCobro(User $user, string $plan): string
    {
        $def = $this->catalogo->plan($plan);
        $recurrente = $this->catalogo->seRenueva($plan, 'stripe');
        $metadata = ['rankit_user_id' => (string) $user->id, 'rankit_plan' => $plan];

        $precio = [
            'currency' => 'mxn',
            'unit_amount' => $def['precio'] * 100,
            'product_data' => ['name' => $this->concepto($def)],
        ];
        if ($recurrente) {
            $precio['recurring'] = ['interval' => 'month'];
        }

        $opciones = [
            'mode' => $recurrente ? 'subscription' : 'payment',
            'success_url' => route('planes.retorno.stripe').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('planes.index').'?cancelado=1',
            'client_reference_id' => (string) $user->id,
            'metadata' => $metadata,
            'locale' => 'es-419',
        ];
        if ($recurrente) {
            // "type" es la llave con la que Cashier guarda la suscripción local.
            $opciones['subscription_data'] = ['metadata' => $metadata + ['type' => $plan]];
        } else {
            $opciones['payment_intent_data'] = ['metadata' => $metadata];
        }

        $checkout = $user->checkout([['price_data' => $precio, 'quantity' => 1]], $opciones);

        return $checkout->asStripeCheckoutSession()->url;
    }

    /**
     * Regreso del Checkout: activa el plan sin esperar el webhook. Devuelve
     * 'activo', 'pendiente' (OXXO sin pagar) o 'fallido'.
     */
    public function confirmarSesion(string $sessionId): string
    {
        $sesion = Cashier::stripe()->checkout->sessions->retrieve($sessionId)->toArray();

        return $this->procesarSesion($sesion);
    }

    /** Eventos del webhook de Cashier (ver App\Listeners\StripeWebhookPlanes). */
    public function procesarEvento(array $payload): void
    {
        $objeto = Arr::get($payload, 'data.object', []);

        match ($payload['type'] ?? null) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded' => $this->procesarSesion($objeto),
            'invoice.paid' => $this->registrarFactura($objeto),
            'customer.subscription.updated' => $this->sincronizarRenovacion($objeto),
            'customer.subscription.deleted' => $this->planes->marcarRenovacion($objeto['id'], false),
            'charge.refunded' => $this->revocarCargo($objeto),
            default => null,
        };
    }

    /** El plan Pro deja de renovarse; sigue activo hasta que termine el mes pagado. */
    public function cancelarRenovacion(string $suscripcion): void
    {
        Cashier::stripe()->subscriptions->update($suscripcion, ['cancel_at_period_end' => true]);
        $this->planes->marcarRenovacion($suscripcion, false);
    }

    public function reanudarRenovacion(string $suscripcion): void
    {
        Cashier::stripe()->subscriptions->update($suscripcion, ['cancel_at_period_end' => false]);
        $this->planes->marcarRenovacion($suscripcion, true);
    }

    private function procesarSesion(array $sesion): string
    {
        $userId = (int) Arr::get($sesion, 'metadata.rankit_user_id');
        $plan = (string) Arr::get($sesion, 'metadata.rankit_plan');

        // Sesiones que no son de planes (Cashier puede recibir otras).
        if (!$userId || !$this->catalogo->existe($plan)) {
            return 'fallido';
        }

        if (($sesion['payment_status'] ?? null) !== 'paid') {
            return ($sesion['status'] ?? null) === 'complete' ? 'pendiente' : 'fallido';
        }

        if (($sesion['mode'] ?? null) === 'subscription') {
            // El periodo sale de la factura (trae las fechas del mes cobrado).
            $facturaId = is_array($sesion['invoice'] ?? null) ? $sesion['invoice']['id'] : ($sesion['invoice'] ?? null);
            if (!$facturaId) {
                return 'pendiente';
            }
            $factura = Cashier::stripe()->invoices->retrieve($facturaId)->toArray();

            return $this->registrarFactura($factura, $userId) ? 'activo' : 'pendiente';
        }

        $this->planes->registrarPago(
            userId: $userId,
            plan: $plan,
            proveedor: 'stripe',
            referencia: 'stripe:'.$this->idPago($sesion),
            monto: ($sesion['amount_total'] ?? 0) / 100,
        );

        return 'activo';
    }

    /**
     * Cada factura pagada de la suscripción es un mes más de Pro (la primera
     * y las renovaciones automáticas).
     */
    private function registrarFactura(array $factura, ?int $userId = null): bool
    {
        if (($factura['status'] ?? null) !== 'paid') {
            return false;
        }

        // API 2025-03-31.basil en adelante: la suscripción vive en parent.
        $suscripcion = Arr::get($factura, 'parent.subscription_details.subscription')
            ?? ($factura['subscription'] ?? null);
        if (!$suscripcion) {
            return false; // factura suelta, no es de un plan
        }
        $metadata = Arr::get($factura, 'parent.subscription_details.metadata')
            ?? Arr::get($factura, 'subscription_details.metadata', []);

        $userId ??= (int) ($metadata['rankit_user_id'] ?? 0)
            ?: Cashier::findBillable($factura['customer'] ?? null)?->getKey();
        $plan = $metadata['rankit_plan'] ?? 'pro';

        if (!$userId || !$this->catalogo->existe($plan)) {
            Log::warning('Stripe: factura pagada sin usuario o plan de Rankit', ['factura' => $factura['id'] ?? null]);

            return false;
        }

        $linea = Arr::get($factura, 'lines.data.0.period', []);
        $inicio = isset($linea['start']) ? Carbon::createFromTimestamp($linea['start']) : null;
        $fin = isset($linea['end']) ? Carbon::createFromTimestamp($linea['end']) : null;

        $this->planes->registrarPago(
            userId: $userId,
            plan: $plan,
            proveedor: 'stripe',
            referencia: 'stripe:'.$factura['id'],
            monto: ($factura['amount_paid'] ?? 0) / 100,
            inicio: $inicio,
            fin: $fin,
            suscripcion: $suscripcion,
            renueva: true,
        );

        return true;
    }

    private function sincronizarRenovacion(array $suscripcion): void
    {
        $vivo = in_array($suscripcion['status'] ?? null, ['active', 'trialing'], true);

        $this->planes->marcarRenovacion(
            $suscripcion['id'],
            $vivo && !($suscripcion['cancel_at_period_end'] ?? false) && empty($suscripcion['cancel_at']),
        );
    }

    /** Reembolso total de un pase pagado con Stripe: se termina el periodo. */
    private function revocarCargo(array $cargo): void
    {
        if (!($cargo['refunded'] ?? false) || empty($cargo['payment_intent'])) {
            return;
        }

        $this->planes->revocarPago('stripe:'.$cargo['payment_intent']);
    }

    /** Pagos únicos: la referencia es el PaymentIntent (lo que reembolsa Stripe). */
    private function idPago(array $sesion): string
    {
        $intent = $sesion['payment_intent'] ?? null;

        return is_array($intent) ? $intent['id'] : ($intent ?: $sesion['id']);
    }

    private function concepto(array $def): string
    {
        return $def['torneos']
            ? "Rankit {$def['nombre']} — {$def['torneos']} torneos ({$def['dias']} días)"
            : "Rankit {$def['nombre']} — torneos ilimitados (mensual)";
    }
}
