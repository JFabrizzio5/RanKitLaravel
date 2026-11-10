<?php

namespace App\Services\Planes;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cobros con Mercado Pago (Checkout Pro). Siempre es pago único: Base y Pro
 * son pases de 30 días. Admite tarjeta, OXXO, SPEI y saldo de Mercado Pago.
 *
 * Se habla con la API REST directo (Http) en vez de su SDK para no sumar una
 * dependencia de Composer al hosting compartido.
 *
 * Nunca se confía en lo que trae la notificación: con el id se consulta el
 * pago a la API con nuestro token, y solo eso activa el plan.
 */
class PasarelaMercadoPago
{
    private const API = 'https://api.mercadopago.com';

    public function __construct(
        private CatalogoPlanes $catalogo,
        private PlanOrganizador $planes,
    ) {
    }

    public function iniciarCobro(User $user, string $plan): string
    {
        $def = $this->catalogo->plan($plan);
        $retorno = route('planes.retorno.mercadopago');

        $preferencia = [
            'items' => [[
                'id' => "rankit-$plan",
                'title' => $def['torneos']
                    ? "Rankit {$def['nombre']} — {$def['torneos']} torneos ({$def['dias']} días)"
                    : "Rankit {$def['nombre']} — torneos ilimitados ({$def['dias']} días)",
                'quantity' => 1,
                'unit_price' => (float) $def['precio'],
                'currency_id' => 'MXN',
            ]],
            'payer' => ['email' => $user->email],
            'external_reference' => $this->referenciaExterna($user, $plan),
            'metadata' => ['rankit_user_id' => $user->id, 'rankit_plan' => $plan],
            'back_urls' => ['success' => $retorno, 'failure' => $retorno, 'pending' => $retorno],
            'statement_descriptor' => 'RANKIT',
        ];

        // Mercado Pago rechaza auto_return y notification_url que no sean
        // https públicos (en local no hay ni uno ni otro).
        if (Str::startsWith($retorno, 'https://')) {
            $preferencia['auto_return'] = 'approved';
            $preferencia['notification_url'] = route('mercadopago.webhook');
        }

        $respuesta = $this->http()->post(self::API.'/checkout/preferences', $preferencia)->throw()->json();

        // Las credenciales de prueba viejas empiezan con TEST- y usan el sandbox.
        $url = Str::startsWith($this->token(), 'TEST-')
            ? ($respuesta['sandbox_init_point'] ?? null)
            : ($respuesta['init_point'] ?? null);

        if (!$url) {
            throw new RuntimeException('Mercado Pago no devolvió la liga de pago.');
        }

        return $url;
    }

    /**
     * Consulta el pago y activa o revoca el plan. Devuelve 'activo',
     * 'pendiente' (OXXO/SPEI sin pagar), 'revocado', 'fallido', o 'error' si
     * no se pudo consultar (el webhook responde 502 para que Mercado Pago
     * reintente).
     */
    public function procesarPago(string $pagoId): string
    {
        try {
            $pago = $this->http()->get(self::API.'/v1/payments/'.urlencode($pagoId))->throw()->json();
        } catch (ConnectionException) {
            return 'error';
        } catch (RequestException $e) {
            Log::warning('Mercado Pago: no se pudo consultar el pago', ['pago' => $pagoId, 'status' => $e->response?->status()]);

            return $e->response?->status() === 404 ? 'fallido' : 'error';
        }

        [$userId, $plan] = $this->leerReferencia($pago['external_reference'] ?? '');
        if (!$userId || !$this->catalogo->existe($plan)) {
            return 'fallido'; // pago que no es de un plan de Rankit
        }

        $referencia = 'mp:'.$pago['id'];

        return match ($pago['status'] ?? null) {
            'approved' => $this->activar($pago, $userId, $plan, $referencia),
            'pending', 'in_process', 'authorized' => 'pendiente',
            'refunded', 'charged_back', 'cancelled' => tap('revocado', fn () => $this->planes->revocarPago($referencia)),
            default => 'fallido',
        };
    }

    /**
     * Firma del webhook (cabecera x-signature). La seguridad real es consultar
     * el pago a la API; esto solo descarta notificaciones falsas sin gastar una
     * consulta. Si no hay secreto configurado, se omite.
     */
    public function firmaValida(Request $request, string $dataId): bool
    {
        $secreto = config('planes.mercadopago.webhook_secret');
        $firma = (string) $request->header('x-signature');
        if (!$secreto || $firma === '') {
            return true;
        }

        $partes = [];
        foreach (explode(',', $firma) as $parte) {
            [$llave, $valor] = array_pad(explode('=', trim($parte), 2), 2, '');
            $partes[$llave] = $valor;
        }
        if (empty($partes['ts']) || empty($partes['v1'])) {
            return false;
        }

        // Mercado Pago firma el id en minúsculas cuando es alfanumérico.
        $manifiesto = 'id:'.strtolower($dataId).';';
        if ($requestId = $request->header('x-request-id')) {
            $manifiesto .= 'request-id:'.$requestId.';';
        }
        $manifiesto .= 'ts:'.$partes['ts'].';';

        return hash_equals(hash_hmac('sha256', $manifiesto, $secreto), $partes['v1']);
    }

    private function activar(array $pago, int $userId, string $plan, string $referencia): string
    {
        // Que no se pueda activar un plan pagando menos (p. ej. una preferencia
        // vieja de cuando el precio era otro).
        $monto = (float) ($pago['transaction_amount'] ?? 0);
        if (($pago['currency_id'] ?? 'MXN') !== 'MXN' || $monto + 0.01 < $this->catalogo->precio($plan)) {
            Log::warning('Mercado Pago: monto o moneda no cuadran con el plan', ['pago' => $pago['id'], 'monto' => $monto, 'plan' => $plan]);

            return 'fallido';
        }

        $this->planes->registrarPago(
            userId: $userId,
            plan: $plan,
            proveedor: 'mercadopago',
            referencia: $referencia,
            monto: $monto,
        );

        return 'activo';
    }

    private function referenciaExterna(User $user, string $plan): string
    {
        return 'rankit:'.$user->id.':'.$plan.':'.Str::lower(Str::random(10));
    }

    /** @return array{0: int, 1: string} */
    private function leerReferencia(string $referencia): array
    {
        $partes = explode(':', $referencia);
        if (count($partes) !== 4 || $partes[0] !== 'rankit') {
            return [0, ''];
        }

        return [(int) $partes[1], $partes[2]];
    }

    private function http()
    {
        return Http::withToken($this->token())->acceptJson()->timeout(20);
    }

    private function token(): string
    {
        return (string) config('planes.mercadopago.access_token');
    }
}
