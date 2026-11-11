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
