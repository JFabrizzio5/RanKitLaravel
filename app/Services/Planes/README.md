# Planes de organizador

Sin plan vigente no se entra a los paneles de organizador: Fortnite (`/admin/*`), LoL/Valorant (`/lol`), fútbol (`/football`) ni al selector de juego. Se manda a `/planes` para pagar.

**Por ahora solo se cobra con Stripe (tarjeta).** Mercado Pago está programado pero apagado: `/planes` lo muestra como "Próximamente". Para prenderlo: `MERCADOPAGO_ACTIVO=true` y `MERCADOPAGO_ACCESS_TOKEN`.

| Plan | Qué da | Cobro |
|---|---|---|
| **Base** | 2 torneos en 30 días | Pago único (Stripe: tarjeta u OXXO · Mercado Pago: tarjeta, OXXO, SPEI, saldo) |
| **Gestor Pro** | Torneos ilimitados por 30 días | Stripe: suscripción mensual con tarjeta, se renueva sola · Mercado Pago: pase de 30 días |

**Exentos** (nunca se les acaba): cualquier superadmin y los correos de `PLANES_EXENTOS` (por defecto `18jangel18@gmail.com,josephfabrizizo@gmail.com`). El rol `admin` ya **no** da acceso por sí solo.

**Cortesía de lanzamiento:** antes de esto el panel estaba abierto a cualquiera con cuenta. Para no cortar de golpe a quien ya organizaba, al desplegar los usuarios con rol `admin` que tienen al menos un torneo (en cualquiera de los tres paneles) reciben **Gestor Pro por 30 días** (`PLANES_CORTESIA_DIAS`, 0 la apaga). Se otorga una sola vez, cuando se crean las tablas en la primera visita después del deploy, así que los 30 días cuentan desde ahí (`CortesiaLanzamiento`). Al 2026-11-10 eran 6 cuentas: gamingmty, tamyaqz, franchescajimeneezz, igem19.avidalj, yaor134di y annialguilar01. Queda en el log como `Planes: cortesía de lanzamiento otorgada`.

⚠️ Cualquier cosa que cree esas tablas contra producción antes del deploy (un `tinker`, correr el código local con el `.env` real) arranca el conteo en ese momento.

## Precio

`config/planes.php` guarda el **neto** que queremos recibir (Base 200, Pro 800). El precio se calcula para que, con la pasarela más cara, quede ese neto después de la comisión y su IVA:

```
P − (P·pct + fijo)·1.16 = neto
```

Con Stripe (3.6 % + $3) y Mercado Pago (3.49 % + $4, cobro inmediato) da **Base $214** y **Pro $839**. La landing y el cobro leen el mismo cálculo (`CatalogoPlanes::precio`). Si cambia una comisión o el neto, se cambia la config y listo.

No incluye impuestos sobre la venta (IVA trasladado si se factura, retenciones de ISR/IVA que Mercado Pago aplica a personas físicas): eso depende del régimen fiscal y hay que revisarlo con el contador.

## Cómo está armado

| Archivo | Responsabilidad |
|---|---|
| `CatalogoPlanes` | Planes, precio y pasarelas configuradas |
| `PlanOrganizador` | Reglas: quién entra, cupo, registrar pagos (idempotente), revocar |
| `PasarelaStripe` | Checkout de Cashier, regreso del pago, eventos del webhook, cancelar/reanudar renovación |
| `PasarelaMercadoPago` | Checkout Pro por API REST (sin SDK), consulta del pago, firma del webhook |
| `Http/Middleware/RequierePlanOrganizador` | Cierra los paneles sin plan |
| `Listeners/StripeWebhookPlanes` | Engancha los planes al webhook de Cashier (`/stripe/webhook`) |
| `Http/Controllers/MercadoPagoWebhookController` | `/mercadopago/webhook` |
| `resources/js/Composables/usePlan.ts` | Textos del plan y cupo sumado de los pases, para el front |
| `resources/js/Components/MenuCuenta.vue` | Avatar con inicial en la barra (estilo Google): nombre, correo, plan y atajos |
| `resources/js/Components/TarjetaPlan.vue` | Estado del plan en el perfil y en `/planes` |

**Tablas** (`plan_periods`, `plan_period_tournaments`): se crean solas la primera vez, igual que `ensureDatabaseIsReady()`, porque en producción no se corren migraciones.

- Cada pago es un renglón de `plan_periods` con inicio y fin. `provider_payment_id` es único: si llegan el webhook y el regreso del checkout (o un webhook repetido), queda un solo periodo.
- Cada torneo creado se anota en `plan_period_tournaments`. Borrar el torneo **no** devuelve el cupo (en LoL y fútbol se puede borrar un torneo ya jugado). Las fases que crea "clasificar a la siguiente ronda" no cuentan.
- En el front el Base se presenta como **tickets de torneo** (1 ticket = 1 torneo): contador en la barra, el menú de la cuenta, el perfil, `/planes` y junto a "Crear torneo" (`ContadorTickets`, `PlanCupo`). **Recargar** = comprar otro Base: suma 2 tickets con su propio vencimiento de 30 días. El botón abre Stripe directo desde donde esté (`useRecarga`); si el cobro no se puede abrir, `pagar` manda el error a `/planes`, no a la página de origen. El resumen trae `recarga: {tickets, precio}` para pintar el botón.
- **Subir de Base a Gestor Pro** es una compra normal de Pro: desde ese momento es ilimitado y lo que quedaba del pase Base no se reembolsa ni se descuenta (`/planes` lo avisa debajo del botón). No hay prorrateo.
- Con la renovación de Pro cancelada, la suscripción de Stripe sigue viva hasta fin de mes: `/planes` ofrece **Reanudar renovación** y `pagar` rechaza abrir otra suscripción (si no, se cobrarían las dos).
- La cuenta **no se puede borrar** desde la app (no hay `DELETE /profile`) y el correo no se cambia desde el perfil: es con el que entra y al que llegan los recibos de Stripe.
- **Gracia por torneo** (`gracia_torneo_dias`, por defecto 30; `PLANES_GRACIA_TORNEO_DIAS=0` la apaga): cada torneo creado con un plan se puede seguir administrando 30 días después de crearlo aunque el plan venza antes. Usar los 2 torneos del Base **no** cierra el panel: solo impide crear un tercero. Crear siempre pide plan vigente. Los torneos de un pago reembolsado no dan gracia.
- Pasada la gracia el panel se cierra; las páginas públicas de los torneos siguen.
- **Avisos de vencimiento** (`aviso_vencimiento_dias`, por defecto 3; `PLANES_AVISO_VENCIMIENTO_DIAS=0` los apaga). Se avisa de tickets sin usar que vencen en esos días y del Gestor Pro que termina sin renovarse (cortesía, renovación cancelada). No se avisa si tiene ilimitados más allá de esa fecha. Regla en `PlanOrganizador::porVencer()`, con días de calendario en CDMX.
  - En la app: chip ámbar, recuadro flotante (`AvisoVencimiento.vue`, se cierra por día) y aviso en el menú y junto a "Crear torneo".
  - Por correo, una vez por periodo (`AvisosVencimiento`, tabla `plan_period_avisos` creada en el primer uso). Se manda sin cola porque producción no tiene worker. Si el correo falla no se marca, y se reintenta al día siguiente.
  - Disparo: `php artisan planes:avisar-vencimientos`, programado a las 10:00 CDMX. Como no hay cron confirmado en Hostinger, también lo despierta la primera visita GET de cada día (middleware `DespertarAvisosVencimiento`, después de responder).
  - El correo usa su propia plantilla (`resources/views/mail/plan-por-vencer.blade.php`) para no salir como "Laravel": producción tiene `APP_NAME=Laravel`.
- El plan se activa por dos vías: al regresar de la pasarela (se consulta el pago) y por webhook. Cualquiera de las dos basta.
- Reembolso o contracargo: Mercado Pago (`refunded`, `charged_back`) y Stripe (`charge.refunded` de un pase) terminan el periodo.
- El superadmin puede dar o quitar planes a mano desde **Usuarios** (`+ Base`, `+ Pro`, `Quitar`). Queda como proveedor `manual` con quién lo dio.

## Poner a cobrar

### Stripe

1. Cuenta de Stripe México verificada (RFC, identificación, CLABE).
2. **Settings → Payment methods**: activar OXXO si se quiere para el plan Base.
3. **Developers → Webhooks → Add endpoint**: `https://rankit.pro/stripe/webhook` con los eventos de `config('cashier.webhook.events')`:
   `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `invoice.paid`, `charge.refunded`, `customer.subscription.created|updated|deleted`, `customer.updated`, `customer.deleted`, `invoice.payment_action_required`, `invoice.payment_succeeded`, `payment_method.automatically_updated`.
   (O `php artisan cashier:webhook --url=https://rankit.pro/stripe/webhook` con la llave secreta ya puesta.)
4. `.env`: `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `CASHIER_CURRENCY=mxn`, `CASHIER_CURRENCY_LOCALE=es_MX`.

No hace falta dar de alta productos ni precios en Stripe: van inline en cada Checkout.

### Mercado Pago

1. **Tus integraciones → Crear aplicación** (Checkout Pro). Copiar el **Access Token** de producción.
2. En la aplicación, **Webhooks**: URL `https://rankit.pro/mercadopago/webhook`, evento **Pagos**. Copiar la **clave secreta**.
3. `.env`: `MERCADOPAGO_ACCESS_TOKEN`, `MERCADOPAGO_WEBHOOK_SECRET`.

Para probar se usan las credenciales de una **cuenta de prueba** (vendedor) y se paga con otra cuenta de prueba (comprador).

Una pasarela sin credenciales no aparece en `/planes`. Sin ninguna, `/planes` muestra el WhatsApp de ventas.

### Al desplegar

```bash
php artisan config:clear && php artisan route:clear
```

`APP_URL` debe ser `https://…`: Mercado Pago no acepta `auto_return` ni `notification_url` sin https.

## Pruebas

`tests/Feature/PlanesTest.php` (SQLite en memoria; aborta si la conexión no es sqlite). Correr siempre forzando SQLite, porque el `.env` local apunta a la base real:

```bash
env DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_HOST=127.0.0.1 DB_PORT=1 php vendor/bin/pest tests/Feature/PlanesTest.php
```

Los cobros reales (Checkout de Stripe y de Mercado Pago) no están probados end-to-end: faltan credenciales de prueba. Los webhooks y el regreso de pago sí, con payloads firmados y `Http::fake`.
