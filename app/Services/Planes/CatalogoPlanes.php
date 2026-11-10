<?php

namespace App\Services\Planes;

use InvalidArgumentException;

/**
 * Qué planes hay, cuánto cuestan y con qué pasarelas se pueden pagar.
 * Todo sale de config/planes.php.
 */
class CatalogoPlanes
{
    public const PASARELAS = ['stripe', 'mercadopago'];

    public function existe(string $plan): bool
    {
        return array_key_exists($plan, config('planes.planes', []));
    }

    public function plan(string $plan): array
    {
        $def = config("planes.planes.$plan");
        if (!$def) {
            throw new InvalidArgumentException("Plan desconocido: $plan");
        }

        return $def + ['clave' => $plan, 'precio' => $this->precio($plan)];
    }

    /** @return array<string, array> */
    public function todos(): array
    {
        $planes = [];
        foreach (array_keys(config('planes.planes', [])) as $clave) {
            $planes[$clave] = $this->plan($clave);
        }

        return $planes;
    }

    /**
     * Precio a cobrar (MXN, entero) para que quede el neto después de la
     * comisión y su IVA, con la pasarela más cara. Despeja P de:
     *   P − (P·pct + fijo)·(1 + iva) = neto
     */
    public function precio(string $plan): int
    {
        $neto = (float) config("planes.planes.$plan.neto");
        $iva = 1 + ((float) config('planes.iva_comision', 16)) / 100;

        $precio = $neto;
        foreach (config('planes.comisiones', []) as $comision) {
            $pct = ((float) $comision['porcentaje']) / 100;
            $fijo = (float) $comision['fijo'];
            $precio = max($precio, ($neto + $fijo * $iva) / (1 - $pct * $iva));
        }

        // round() evita que un 213.0000001 de punto flotante suba a 214.
        return (int) ceil(round($precio, 4));
    }

    /** Pasarelas con credenciales configuradas. */
    public function pasarelasDisponibles(): array
    {
        return array_values(array_filter([
            config('cashier.secret') ? 'stripe' : null,
            config('planes.mercadopago.activo') && config('planes.mercadopago.access_token') ? 'mercadopago' : null,
        ]));
    }

    /** ¿El plan se renueva solo con esta pasarela? */
    public function seRenueva(string $plan, string $pasarela): bool
    {
        return (bool) config("planes.planes.$plan.recurrente") && $pasarela === 'stripe';
    }
}
