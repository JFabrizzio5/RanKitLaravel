<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    expect(DB::connection()->getDriverName())->toBe('sqlite');
    $this->withoutVite();
    // Crea las tablas de torneos en el primer request (esquema perezoso).
    $this->getJson('/api/league/standings')->assertOk();
});

function torneoPublico(array $attrs = []): int
{
    $user = User::factory()->create();

    return DB::table('tournaments')->insertGetId($attrs + [
        'user_id' => $user->id,
        'name' => 'Copa de prueba',
        'is_private' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('/torneos lista los públicos, primero los abiertos, sin privados ni fechas hijas ni códigos', function () {
    $jugado = torneoPublico(['name' => 'Jugado', 'slug' => 'jugado']);
    $abierto = torneoPublico(['name' => 'Abierto', 'registration_status' => 'open']);
    torneoPublico(['name' => 'Privado', 'is_private' => true, 'access_code' => 'secreto-xyz']);
    torneoPublico(['name' => 'Fecha hija', 'parent_tournament_id' => $jugado]);

    $respuesta = $this->get('/torneos')->assertOk();

    $respuesta->assertInertia(fn (Assert $p) => $p
        ->component('Torneos/Index')
        ->has('torneos', 2)
        ->where('torneos.0.nombre', 'Abierto')
        ->where('torneos.0.estado', 'abierto')
        ->where('torneos.0.url', "/t/{$abierto}")
        ->where('torneos.1.url', '/t/jugado')
    );
    expect($respuesta->getContent())->not->toContain('secreto-xyz');
});

it('/tournaments (enlace viejo) manda a /torneos', function () {
    $this->get('/tournaments')->assertRedirect('/torneos');
});

it('el widget de OBS ya no pone el logo de BellzCup en cualquier torneo', function () {
    $id = torneoPublico(['name' => 'Liga del barrio']);

    $this->get("/widget/obs/global/{$id}")->assertOk()->assertInertia(fn (Assert $p) => $p
        ->component('Widgets/ObsGlobal')
        ->where('torneo.nombre', 'Liga del barrio')
        ->where('torneo.logo', null)
    );
});

it('el widget usa el logo que subió el torneo y BellzCup conserva el suyo', function () {
    $conLogo = torneoPublico(['name' => 'Copa con logo', 'logo_image' => 'torneos/logo_1.png']);
    $bellz = torneoPublico(['name' => 'BellzCup 2026']);

    $this->get("/widget/obs/global/{$conLogo}")->assertInertia(fn (Assert $p) => $p
        ->where('torneo.logo', asset('public/torneos/logo_1.png'))
    );
    $this->get("/widget/obs/global/{$bellz}")->assertInertia(fn (Assert $p) => $p
        ->where('torneo.logo', asset('public/BellzCup.png'))
    );
});

it('la página para personalizar el widget abre y da 404 si el torneo no existe', function () {
    $id = torneoPublico();

    $this->get("/widget/obs/{$id}/personalizar")->assertOk()->assertInertia(fn (Assert $p) => $p
        ->component('Widgets/Personalizar')
        ->where('tournamentId', $id)
    );
    $this->get('/widget/obs/999999/personalizar')->assertNotFound();
});

it('la página de inicio es la landing de ventas, sin el prefetch de toda la app', function () {
    $respuesta = $this->get('/')->assertOk();

    $respuesta->assertInertia(fn ($p) => $p
        ->component('Propuestas/LandingK')
        ->has('precioBase')
        ->has('precioPro')
        ->has('eventos')
        ->has('seo'));
    // Vite::prefetch bajaría los chunks de toda la app al cargar la landing.
    expect($respuesta->getContent())->not->toContain('rel":"prefetch"');
});

it('la API del widget manda la posición real y los totales como números', function () {
    (new \App\Http\Controllers\Admin\TournamentParserController())->ensureDatabaseIsReady();
    $id = torneoPublico();
    // Tres jugadores en dos partidas: Ana 2 kills, Beto 5, Caro 9 (con sus puntos).
    foreach (['m1', 'm2'] as $m) {
        $partida = DB::table('tournament_matches')->insertGetId([
            'tournament_id' => $id, 'match_id' => "w-{$m}", 'game_mode' => 'solo', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([['Ana', 1, 10.25], ['Beto', 2, 20], ['Caro', 4, 30]] as [$nombre, $kills, $pts]) {
            DB::table('player_match_stats')->insert([
                'tournament_match_id' => $partida, 'player_name' => $nombre, 'placement' => 3, 'kills' => $kills,
                'extra_stats' => json_encode(['totalPoints' => $pts]), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    $filas = $this->getJson("/api/widget/{$id}/stats?type=players&sort=points&limit=10")->assertOk()->json();
    expect(array_column($filas, 'player_name'))->toBe(['Caro', 'Beto', 'Ana'])
        ->and(array_column($filas, 'rank'))->toBe([1, 2, 3])
        ->and($filas[2]['total_points'])->toBe(20.5)
        ->and($filas[2]['total_kills'])->toBe(2)
        ->and($filas[2]['games_played'])->toBe(2);

    // En seguimiento el puesto es el del ranking completo, no un 1.
    $uno = $this->getJson("/api/widget/{$id}/stats?type=players&sort=points&search=ana")->assertOk()->json();
    expect($uno)->toHaveCount(1)
        ->and($uno[0]['player_name'])->toBe('Ana')
        ->and($uno[0]['rank'])->toBe(3);
});
