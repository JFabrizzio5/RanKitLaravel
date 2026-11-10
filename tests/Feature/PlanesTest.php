<?php

use App\Models\PlanPeriod;
use App\Models\User;
use App\Services\Planes\CatalogoPlanes;
use App\Services\Planes\PlanOrganizador;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Candado: estas pruebas usan RefreshDatabase. Jamás contra MySQL.
    expect(DB::connection()->getDriverName())->toBe('sqlite');

    // En producción se crean en el primer uso; aquí las pruebas cuentan
    // renglones aunque nadie haya pagado todavía.
    PlanOrganizador::asegurarTablas();
    $this->withoutVite();
    config([
        'planes.exentos' => ['18jangel18@gmail.com', 'josephfabrizizo@gmail.com'],
        'planes.mercadopago.access_token' => null,
        'planes.mercadopago.webhook_secret' => null,
        'cashier.secret' => null,
        'cashier.webhook.secret' => null,
    ]);
});

function organizador(array $attrs = []): User
{
    return User::factory()->create($attrs + ['role' => 'organizer']);
}

function darPlan(User $user, string $plan = 'base', int $dias = 30, string $ref = null): PlanPeriod
{
    return app(PlanOrganizador::class)->registrarPago(
        userId: $user->id,
        plan: $plan,
        proveedor: 'manual',
        referencia: $ref ?? 'manual:'.uniqid(),
        monto: 0,
        fin: now()->addDays($dias),
    );
}

function crearTorneoFortnite(User $user, string $nombre = 'Copa')
{
    return test()->actingAs($user)->from('/admin/jangel')->post('/admin/tournaments', ['name' => $nombre]);
}

function firmaStripe(string $payload, string $secreto): string
{
    $t = time();

    return "t=$t,v1=".hash_hmac('sha256', "$t.$payload", $secreto);
}

// ---------------------------------------------------------------- precios

it('cobra lo necesario para que queden 200 y 800 netos después de comisión', function () {
    $catalogo = app(CatalogoPlanes::class);

    expect($catalogo->precio('base'))->toBe(214)
        ->and($catalogo->precio('pro'))->toBe(839);

    // Con cualquiera de las dos pasarelas queda al menos el neto.
    foreach (config('planes.comisiones') as $c) {
        $neto = fn (int $p) => $p - ($p * $c['porcentaje'] / 100 + $c['fijo']) * 1.16;
        expect($neto(214))->toBeGreaterThanOrEqual(200)
            ->and($neto(839))->toBeGreaterThanOrEqual(800);
    }
});

// ---------------------------------------------------------------- acceso

it('manda a /planes a quien no tiene plan', function (string $ruta) {
    $this->actingAs(organizador())->get($ruta)->assertRedirect('/planes?motivo=sin-plan');
})->with(['/admin/jangel', '/lol', '/football', '/game-selector']);

it('responde 403 en JSON a quien no tiene plan', function () {
    $this->actingAs(organizador())
        ->getJson('/admin/tournaments/1/registrations')
        ->assertForbidden()
        ->assertJsonPath('planes', route('planes.index'));
});

it('deja entrar sin plan a jangel y a josephfabrizizo', function (string $email) {
    $this->actingAs(organizador(['email' => $email]))->get('/admin/jangel')->assertOk();
})->with(['18jangel18@gmail.com', 'josephfabrizizo@gmail.com']);

it('a un admin hecho a mano ya no lo deja entrar sin plan', function () {
    $this->actingAs(organizador(['role' => 'admin']))->get('/admin/jangel')->assertRedirect('/planes?motivo=sin-plan');
});

it('cierra el admin cuando vence el plan', function () {
    $user = organizador();
    $periodo = darPlan($user);
    $this->actingAs($user)->get('/admin/jangel')->assertOk();

    $this->travelTo($periodo->ends_at->copy()->addMinute());
    $this->actingAs($user)->get('/admin/jangel')->assertRedirect('/planes?motivo=sin-plan');
});

// ---------------------------------------------------------------- cupo

it('el plan Base deja crear 2 torneos y el tercero no', function () {
    $user = organizador();
    darPlan($user, 'base');

    crearTorneoFortnite($user, 'Uno')->assertRedirect('/admin/jangel')->assertSessionHasNoErrors();
    crearTorneoFortnite($user, 'Dos')->assertSessionHasNoErrors();
    crearTorneoFortnite($user, 'Tres')->assertSessionHasErrors('plan');

    expect(DB::table('tournaments')->where('user_id', $user->id)->count())->toBe(2);
    expect(app(PlanOrganizador::class)->resumen($user)['torneos_restantes'])->toBe(0);
});

it('el cupo se comparte entre Fortnite, fútbol y LoL', function () {
    $user = organizador();
    darPlan($user, 'base');

    crearTorneoFortnite($user)->assertSessionHasNoErrors();
    $this->actingAs($user)->post('/football', ['name' => 'Liga', 'format' => 'league'])->assertSessionHasNoErrors();
    $this->actingAs($user)->from('/lol')->post('/lol', ['name' => 'Cup', 'game' => 'lol', 'format' => 'elimination'])
        ->assertSessionHasErrors('plan');
});

it('borrar un torneo no devuelve el cupo', function () {
    $user = organizador();
    darPlan($user, 'base');
    crearTorneoFortnite($user, 'Uno');
    crearTorneoFortnite($user, 'Dos');

    $id = DB::table('tournaments')->where('user_id', $user->id)->value('id');
    $this->actingAs($user)->delete("/admin/tournaments/$id");

    crearTorneoFortnite($user, 'Tres')->assertSessionHasErrors('plan');
});

it('un segundo pase Base suma 2 torneos más', function () {
    $user = organizador();
    darPlan($user, 'base');
    crearTorneoFortnite($user, 'Uno');
    crearTorneoFortnite($user, 'Dos');
    darPlan($user, 'base');

    crearTorneoFortnite($user, 'Tres')->assertSessionHasNoErrors();
});

it('Gestor Pro no tiene límite y los exentos tampoco', function () {
    $pro = organizador();
    darPlan($pro, 'pro');
    foreach (range(1, 4) as $i) {
        crearTorneoFortnite($pro, "Pro $i")->assertSessionHasNoErrors();
    }

    $jangel = organizador(['email' => '18jangel18@gmail.com']);
    foreach (range(1, 3) as $i) {
        crearTorneoFortnite($jangel, "J $i")->assertSessionHasNoErrors();
    }

    expect(DB::table('tournaments')->count())->toBe(7);
});

it('registrar el mismo pago dos veces deja un solo periodo', function () {
    $user = organizador();
    darPlan($user, 'base', ref: 'mp:999');
    darPlan($user, 'base', ref: 'mp:999');

    expect(PlanPeriod::count())->toBe(1);
});

// ---------------------------------------------------------------- Mercado Pago

function pagoMp(User $user, array $cambios = []): array
{
    return $cambios + [
        'id' => 123456,
        'status' => 'approved',
        'currency_id' => 'MXN',
        'transaction_amount' => 214,
        'external_reference' => "rankit:{$user->id}:base:abc123defg",
    ];
}

it('el webhook de Mercado Pago activa el plan consultando el pago', function () {
    config(['planes.mercadopago.access_token' => 'APP_USR-prueba']);
    $user = organizador();
    Http::fake(['api.mercadopago.com/v1/payments/123456' => Http::response(pagoMp($user))]);

    $this->postJson('/mercadopago/webhook?data.id=123456&type=payment', ['type' => 'payment', 'data' => ['id' => '123456']])
        ->assertNoContent();
    $this->postJson('/mercadopago/webhook?data.id=123456&type=payment')->assertNoContent(); // repetido

    expect(PlanPeriod::where('user_id', $user->id)->count())->toBe(1);
    $periodo = PlanPeriod::first();
    expect($periodo->provider)->toBe('mercadopago')
        ->and($periodo->tournaments_limit)->toBe(2)
        ->and($periodo->provider_payment_id)->toBe('mp:123456');
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer APP_USR-prueba'));
});

it('Mercado Pago: no activa si pagaron menos que el precio', function () {
    config(['planes.mercadopago.access_token' => 'APP_USR-prueba']);
    $user = organizador();
    Http::fake(['api.mercadopago.com/*' => Http::response(pagoMp($user, ['transaction_amount' => 50]))]);

    $this->postJson('/mercadopago/webhook?data.id=123456&type=payment')->assertNoContent();

    expect(PlanPeriod::count())->toBe(0);
});

it('Mercado Pago: un reembolso termina el plan', function () {
    config(['planes.mercadopago.access_token' => 'APP_USR-prueba']);
    $user = organizador();
    Http::fakeSequence('api.mercadopago.com/*')
        ->push(pagoMp($user))
        ->push(pagoMp($user, ['status' => 'refunded']));

    $this->postJson('/mercadopago/webhook?data.id=123456&type=payment');
    expect(app(PlanOrganizador::class)->puedeEntrar($user))->toBeTrue();

    $this->postJson('/mercadopago/webhook?data.id=123456&type=payment');
    expect(app(PlanOrganizador::class)->puedeEntrar($user->fresh()))->toBeFalse();
});

it('Mercado Pago: valida la firma cuando hay secreto', function () {
    config(['planes.mercadopago.access_token' => 'APP_USR-prueba', 'planes.mercadopago.webhook_secret' => 'secreto']);
    $user = organizador();
    Http::fake(['api.mercadopago.com/*' => Http::response(pagoMp($user))]);

    $ts = (string) time();
    $v1 = hash_hmac('sha256', "id:123456;request-id:req-1;ts:$ts;", 'secreto');

    $this->postJson('/mercadopago/webhook?data.id=123456&type=payment', [], ['x-signature' => "ts=$ts,v1=malo", 'x-request-id' => 'req-1'])
        ->assertStatus(401);
    $this->postJson('/mercadopago/webhook?data.id=123456&type=payment', [], ['x-signature' => "ts=$ts,v1=$v1", 'x-request-id' => 'req-1'])
        ->assertNoContent();

    expect(PlanPeriod::count())->toBe(1);
});

it('Mercado Pago: si su API falla responde 502 para que reintente', function () {
    config(['planes.mercadopago.access_token' => 'APP_USR-prueba']);
    Http::fake(['api.mercadopago.com/*' => Http::response(['message' => 'boom'], 500)]);

    $this->postJson('/mercadopago/webhook?data.id=123456&type=payment')->assertStatus(502);
});

it('Mercado Pago: el regreso del pago activa el plan', function () {
    config(['planes.mercadopago.access_token' => 'APP_USR-prueba']);
    $user = organizador();
    Http::fake(['api.mercadopago.com/*' => Http::response(pagoMp($user))]);

    $this->actingAs($user)->get('/planes/retorno/mercadopago?payment_id=123456&status=approved')
        ->assertRedirect('/planes?pago=activo');
});

it('Mercado Pago: pagar abre Checkout Pro con el precio calculado', function () {
    config(['planes.mercadopago.access_token' => 'APP_USR-prueba']);
    $user = organizador();
    Http::fake(['api.mercadopago.com/checkout/preferences' => Http::response([
        'init_point' => 'https://www.mercadopago.com.mx/checkout/v1/redirect?pref_id=1',
    ], 201)]);

    $this->actingAs($user)->post('/planes/base/pagar/mercadopago')
        ->assertRedirect('https://www.mercadopago.com.mx/checkout/v1/redirect?pref_id=1');

    Http::assertSent(fn ($r) => $r['items'][0]['unit_price'] == 214
        && str_starts_with($r['external_reference'], "rankit:{$user->id}:base:"));
});

it('no deja pagar con una pasarela sin credenciales', function () {
    $this->actingAs(organizador())->from('/planes')->post('/planes/base/pagar/stripe')
        ->assertSessionHasErrors('pago');
});

// ---------------------------------------------------------------- Stripe

it('Stripe: checkout.session.completed de un pase activa el plan', function () {
    config(['cashier.webhook.secret' => 'whsec_prueba']);
    $user = organizador();

    $payload = json_encode([
        'id' => 'evt_1',
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => 'cs_1',
            'object' => 'checkout.session',
            'mode' => 'payment',
            'status' => 'complete',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_1',
            'amount_total' => 21400,
            'metadata' => ['rankit_user_id' => (string) $user->id, 'rankit_plan' => 'base'],
        ]],
    ]);

    $this->call('POST', '/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => firmaStripe($payload, 'whsec_prueba'),
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    $periodo = PlanPeriod::firstOrFail();
    expect($periodo->provider_payment_id)->toBe('stripe:pi_1')
        ->and($periodo->amount)->toBe(214.0)
        ->and($periodo->tournaments_limit)->toBe(2);
});

it('Stripe: OXXO sin pagar no activa; se activa con async_payment_succeeded', function () {
    config(['cashier.webhook.secret' => 'whsec_prueba']);
    $user = organizador();
    $sesion = [
        'id' => 'cs_2', 'object' => 'checkout.session', 'mode' => 'payment', 'status' => 'complete',
        'payment_status' => 'unpaid', 'payment_intent' => 'pi_2', 'amount_total' => 21400,
        'metadata' => ['rankit_user_id' => (string) $user->id, 'rankit_plan' => 'base'],
    ];
    $enviar = function (string $tipo, array $obj) {
        $payload = json_encode(['id' => 'evt_'.uniqid(), 'type' => $tipo, 'data' => ['object' => $obj]]);

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => firmaStripe($payload, 'whsec_prueba'),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    };

    $enviar('checkout.session.completed', $sesion)->assertOk();
    expect(PlanPeriod::count())->toBe(0);

    $enviar('checkout.session.async_payment_succeeded', ['payment_status' => 'paid'] + $sesion)->assertOk();
    expect(PlanPeriod::count())->toBe(1);
});

it('Stripe: cada factura pagada de la suscripción es un mes de Pro', function () {
    config(['cashier.webhook.secret' => 'whsec_prueba']);
    $user = organizador();
    $inicio = now()->startOfMinute();

    $payload = json_encode([
        'id' => 'evt_3',
        'type' => 'invoice.paid',
        'data' => ['object' => [
            'id' => 'in_1',
            'object' => 'invoice',
            'status' => 'paid',
            'customer' => 'cus_1',
            'amount_paid' => 83900,
            'parent' => ['subscription_details' => [
                'subscription' => 'sub_1',
                'metadata' => ['rankit_user_id' => (string) $user->id, 'rankit_plan' => 'pro', 'type' => 'pro'],
            ]],
            'lines' => ['data' => [['period' => [
                'start' => $inicio->timestamp,
                'end' => $inicio->copy()->addMonth()->timestamp,
            ]]]],
        ]],
    ]);

    $this->call('POST', '/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => firmaStripe($payload, 'whsec_prueba'),
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    $periodo = PlanPeriod::firstOrFail();
    expect($periodo->tournaments_limit)->toBeNull()
        ->and($periodo->renews)->toBeTrue()
        ->and($periodo->provider_subscription_id)->toBe('sub_1')
        ->and($periodo->ends_at->timestamp)->toBe($inicio->copy()->addMonth()->timestamp);

    $resumen = app(PlanOrganizador::class)->resumen($user);
    expect($resumen['ilimitado'])->toBeTrue()->and($resumen['renueva'])->toBeTrue();
});

it('Stripe: rechaza webhooks sin firma válida', function () {
    config(['cashier.webhook.secret' => 'whsec_prueba']);
    $payload = json_encode(['id' => 'evt_4', 'type' => 'invoice.paid', 'data' => ['object' => []]]);

    $this->call('POST', '/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => firmaStripe($payload, 'otro_secreto'),
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertForbidden();
});

// ---------------------------------------------------------------- superadmin

it('el superadmin activa y quita planes a mano', function () {
    $jangel = organizador(['email' => '18jangel18@gmail.com']);
    $user = organizador();

    $this->actingAs($jangel)->post("/admin/users/{$user->id}/plan", ['plan' => 'base', 'dias' => 15])->assertRedirect();
    $periodo = PlanPeriod::firstOrFail();
    expect($periodo->provider)->toBe('manual')
        ->and($periodo->notes)->toContain('18jangel18@gmail.com')
        ->and((int) round(now()->diffInDays($periodo->ends_at)))->toBe(15);

    $this->actingAs($jangel)->delete("/admin/users/{$user->id}/plan")->assertRedirect();
    expect(app(PlanOrganizador::class)->puedeEntrar($user))->toBeFalse();
});

it('un organizador con plan no puede regalarse planes', function () {
    $user = organizador();
    darPlan($user);

    $this->actingAs($user)->post("/admin/users/{$user->id}/plan", ['plan' => 'pro', 'dias' => 365])->assertForbidden();
});

// ---------------------------------------------------------------- lanzamiento

it('al crear las tablas da 30 días de Pro a los admins que ya tenían torneos', function () {
    // Como en producción: las tablas de planes aún no existen al desplegar.
    Schema::dropIfExists('plan_period_tournaments');
    Schema::dropIfExists('plan_periods');
    app(\App\Http\Controllers\Admin\TournamentParserController::class)->ensureDatabaseIsReady();

    $conTorneo = organizador(['role' => 'admin']);
    $sinTorneo = organizador(['role' => 'admin']);
    $exento = organizador(['role' => 'admin', 'email' => 'josephfabrizizo@gmail.com']);
    $organizer = organizador();
    foreach ([$conTorneo, $exento, $organizer] as $u) {
        DB::table('tournaments')->insert([
            'user_id' => $u->id, 'name' => 'Viejo', 'table_name' => 'viejo_'.$u->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    PlanOrganizador::asegurarTablas();
    PlanOrganizador::asegurarTablas(); // la segunda vez ya no hace nada

    expect(PlanPeriod::pluck('user_id')->all())->toBe([$conTorneo->id]);
    $periodo = PlanPeriod::first();
    expect($periodo->plan)->toBe('pro')
        ->and($periodo->tournaments_limit)->toBeNull()
        ->and($periodo->provider_payment_id)->toBe('cortesia-lanzamiento:'.$conTorneo->id)
        ->and((int) round(now()->diffInDays($periodo->ends_at)))->toBe(30);

    $this->actingAs($conTorneo)->get('/admin/jangel')->assertOk();
    $this->actingAs($sinTorneo)->get('/admin/jangel')->assertRedirect('/planes?motivo=sin-plan');
    $this->actingAs($organizer)->get('/admin/jangel')->assertRedirect('/planes?motivo=sin-plan');
});

it('la cortesía se apaga con PLANES_CORTESIA_DIAS=0', function () {
    config(['planes.cortesia_lanzamiento.dias' => 0]);
    Schema::dropIfExists('plan_period_tournaments');
    Schema::dropIfExists('plan_periods');
    app(\App\Http\Controllers\Admin\TournamentParserController::class)->ensureDatabaseIsReady();

    $admin = organizador(['role' => 'admin']);
    DB::table('tournaments')->insert([
        'user_id' => $admin->id, 'name' => 'Viejo', 'table_name' => 'viejo',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    PlanOrganizador::asegurarTablas();

    expect(PlanPeriod::count())->toBe(0);
});
