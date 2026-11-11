<?php

use App\Http\Controllers\Admin\TournamentParserController;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Candado: estas pruebas usan RefreshDatabase. Jamás contra MySQL.
    expect(DB::connection()->getDriverName())->toBe('sqlite');

    $this->withoutVite();
    config(['seo.url' => 'https://rankit.pro', 'seo.imagen' => 'public/league/gran-final.png']);
});

/** Crea la tabla tournaments como en producción (en el primer uso) y un torneo. */
function torneoParaSeo(array $attrs = []): int
{
    (new TournamentParserController())->ensureDatabaseIsReady();

    return DB::table('tournaments')->insertGetId($attrs + [
        'user_id' => User::factory()->create()->id,
        'name' => 'Copa Relámpago',
        'is_private' => false,
        'created_at' => '2026-11-01 10:00:00',
        'updated_at' => '2026-11-05 12:30:00',
    ]);
}

it('pinta description, og:title y el idioma en el inicio', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('<html lang="es-MX"')
        ->toContain('<title inertia>Rankit: torneos de Fortnite en México con tabla en vivo</title>')
        ->toContain('<meta name="description" content="Crea y juega torneos y scrims de Fortnite en México')
        ->toContain('<meta property="og:title" content="Rankit: torneos de Fortnite en México con tabla en vivo">')
        ->toContain('<meta property="og:locale" content="es_MX">')
        ->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->toContain('<link rel="canonical" href="https://rankit.pro/">')
        ->toContain('<meta property="og:image" content="https://rankit.pro/public/league/gran-final.png">');
});

it('pinta description y og:title en /league', function () {
    $html = $this->get('/league')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta name="description" content="Circuito de 4 semanas de Fortnite en Solitario')
        ->toContain('<meta property="og:title" content="Rankit League: liga competitiva de Fortnite en México">')
        ->toContain('<link rel="canonical" href="https://rankit.pro/league">');
});

it('pinta el seo de las demás páginas fijas', function (string $ruta, string $titulo) {
    User::factory()->create(); // /semanales necesita un dueño para crear sus eventos

    $html = $this->get($ruta)->assertOk()->getContent();

    expect($html)
        ->toContain("<title inertia>{$titulo}</title>")
        ->toContain("<meta property=\"og:title\" content=\"{$titulo}\">");
})->with([
    ['/semanales', 'Torneos semanales de Fortnite gratis con premios | Rankit'],
    ['/duels', 'Retas 1v1 y wagers de Fortnite: muy pronto en Rankit'],
    ['/mundial', 'Mundial 2026: campeón, bracket y estadísticas | Rankit'],
    ['/BellzCup', 'BellzCup: códigos de sala y tabla del torneo | Rankit'],
]);

it('usa valores por defecto en páginas sin prop seo', function () {
    $html = $this->get('/login')->assertOk()->getContent();

    expect($html)
        ->toContain('<title inertia>Rankit</title>')
        ->toContain('<meta name="description" content="Crea y juega torneos')
        ->toContain('<link rel="canonical" href="https://rankit.pro/login">');
});

it('pone el nombre del torneo público en og:title, su banner y el JSON-LD', function () {
    $id = torneoParaSeo(['banner_image' => 'torneos/banner_1.png']);

    foreach (["/t/{$id}", "/live/{$id}", "/tournament/{$id}"] as $ruta) {
        $html = $this->get($ruta)->assertOk()->getContent();

        expect($html)
            ->toContain('<meta property="og:title" content="Copa Relámpago: tabla en vivo de Fortnite | Rankit">')
            ->toContain("<link rel=\"canonical\" href=\"https://rankit.pro/t/{$id}\">")
            ->toContain('<meta property="og:image" content="https://rankit.pro/public/torneos/banner_1.png">')
            ->toContain('"@type":"SportsEvent"')
            ->not->toContain('name="robots"');
    }
});

it('pasa la fecha, el precio y la entrada gratis al JSON-LD cuando existen', function () {
    (new TournamentParserController())->ensureDatabaseIsReady();
    // start_date e is_free las agrega SemanalesController en producción.
    Schema::table('tournaments', function ($table) {
        $table->dateTime('start_date')->nullable();
        $table->boolean('is_free')->nullable();
    });

    $conHora = torneoParaSeo(['start_date' => '2026-12-05 20:00:00', 'ticket_price' => '$150 MXN']);
    $gratis = torneoParaSeo(['start_date' => '2026-12-12 00:00:00', 'is_free' => true]);

    $html = $this->get("/t/{$conHora}")->assertOk()->getContent();
    expect($html)
        ->toContain('"startDate":"2026-12-05T20:00:00-06:00"')
        ->toContain('"price":"150.00","priceCurrency":"MXN"')
        ->toContain('Se juega el 5 de diciembre de 2026.');

    expect($this->get("/t/{$gratis}")->getContent())
        ->toContain('"startDate":"2026-12-12"')
        ->toContain('"isAccessibleForFree":true')
        ->not->toContain('"offers"');
});

it('no indexa torneos privados ni les pone JSON-LD', function () {
    $id = torneoParaSeo(['name' => 'Scrim Privada', 'is_private' => true, 'access_code' => 'SECRETO']);

    $html = $this->get("/t/{$id}")->assertOk()->getContent();

    expect($html)
        ->toContain('<meta name="robots" content="noindex, nofollow">')
        ->not->toContain('application/ld+json');
});

it('escapa el JSON-LD para que un nombre no cierre el script', function () {
    $id = torneoParaSeo(['name' => 'Copa </script><script>alert(1)</script>']);

    $html = $this->get("/t/{$id}")->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>');
});

it('genera un sitemap XML válido sin torneos privados', function () {
    $publico = torneoParaSeo(['name' => 'Copa Abierta']);
    $privado = torneoParaSeo(['name' => 'Copa Cerrada', 'is_private' => true]);

    $respuesta = $this->get('/sitemap.xml')->assertOk();
    expect($respuesta->headers->get('Content-Type'))->toContain('application/xml');

    $xml = simplexml_load_string($respuesta->getContent());
    expect($xml)->not->toBeFalse();

    $urls = [];
    foreach ($xml->url as $url) {
        $urls[(string) $url->loc] = (string) $url->lastmod;
    }

    expect($urls)
        ->toHaveKey('https://rankit.pro/')
        ->toHaveKey('https://rankit.pro/league')
        ->toHaveKey('https://rankit.pro/semanales')
        ->toHaveKey("https://rankit.pro/t/{$publico}")
        ->not->toHaveKey("https://rankit.pro/t/{$privado}")
        ->not->toHaveKey('https://rankit.pro/BellzCup');

    expect($urls["https://rankit.pro/t/{$publico}"])->toBe('2026-11-05');
});

it('responde el sitemap aunque la tabla de torneos no exista', function () {
    expect(Schema::hasTable('tournaments'))->toBeFalse();

    $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());

    expect($xml)->not->toBeFalse()
        ->and(count($xml->url))->toBeGreaterThan(0);
});

it('sirve robots.txt con el sitemap y las rutas bloqueadas', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Sitemap: https://rankit.pro/sitemap.xml')
        ->assertSee('Disallow: /admin');
});

it('ya no truena /tournaments: manda a la sección de torneos', function () {
    $this->get('/tournaments')->assertRedirect('/torneos');
});

it('sirve /favicon.ico (en el hosting la raíz no es public/)', function () {
    $this->get('/favicon.ico')->assertOk()->assertHeader('Content-Type', 'image/x-icon');
});
