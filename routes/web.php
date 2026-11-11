<?php
use App\Http\Controllers\BellzCupReferralController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\GoogleController;

use App\Http\Controllers\Admin\TournamentParserController; // Admin Nuevo
use App\Http\Controllers\Admin\AdminUserController; // Gestión de usuarios
use App\Http\Controllers\Public\PublicTournamentController; // Publico Nuevo
use App\Http\Controllers\TournamentsController; // Publico Viejo (Listado)
use App\Http\Controllers\SemanalesController; // Semanales (promocional)
use App\Http\Controllers\TournamentController; // Dashboard Viejo
use App\Http\Controllers\ProfilePageController; // Rankit
use App\Http\Controllers\LolTournamentController; // LoL / Valorant
use App\Http\Controllers\FootballController; // Futbol
use App\Http\Controllers\PlanesController; // Planes de organizador
use App\Http\Controllers\MercadoPagoWebhookController;
use App\Http\Controllers\SitemapController;
use App\Support\Seo;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

Route::get('/test-mail', function() {
    try {
        Mail::raw('Prueba de conexión SMTP desde Hostinger', function($msg) {
            $msg->to('cometax.ti@gmail.com')->subject('Prueba Rankit Hostinger');
        });
        return "✅ Correo enviado con éxito usando la configuración actual. (Mailer: " . config('mail.default') . ")";
    } catch (\Exception $e) {
        return "❌ Error crítico al enviar correo: " . $e->getMessage();
    }
});

// --- RUTAS DE DEBUG ---
Route::get('/debug-role', function (\Illuminate\Http\Request $request) {
    $user = $request->user();
    return response()->json([
        'user' => $user,
        'role' => $user?->role,
        'isAdmin' => $user ? $user->isAdmin() : false,
        'isSuperAdmin' => $user ? $user->isSuperAdmin() : false,
    ]);
});

// --- PAGINA DE INICIO ---
// La landing de ventas (propuesta K, elegida el 2026-11-11). La anterior sigue en Pages/Inicio.vue.
Route::get('/', function () {
    $catalogo = app(\App\Services\Planes\CatalogoPlanes::class);
    // Sin el prefetch global (AppServiceProvider): al cargar bajaba los ~100 archivos de
    // TODA la app (panel, torneos, three.js…) y en celular le quitaba red a la landing.
    \Illuminate\Support\Facades\Vite::usePrefetchStrategy(null);

    return Inertia::render('Propuestas/LandingK', [
        'precioBase' => $catalogo->precio('base'),
        'precioPro' => $catalogo->precio('pro'),
        'eventos' => app(\App\Services\Landing\EventosAbiertos::class)->obtener(),
        'seo' => Seo::pagina('inicio'),
    ]);
})->name('Inicio');

// --- SEO: sitemap dinámico y robots.txt ---
// En el hosting la raíz del sitio es el proyecto, no public/: public/robots.txt
// no se ve en /robots.txt, así que esta ruta lo sirve (en local gana el archivo).
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', fn () => response(file_get_contents(public_path('robots.txt')), 200, [
    'Content-Type' => 'text/plain; charset=UTF-8',
]))->name('robots');

// --- RANKIT.PRO LEAGUE LANDING ---
// Estaba registrada dos veces (aquí como rankit.league y abajo como league);
// la segunda pisaba a la primera y route('rankit.league') no existía.
Route::get('/league', function () {
    return Inertia::render('RankitLeague', ['seo' => Seo::pagina('league')]);
})->name('league');

// --- MUNDIAL 2026 STATS ---
Route::get('/mundial', function () {
    return Inertia::render('WorldCup', ['seo' => Seo::pagina('mundial')]);
})->name('worldcup.index');

// --- SEMANALES (apartado promocional, NO es Rankit League) ---
// Vista pública: si los eventos no existen, el controlador los crea solo.
Route::get('/semanales', [SemanalesController::class, 'index'])->name('semanales.index');
// Inscripción gratuita (JSON, sin auth obligatoria, con throttle anti-spam).
Route::post('/semanales/{id}/registro', [SemanalesController::class, 'register'])
    ->middleware('throttle:10,1')
    ->name('semanales.register');
// Aviso por correo a los inscritos de que el evento se recorrió (sólo admin).
// El throttle es bajo a propósito: manda correos masivos.
Route::post('/semanales/{id}/aviso-recorrido', [SemanalesController::class, 'notifyReschedule'])
    ->middleware(['auth', 'throttle:5,10'])
    ->name('semanales.reschedule-notice');

// --- 1V1 / WAGERS (landing "coming soon" para organizadores y streamers) ---
Route::get('/duels', function () {
    return Inertia::render('Duels/Index', ['seo' => Seo::pagina('duels')]);
})->name('duels.index');

// --- AUTH SOCIAL ---
Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google-callback', [GoogleController::class, 'callback'])->name('google.callback');

// --- DASHBOARD USUARIO ---
Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
    // Torneos donde el jugador está inscrito: por su cuenta (user_id) o por su
    // correo. Es la misma lista de /mis-torneos; el dashboard muestra solo el inicio.
    $misTorneos = app(\App\Services\Inscripciones\MisInscripciones::class)->de($request->user());

    // Obtener todos los torneos públicos
    $torneosPublicos = \Illuminate\Support\Facades\DB::table('tournaments')
        ->where('is_private', false)
        ->get();

    return Inertia::render('Dashboard', [
        'misTorneos' => $misTorneos,
        'torneosPublicos' => $torneosPublicos
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

// --- MIS TORNEOS (jugador): todas sus inscripciones en un solo lugar ---
Route::get('/mis-torneos', \App\Http\Controllers\MisTorneosController::class)
    ->middleware('auth')
    ->name('mis-torneos');

// --- RUTAS RESTAURADAS ---

// 1. Perfil Rankit
Route::get('/profile/rankit', [ProfilePageController::class, 'show'])->name('rankit.profile');

// 2. Copa Bellz
Route::get('/BellzCup', function () {
    return Inertia::render('BellzCup/Index', ['seo' => Seo::pagina('bellzcup')]);
})->name('bellzcup.index');

// 3. Listado Público de Torneos: /torneos (el /tournaments viejo redirige ahí)
Route::get('/torneos', [TournamentsController::class, 'lista'])->name('torneos.lista');
Route::get('/tournaments', [TournamentsController::class, 'index'])->name('tournaments.index');

// 4. Detalle de Torneo (Original)
Route::get('/tournament/{id}', [TournamentsController::class, 'show'])
    ->whereNumber('id')
    ->name('tournaments.show');

// 5. Dashboard/Widget Usuario (Original)
Route::middleware('auth')->group(function () {
    Route::get('/tournament/dashboard', [TournamentController::class, 'index'])->name('tournament.dashboard');
    Route::get('/tournament/widget', [TournamentController::class, 'widget'])->name('tournament.widget');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/bellzcup/referidos/redeem', [BellzCupReferralController::class, 'redeem'])
        ->name('bellzcup.referidos.redeem');
});

// --- SELECTOR DE JUEGO ---
Route::get('/game-selector', function () {
    return Inertia::render('GameSelector');
})->middleware(['auth', 'plan.organizador'])->name('game.selector');

// --- PLANES DE ORGANIZADOR (cobro en línea) ---
// Botón "Contratar" de la landing: con sesión va directo a /planes con la tarjeta
// resaltada; sin sesión manda a crear cuenta y, al terminar, regresa a /planes
// (RegisteredUserController usa redirect()->intended).
Route::get('/contratar/{plan}', function (\Illuminate\Http\Request $request, string $plan) {
    $destino = '/planes?quiero='.($plan === 'pro' ? 'pro' : 'mas');
    if ($request->user()) {
        return redirect($destino);
    }
    $request->session()->put('url.intended', url($destino));

    return redirect()->route('register');
})->whereIn('plan', ['base', 'pro'])->name('contratar');

// Sin plan vigente no se entra a los paneles (middleware plan.organizador).
Route::middleware('auth')->prefix('planes')->name('planes.')->group(function () {
    Route::get('/', [PlanesController::class, 'index'])->name('index');
    Route::post('/{plan}/pagar/{pasarela}', [PlanesController::class, 'pagar'])
        ->whereIn('plan', ['base', 'pro'])
        ->whereIn('pasarela', ['stripe', 'mercadopago'])
        ->middleware('throttle:10,1')
        ->name('pagar');
    Route::get('/retorno/stripe', [PlanesController::class, 'retornoStripe'])->name('retorno.stripe');
    Route::get('/retorno/mercadopago', [PlanesController::class, 'retornoMercadoPago'])->name('retorno.mercadopago');
    Route::post('/renovacion/cancelar', [PlanesController::class, 'cancelarRenovacion'])->name('renovacion.cancelar');
    Route::post('/renovacion/reanudar', [PlanesController::class, 'reanudarRenovacion'])->name('renovacion.reanudar');
});
// Notificaciones de Mercado Pago (las de Stripe las recibe Cashier en /stripe/webhook).
Route::post('/mercadopago/webhook', MercadoPagoWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('mercadopago.webhook');

// --- LoL / VALORANT TORNEOS ---
Route::middleware(['auth', 'plan.organizador'])->prefix('lol')->name('lol.')->group(function () {
    Route::get('/',                               [LolTournamentController::class, 'index'])->name('index');
    Route::post('/',                              [LolTournamentController::class, 'store'])->name('store');
    Route::get('/{id}',                           [LolTournamentController::class, 'show'])->name('show');
    Route::delete('/{id}',                        [LolTournamentController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/teams',                    [LolTournamentController::class, 'addTeam'])->name('team.add');
    Route::put('/{id}/teams/{teamId}',            [LolTournamentController::class, 'updateTeam'])->name('team.update');
    Route::delete('/{id}/teams/{teamId}',         [LolTournamentController::class, 'removeTeam'])->name('team.remove');
    Route::post('/{id}/shuffle',                  [LolTournamentController::class, 'shuffleTeams'])->name('shuffle');
    Route::post('/{id}/sort',                     [LolTournamentController::class, 'sortTeamsByName'])->name('sort');
    Route::post('/{id}/generate',                 [LolTournamentController::class, 'generateBracket'])->name('generate');
    Route::post('/{id}/result',                   [LolTournamentController::class, 'recordResult'])->name('result');
    Route::post('/{id}/edit-result',              [LolTournamentController::class, 'editResult'])->name('edit.result');
    Route::post('/{id}/recalculate',              [LolTournamentController::class, 'recalculateLeague'])->name('recalculate');
    Route::post('/{id}/schedule-match',           [LolTournamentController::class, 'scheduleMatch'])->name('schedule');
    Route::post('/{id}/advance',                  [LolTournamentController::class, 'advancePhase'])->name('advance');
    Route::post('/{id}/manual-round1',            [LolTournamentController::class, 'setManualRound1'])->name('manual.round1');
});

// Widget OBS LoL / Valorant — SIN autenticación para OBS
Route::get('/lol/{id}/widget',      [LolTournamentController::class, 'widget'])->name('lol.widget');
Route::get('/lol/{id}/bracket',     [LolTournamentController::class, 'bracket'])->name('lol.bracket');
Route::get('/lol/{id}/widget-data', [LolTournamentController::class, 'widgetData'])->name('lol.widget.data');
// Página pública del torneo — SIN autenticación (compartible)
Route::get('/lol/{id}/ver',         [LolTournamentController::class, 'publicView'])->name('lol.public.view');

// --- FUTBOL TORNEOS ---
Route::middleware(['auth', 'plan.organizador'])->prefix('football')->name('football.')->group(function () {
    Route::get('/',                               [FootballController::class, 'index'])->name('index');
    Route::post('/',                              [FootballController::class, 'store'])->name('store');
    Route::get('/{id}',                           [FootballController::class, 'show'])->name('show');
    Route::delete('/{id}',                        [FootballController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/teams',                    [FootballController::class, 'addTeam'])->name('team.add');
    Route::put('/{id}/teams/{teamId}',            [FootballController::class, 'updateTeam'])->name('team.update');
    Route::delete('/{id}/teams/{teamId}',         [FootballController::class, 'removeTeam'])->name('team.remove');
    Route::post('/{id}/teams/{teamId}/players',   [FootballController::class, 'addPlayer'])->name('player.add');
    Route::delete('/{id}/players/{playerId}',     [FootballController::class, 'removePlayer'])->name('player.remove');
    Route::post('/{id}/generate',                 [FootballController::class, 'generate'])->name('generate');
    Route::post('/{id}/result',                   [FootballController::class, 'recordResult'])->name('result');
});
// Vista pública futbol
Route::get('/football/{id}/ver',         [FootballController::class, 'publicView'])->name('football.public.view');




// --- RUTAS DE VISITAS (HEARTBEAT) ELIMINADAS ---
// La lógica de puntos ahora se maneja exclusivamente vía WebSocket en el microservicio Python.

// API para los Widgets (Pública para OBS)
Route::get('/api/tournaments/stats-detailed/{tableName}', [TournamentParserController::class, 'getDetailedStats']);


// --- RUTAS NUEVAS (SISTEMA JANGEL / LIVE) ---

// 1. Vista "Live" Nueva (Auto-actualizable)
Route::get('/live/{id}', [PublicTournamentController::class, 'show'])->name('public.live');
Route::get('/api/live/{id}/data', [PublicTournamentController::class, 'getPublicData'])->name('api.public.data');

// 2. Widgets OBS Nuevos
Route::get('/widget/obs/global/{id}', [PublicTournamentController::class, 'widgetGlobal'])->name('widget.obs.global');
// Armar el widget (tema, color, filas, logos) con vista previa y copiar el link para OBS.
Route::get('/widget/obs/{id}/personalizar', [PublicTournamentController::class, 'widgetPersonalizar'])->whereNumber('id')->name('widget.obs.personalizar');
Route::get('/widget/obs/player/{id}/{playerName}', [PublicTournamentController::class, 'widgetPlayer'])->name('widget.obs.player');
Route::get('/api/widget/{id}/stats', [PublicTournamentController::class, 'getWidgetStats'])->name('api.widget.stats');


// --- RUTAS AUTENTICADAS (PERFIL Y ADMIN) ---
Route::middleware('auth')->group(function () {
    // Perfil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    // Sin DELETE /profile: la cuenta no se puede borrar desde la app.

    // --- PANEL ADMIN JANGEL ---
    // Solo con plan vigente (o exentos: superadmin y config planes.exentos).
    Route::prefix('admin')->middleware('plan.organizador')->group(function () {
        // Vista Principal
        Route::get('/jangel', [TournamentParserController::class, 'index'])->name('jangel.indexdos');
        
        // Crear Torneo
        Route::post('/tournaments', [TournamentParserController::class, 'store'])->name('jangel.tournament.store');
        Route::put('/tournaments/{id}', [TournamentParserController::class, 'update'])->name('jangel.tournament.update');
        
        // Eliminar Torneo
        Route::delete('/tournaments/{id}', [TournamentParserController::class, 'destroy'])->name('jangel.tournament.delete');

        // Subir imagen de banner del torneo
        Route::post('/tournaments/{id}/banner', [TournamentParserController::class, 'uploadBanner'])->name('jangel.tournament.banner');

        // Subir logo del torneo (sale en el widget de OBS)
        Route::post('/tournaments/{id}/logo', [TournamentParserController::class, 'uploadLogo'])->name('jangel.tournament.logo');
        
        // Crear Slot (Código)
        Route::post('/matches/schedule/{tournamentId}', [TournamentParserController::class, 'storeScheduledMatch'])->name('jangel.match.schedule');

        // Enviar código de partida por email
        Route::post('/tournaments/{id}/match/{matchId}/send-code', [TournamentParserController::class, 'sendMatchCode'])->name('jangel.match.send-code');

        // Actualizar partida (Editar código)
        Route::put('/match/{id}', [TournamentParserController::class, 'updateMatch'])->name('jangel.match.update');
        
        // Procesar Replay
        Route::post('/tournaments/{id}/process-replay', [TournamentParserController::class, 'processReplay'])->name('tournaments.process-replay');
        Route::post('/matches/process/{id}', [TournamentParserController::class, 'processReplay'])->name('jangel.match.process'); // Alias
        
        // Eliminar Partida
        Route::delete('/match/{matchId}', [TournamentParserController::class, 'deleteMatch'])->name('jangel.match.delete');

        // Editar resultado directo de un jugador en una partida
        Route::put('/match/{matchId}/player-result', [TournamentParserController::class, 'updatePlayerResult'])->name('jangel.player.result.update');

        // Inscripciones / Pagos (Admin)
        Route::get('/tournaments/{id}/registrations', [TournamentParserController::class, 'getRegistrations'])->name('jangel.registrations');
        Route::put('/registrations/{id}/payment', [TournamentParserController::class, 'updatePaymentStatus'])->name('jangel.registrations.payment');
        Route::post('/tournaments/{id}/accept-all', [TournamentParserController::class, 'acceptAllRegistrations'])->name('jangel.registrations.accept_all');
        Route::post('/tournaments/initialize-league', [TournamentParserController::class, 'initializeLeague'])->name('jangel.tournaments.initialize_league');
        Route::get('/tournaments/initialize-league', [TournamentParserController::class, 'initializeLeague']);

        // Seriación / Clasificación
        Route::post('/tournaments/{id}/classify', [TournamentParserController::class, 'classifyToNextRound'])->name('jangel.tournaments.classify');
        Route::get('/tournaments/{id}/qualifiers', [TournamentParserController::class, 'getQualifiers'])->name('jangel.tournaments.qualifiers');
        
        // API Leaderboard
        Route::get('/api/leaderboard/{tournamentId}', [TournamentParserController::class, 'getLeaderboard'])->name('api.leaderboard');
        
        // Clasificados Manuales y Registro
        Route::get('/api/league/tournaments/public', [TournamentParserController::class, 'getPublicTournaments'])->name('api.league.tournaments.public');
        
        Route::post('/tournaments/{id}/qualify-manual', [TournamentParserController::class, 'qualifyPlayerManual'])->name('jangel.tournaments.qualify-manual');
        Route::delete('/tournaments/{id}/qualify-manual/{qualifierId}', [TournamentParserController::class, 'removeQualifierManual'])->name('jangel.tournaments.unqualify-manual');
        Route::post('/tournaments/{id}/send-access-codes', [TournamentParserController::class, 'sendAccessCodes'])->name('jangel.tournaments.send-codes');

        Route::get('/api/leaderboard-internal/{tournamentId}', [TournamentParserController::class, 'getLeaderboard'])->name('jangel.api.leaderboard'); // Alias

        // --- GESTIÓN DE CANCHAS (PITCHES) ---
        Route::get('/pitches', [TournamentParserController::class, 'getPitches'])->name('jangel.pitches');
        Route::post('/pitches', [TournamentParserController::class, 'storePitch'])->name('jangel.pitches.store');
        Route::delete('/pitches/{id}', [TournamentParserController::class, 'deletePitch'])->name('jangel.pitches.delete');
        Route::get('/pitches/{id}/reservations', [TournamentParserController::class, 'getPitchReservations'])->name('jangel.pitches.reservations');
        Route::post('/pitches/{id}/reservations', [TournamentParserController::class, 'storePitchReservation'])->name('jangel.pitches.reservations.store');
        Route::put('/reservations/{id}/status', [TournamentParserController::class, 'updatePitchReservationStatus'])->name('jangel.reservations.status');

        // --- GESTIÓN DE USUARIOS (solo superadmin) ---
        Route::middleware('superadmin')->group(function () {
            Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users.index');
            Route::post('/users', [AdminUserController::class, 'store'])->name('admin.users.store');
            Route::put('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('admin.users.role');
            Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
            Route::post('/users/{user}/plan', [AdminUserController::class, 'otorgarPlan'])->name('admin.users.plan.grant');
            Route::delete('/users/{user}/plan', [AdminUserController::class, 'revocarPlan'])->name('admin.users.plan.revoke');
        });
    });
});

Route::middleware(['auth'])->group(function () {
    Route::post('/league/register', [TournamentParserController::class, 'registerPlayer'])->name('league.register');
});

// API Public Registration
Route::post('/api/tournaments/{id}/register', [TournamentParserController::class, 'registerForTournament'])
    ->name('api.tournaments.register');

    //IMPLEMENTACIONES RAPIDAS
Route::post('/admin/tournaments/{tournament}/adjust-score', [TournamentParserController::class, 'adjustPlayerScore'])
    ->middleware(['auth', 'superadmin'])
    ->name('jangel.score.adjust');
 Route::post('/admin/tournaments/{id}/appeal', [TournamentParserController::class, 'appealReplay'])
    ->middleware(['auth', 'superadmin'])
    ->name('jangel.match.appeal');
    
 
 // Esta ruta acepta el parámetro opcional ?code=XYZ
Route::get('/t/{slug}', [PublicTournamentController::class, 'show'])->name('public.tournament.show');
// Verificación de código de acceso con rate limiting (máx 3 intentos por sesión)
Route::post('/t/{id}/verify-code', [PublicTournamentController::class, 'verifyCode'])->name('public.tournament.verify');
Route::get('/api/tournaments/{id}/registration-status', [PublicTournamentController::class, 'getRegistrationStatus']);

require __DIR__.'/auth.php';Route::get('/api/league/standings', [App\Http\Controllers\Admin\TournamentParserController::class, 'getSerializedStandings']);


// --- PROPUESTAS DE LANDING (solo local, para revisar antes de elegir) ---
if (app()->environment('local')) {
    Route::get('/propuestas/ver-objetos', fn () => Inertia::render('Propuestas/VerObjetos'));

    Route::get('/propuestas/landing/{v?}', function (string $v = 'a') {
        $catalogo = app(\App\Services\Planes\CatalogoPlanes::class);
        // Sin el prefetch global (AppServiceProvider): al cargar bajaba los ~100 archivos de
        // TODA la app (panel, torneos, three.js…) y en celular le quitaba red a la landing.
        \Illuminate\Support\Facades\Vite::usePrefetchStrategy(null);

        return Inertia::render('Propuestas/Landing'.strtoupper($v), [
            'precioBase' => $catalogo->precio('base'),
            'precioPro' => $catalogo->precio('pro'),
            'eventos' => app(\App\Services\Landing\EventosAbiertos::class)->obtener(),
        ]);
    })->whereIn('v', ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'k'])->name('propuestas.landing');
}
