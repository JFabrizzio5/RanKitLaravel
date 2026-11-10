<?php

namespace App\Http\Controllers;

use App\Services\Planes\PasarelaMercadoPago;
use Illuminate\Http\Request;

/**
 * Notificaciones de Mercado Pago. Llegan como webhook (?data.id=…&type=payment,
 * o en el cuerpo) o como IPN viejo (?id=…&topic=payment). PHP convierte el
 * punto de "data.id" en guion bajo, por eso se busca data_id.
 */
class MercadoPagoWebhookController extends Controller
{
    public function __invoke(Request $request, PasarelaMercadoPago $mercadoPago)
    {
        $tipo = $request->query('type') ?? $request->query('topic') ?? $request->input('type') ?? $request->input('topic');
        $id = (string) ($request->query('data_id') ?? $request->input('data.id') ?? $request->query('id') ?? '');

        if ($tipo !== 'payment' || $id === '') {
            return response()->noContent(); // merchant_order y demás no nos interesan
        }

        if (!$mercadoPago->firmaValida($request, $id)) {
            return response()->json(['message' => 'Firma inválida'], 401);
        }

        if ($mercadoPago->procesarPago($id) === 'error') {
            return response()->json(['message' => 'Reintentar'], 502);
        }

        return response()->noContent();
    }
}
