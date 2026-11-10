<?php

/*
|--------------------------------------------------------------------------
| Planes de organizador
|--------------------------------------------------------------------------
|
| Quien no tenga un plan activo no entra a los paneles de administración
| (Fortnite, LoL/Valorant y fútbol). Ver app/Services/Planes/README.md.
|
| "neto" es lo que debe quedar después de comisiones: el precio que se cobra
| se calcula sumando la comisión de la pasarela más cara y su IVA, y se
| redondea hacia arriba al peso. Así el precio de la landing y el del cobro
| salen del mismo lugar.
|
*/

return [

    'planes' => [
        'base' => [
            'nombre' => 'Base',
            'neto' => (float) env('PLAN_BASE_NETO', 200),
            'torneos' => 2,
            'dias' => 30,
            // Pase de pago único: admite tarjeta, OXXO y SPEI.
            'recurrente' => false,
        ],
        'pro' => [
            'nombre' => 'Gestor Pro',
            'neto' => (float) env('PLAN_PRO_NETO', 800),
            'torneos' => null, // ilimitados
            'dias' => 30,
            // Con Stripe se renueva cada mes; con Mercado Pago es pase de 30 días.
            'recurrente' => true,
        ],
    ],

    // Comisión por cobro exitoso (cobro inmediato, tarjeta nacional). El IVA se
    // aplica sobre la comisión, no sobre el precio.
    'comisiones' => [
        'stripe' => ['porcentaje' => 3.6, 'fijo' => 3.00],
        'mercadopago' => ['porcentaje' => 3.49, 'fijo' => 4.00],
    ],
    'iva_comision' => 16,

    // Nunca se les agota el plan. Además de estos, cualquier superadmin.
    'exentos' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'PLANES_EXENTOS',
        '18jangel18@gmail.com,josephfabrizizo@gmail.com'
    ))))),

    // Al desplegar, los admins que ya tenían torneos reciben este plan gratis
    // (ver app/Services/Planes/CortesiaLanzamiento.php). 0 = desactivada.
    'cortesia_lanzamiento' => [
        'dias' => (int) env('PLANES_CORTESIA_DIAS', 30),
        'plan' => 'pro',
    ],

    // Apagado: por ahora solo se cobra con Stripe y /planes muestra Mercado
    // Pago como "Próximamente". Para prenderlo: MERCADOPAGO_ACTIVO=true + token.
    'mercadopago' => [
        'activo' => (bool) env('MERCADOPAGO_ACTIVO', false),
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
    ],

];
