<?php

use App\Services\Landing\EventosAbiertos;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    expect(DB::connection()->getDriverName())->toBe('sqlite');
    $this->getJson('/api/league/standings')->assertOk(); // crea las tablas de torneos
    $this->user = App\Models\User::factory()->create();
});

function torneo(array $datos): int
{
    return DB::table('tournaments')->insertGetId($datos + [
        'user_id' => test()->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('limpia el nombre de la fase sin romper caracteres especiales', function (string $nombre) {
    torneo(['name' => $nombre, 'slug' => 'v0.1-rankit-league-repechaje', 'is_serialized' => true, 'registration_status' => 'open']);

    $liga = app(EventosAbiertos::class)->obtener()['liga'];

    expect($liga['fase'])->toBe('Repechaje')
        ->and(json_encode($liga))->not->toBeFalse();
})->with(['Rankit Pro League - Repechaje (v0.1)', 'Rankit Pro League · Repechaje', 'Rankit Pro League — Repechaje']);

it('un semanal con fecha pasada no cuenta como abierto aunque diga open', function () {
    if (!Illuminate\Support\Facades\Schema::hasColumn('tournaments', 'start_date')) {
        Illuminate\Support\Facades\Schema::table('tournaments', fn ($t) => $t->timestamp('start_date')->nullable());
    }
    torneo(['name' => 'Semanal viejo', 'slug' => 'semanal-1', 'registration_status' => 'open', 'start_date' => now()->subMonths(3)]);

    expect(app(EventosAbiertos::class)->obtener()['semanal']['abierto'])->toBeFalse();

    torneo(['name' => 'Semanal nuevo', 'slug' => 'semanal-2', 'registration_status' => 'open', 'start_date' => now()->addDays(3)]);

    $semanal = app(EventosAbiertos::class)->obtener()['semanal'];
    expect($semanal['abierto'])->toBeTrue()->and($semanal['nombre'])->toBe('Semanal nuevo');
});
