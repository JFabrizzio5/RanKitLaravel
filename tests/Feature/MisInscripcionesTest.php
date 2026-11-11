<?php

use App\Http\Controllers\Admin\TournamentParserController;
use App\Models\User;
use App\Services\Inscripciones\MisInscripciones;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Candado: estas pruebas usan RefreshDatabase. Jamás contra MySQL.
    expect(DB::connection()->getDriverName())->toBe('sqlite');

    // En producción las tablas de torneos se crean en el primer uso; aquí igual.
    (new TournamentParserController())->ensureDatabaseIsReady();
    $this->withoutVite();
    Mail::fake();
});

/** Torneo de Fortnite tal como lo deja el panel (tabla sin modelo). */
function torneoInscripciones(string $nombre, array $extra = []): int
{
    $dueno = User::factory()->create();

    return DB::table('tournaments')->insertGetId($extra + [
        'user_id' => $dueno->id,
        'name' => $nombre,
        'slug' => \Illuminate\Support\Str::slug($nombre),
        'registration_status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function inscribirFila(int $torneoId, array $datos): int
{
    return DB::table('tournament_registrations')->insertGetId($datos + [
        'tournament_id' => $torneoId,
        'player_name' => 'Jugador',
        'payment_status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** start_date la agrega SemanalesController en el primer uso; aquí se simula. */
function conFechaDeTorneo(): void
{
    if (!Schema::hasColumn('tournaments', 'start_date')) {
        Schema::table('tournaments', fn (Blueprint $t) => $t->dateTime('start_date')->nullable());
    }
}

// ---------------------------------------------------------------- registro

it('guarda user_id al inscribirse con sesión', function () {
    $jugador = User::factory()->create(['email' => 'jugador@rankit.test']);
    $torneo = torneoInscripciones('Copa Martes');

    $this->actingAs($jugador)
        ->postJson("/api/tournaments/{$torneo}/register", [
            'player_name' => 'ElPro',
            'whatsapp' => '5512345678',
            'email' => 'otro-correo@rankit.test',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    $fila = DB::table('tournament_registrations')->where('tournament_id', $torneo)->first();
    expect($fila->user_id)->toBe($jugador->id);
});

it('sin sesión la inscripción queda sin user_id, como antes', function () {
    $torneo = torneoInscripciones('Copa Abierta');

    $this->postJson("/api/tournaments/{$torneo}/register", [
        'player_name' => 'Invitado',
        'whatsapp' => '5598765432',
    ])->assertOk();

    expect(DB::table('tournament_registrations')->where('tournament_id', $torneo)->value('user_id'))->toBeNull();
});

it('guarda user_id al inscribirse a un semanal', function () {
    $jugador = User::factory()->create();

    // Entrar a /semanales crea los eventos (Semanal 2 tiene el registro abierto).
    $this->actingAs($jugador)->get('/semanales')->assertOk();
    $semanal = DB::table('tournaments')->where('slug', 'semanal-2')->first();

    $this->actingAs($jugador)
        ->postJson("/semanales/{$semanal->id}/registro", [
            'player_name' => 'ElPro',
            'epic_id' => 'ElPro#1234',
            'whatsapp' => '5512345678',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(DB::table('tournament_registrations')->where('tournament_id', $semanal->id)->value('user_id'))
        ->toBe($jugador->id);
});

it('agrega user_id en el primer uso si la tabla es vieja', function () {
    Schema::table('tournament_registrations', function (Blueprint $t) {
        $t->dropIndex(['user_id']);
        $t->dropColumn('user_id');
    });
    expect(Schema::hasColumn('tournament_registrations', 'user_id'))->toBeFalse();

    app(MisInscripciones::class)->de(User::factory()->create());

    expect(Schema::hasColumn('tournament_registrations', 'user_id'))->toBeTrue();
});

// ---------------------------------------------------------------- /mis-torneos

it('lista lo inscrito por user_id y por correo sin importar mayúsculas', function () {
    $jugador = User::factory()->create(['email' => 'Jugador@Rankit.test']);

    // Con la cuenta pero con otro correo en el formulario.
    inscribirFila(torneoInscripciones('Copa Cuenta'), ['user_id' => $jugador->id, 'email' => 'otro@rankit.test']);
    // Vieja, sin user_id: solo el correo (escrito distinto) la liga.
    inscribirFila(torneoInscripciones('Copa Correo'), ['email' => ' jugador@RANKIT.test ', 'payment_status' => 'paid']);

    $this->actingAs($jugador)
        ->get('/mis-torneos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MisTorneos')
            ->has('torneos', 2)
            ->where('torneos', fn ($torneos) => collect($torneos)->pluck('nombre')->sort()->values()->all() === ['Copa Correo', 'Copa Cuenta'])
        );
});

it('no lista inscripciones de otros', function () {
    $jugador = User::factory()->create(['email' => 'yo@rankit.test']);
    $otro = User::factory()->create(['email' => 'otro@rankit.test']);

    inscribirFila(torneoInscripciones('Copa Ajena'), ['user_id' => $otro->id, 'email' => 'otro@rankit.test']);
    inscribirFila(torneoInscripciones('Copa Invitado'), ['email' => '5512345678@guest.com']);

    $this->actingAs($jugador)
        ->get('/mis-torneos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('MisTorneos')->has('torneos', 0));
});

it('sin sesión /mis-torneos manda a iniciar sesión', function () {
    $this->get('/mis-torneos')->assertRedirect(route('login'));
});

it('pone primero los próximos (el más cercano arriba) y luego los pasados', function () {
    conFechaDeTorneo();
    $jugador = User::factory()->create();
    $dia = fn (int $n) => now('America/Mexico_City')->addDays($n)->toDateString();

    $lejano = torneoInscripciones('En 10 dias', ['start_date' => $dia(10)]);
    $cercano = torneoInscripciones('En 2 dias', ['start_date' => $dia(2)]);
    $viejo = torneoInscripciones('Hace 5 dias', ['start_date' => $dia(-5)]);
    $jugado = torneoInscripciones('Jugado hace 3 dias');
    $sinFecha = torneoInscripciones('Sin fecha');

    // Sin fecha programada, cuenta el día de la última partida con replay.
    DB::table('tournament_matches')->insert([
        'tournament_id' => $jugado,
        'match_id' => 'm-1',
        'raw_data' => '{}',
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ]);

    foreach ([$lejano, $cercano, $viejo, $jugado, $sinFecha] as $id) {
        inscribirFila($id, ['user_id' => $jugador->id, 'email' => $jugador->email]);
    }

    $torneos = app(MisInscripciones::class)->de($jugador);

    expect(array_column($torneos, 'nombre'))->toBe(['En 2 dias', 'En 10 dias', 'Sin fecha', 'Jugado hace 3 dias', 'Hace 5 dias'])
        ->and(array_column($torneos, 'proximo'))->toBe([true, true, true, false, false])
        ->and($torneos[3]['fecha_tipo'])->toBe('jugada')
        ->and($torneos[0]['url'])->toBe("/t/{$cercano}");
});

it('muestra una sola tarjeta por torneo y prefiere la confirmada', function () {
    $jugador = User::factory()->create();
    $torneo = torneoInscripciones('Copa Doble');

    inscribirFila($torneo, ['user_id' => $jugador->id, 'email' => 'otro@rankit.test', 'payment_status' => 'paid', 'created_at' => now()->subDay()]);
    inscribirFila($torneo, ['email' => $jugador->email, 'payment_status' => 'pending']);

    $torneos = app(MisInscripciones::class)->de($jugador);

    expect($torneos)->toHaveCount(1)
        ->and($torneos[0]['estado'])->toBe('paid');
});

it('el dashboard usa la misma lista', function () {
    $jugador = User::factory()->create();
    inscribirFila(torneoInscripciones('Copa Dashboard'), ['user_id' => $jugador->id, 'email' => 'otro@rankit.test']);

    $this->actingAs($jugador)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('misTorneos', 1)
            ->where('misTorneos.0.nombre', 'Copa Dashboard')
            ->where('misTorneos.0.url', fn ($url) => str_starts_with($url, '/t/'))
        );
});
