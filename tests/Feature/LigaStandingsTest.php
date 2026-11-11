<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    expect(DB::connection()->getDriverName())->toBe('sqlite');
});

it('la tabla pública de la liga no expone el código de acceso', function () {
    // Crea las tablas de torneos en el primer request.
    $this->getJson('/api/league/standings')->assertOk();

    $user = App\Models\User::factory()->create();
    DB::table('tournaments')->insert([
        'user_id' => $user->id,
        'name' => 'Gran Final',
        'slug' => 'v0.1-rankit-league-gran-final',
        'is_serialized' => true,
        'is_private' => true,
        'access_code' => 'secreto-final',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $respuesta = $this->getJson('/api/league/standings')->assertOk();

    expect($respuesta->json('0.tournament.name'))->toBe('Gran Final')
        ->and($respuesta->json('0.tournament'))->not->toHaveKey('access_code')
        ->and($respuesta->getContent())->not->toContain('secreto-final');
});

it('la página pública de un torneo privado no lleva su código en el HTML', function () {
    $this->getJson('/api/league/standings')->assertOk(); // crea las tablas

    $user = App\Models\User::factory()->create();
    $id = DB::table('tournaments')->insertGetId([
        'user_id' => $user->id,
        'name' => 'Torneo cerrado',
        'is_private' => true,
        'access_code' => 'codigo-secreto-123',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $html = $this->get("/t/{$id}")->assertOk()->getContent();

    expect($html)->toContain('Torneo cerrado')
        ->and($html)->not->toContain('codigo-secreto-123');
});
