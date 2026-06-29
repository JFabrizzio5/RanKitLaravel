<?php
use App\Http\Controllers\BellzCupReferralController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\GoogleController;

use App\Http\Controllers\Admin\TournamentParserController; // Admin Nuevo
use App\Http\Controllers\Admin\AdminUserController; // Gestión de usuarios
use App\Http\Controllers\Public\PublicTournamentController; // Publico Nuevo
use App\Http\Controllers\TournamentsController; // Publico Viejo (Listado)
use App\Http\Controllers\TournamentController; // Dashboard Viejo
use App\Http\Controllers\ProfilePageController; // Rankit
use App\Http\Controllers\LolTournamentController; // LoL / Valorant
use App\Http\Controllers\FootballController; // Futbol

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// --- PAGINA DE INICIO ---
Route::get('/', function () {
    return Inertia::render('Inicio', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('Inicio');

// --- RANKIT.PRO LEAGUE LANDING ---
Route::get('/league', function () {
    return Inertia::render('RankitLeague');
})->name('rankit.league');

// --- MUNDIAL 2026 STATS ---
Route::get('/mundial', function () {
    return Inertia::render('WorldCup');
})->name('worldcup.index');

// --- AUTH SOCIAL ---
Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google-callback', [GoogleController::class, 'callback'])->name('google.callback');

// --- DASHBOARD USUARIO ---
Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
    $email = $request->user()->email;
    
    // Obtener torneos donde el usuario está registrado
    $misTorneosIds = \Illuminate\Support\Facades\DB::table('tournament_registrations')
        ->where('email', $email)
        ->pluck('tournament_id');
        
    $misTorneos = \Illuminate\Support\Facades\DB::table('tournaments')
        ->whereIn('id', $misTorneosIds)
        ->get();
        
    // Obtener todos los torneos públicos
    $torneosPublicos = \Illuminate\Support\Facades\DB::table('tournaments')
        ->where('is_private', false)
        ->get();

    return Inertia::render('Dashboard', [
        'misTorneos' => $misTorneos,
        'torneosPublicos' => $torneosPublicos
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

// --- RUTAS RESTAURADAS ---

// 1. Perfil Rankit
Route::get('/profile/rankit', [ProfilePageController::class, 'show'])->name('rankit.profile');

// 2. Copa Bellz
Route::get('/BellzCup', function () {
    return Inertia::render('BellzCup/Index');
})->name('bellzcup.index');

// 3. Listado Público de Torneos (Original)
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
})->middleware('auth')->name('game.selector');

// --- LoL / VALORANT TORNEOS ---
Route::get('/league', function () {
    return Inertia::render('RankitLeague');
})->name('league');

Route::middleware('auth')->prefix('lol')->name('lol.')->group(function () {
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
Route::middleware('auth')->prefix('football')->name('football.')->group(function () {
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
Route::get('/widget/obs/player/{id}/{playerName}', [PublicTournamentController::class, 'widgetPlayer'])->name('widget.obs.player');
Route::get('/api/widget/{id}/stats', [PublicTournamentController::class, 'getWidgetStats'])->name('api.widget.stats');


// --- RUTAS AUTENTICADAS (PERFIL Y ADMIN) ---
Route::middleware('auth')->group(function () {
    // Perfil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // --- PANEL ADMIN JANGEL ---
    Route::prefix('admin')->group(function () {
        // Vista Principal
        Route::get('/jangel', [TournamentParserController::class, 'index'])->name('jangel.indexdos');
        
        // Crear Torneo
        Route::post('/tournaments', [TournamentParserController::class, 'store'])->name('jangel.tournament.store');
        Route::put('/tournaments/{id}', [TournamentParserController::class, 'update'])->name('jangel.tournament.update');
        
        // Eliminar Torneo
        Route::delete('/tournaments/{id}', [TournamentParserController::class, 'destroy'])->name('jangel.tournament.delete');

        // Subir imagen de banner del torneo
        Route::post('/tournaments/{id}/banner', [TournamentParserController::class, 'uploadBanner'])->name('jangel.tournament.banner');
        
        // Crear Slot (Código)
        Route::post('/matches/schedule/{tournamentId}', [TournamentParserController::class, 'storeScheduledMatch'])->name('jangel.match.schedule');

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

        // Seriación / Clasificación
        Route::post('/tournaments/{id}/classify', [TournamentParserController::class, 'classifyToNextRound'])->name('jangel.tournaments.classify');
        Route::get('/tournaments/{id}/qualifiers', [TournamentParserController::class, 'getQualifiers'])->name('jangel.tournaments.qualifiers');
        
        // API Leaderboard
        Route::get('/api/leaderboard/{tournamentId}', [TournamentParserController::class, 'getLeaderboard'])->name('api.leaderboard');
        
        // Clasificados Manuales y Registro
        Route::get('/api/league/tournaments/public', [TournamentParserController::class, 'getPublicTournaments'])->name('api.league.tournaments.public');
        
        Route::post('/tournaments/{id}/qualify-manual', [TournamentParserController::class, 'qualifyPlayerManual'])->name('jangel.tournaments.qualify-manual');
        Route::delete('/tournaments/{id}/qualify-manual/{qualifierId}', [TournamentParserController::class, 'removeQualifierManual'])->name('jangel.tournaments.unqualify-manual');

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
