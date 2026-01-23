<?php
use App\Http\Controllers\BellzCupReferralController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\GoogleController;

use App\Http\Controllers\Admin\TournamentParserController; // Admin Nuevo
use App\Http\Controllers\Public\PublicTournamentController; // Publico Nuevo
use App\Http\Controllers\TournamentsController; // Publico Viejo (Listado)
use App\Http\Controllers\TournamentController; // Dashboard Viejo
use App\Http\Controllers\ProfilePageController; // Rankit

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

// --- AUTH SOCIAL ---
Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google-callback', [GoogleController::class, 'callback'])->name('google.callback');

// --- DASHBOARD USUARIO ---
Route::get('/dashboard', function () {
    if (auth()->check() && auth()->user()->email === '18jangel18@gmail.com') {
        return redirect()->route('jangel.indexdos');
    }
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// --- RUTAS RESTAURADAS ---

// 1. Perfil Rankit
Route::get('/profile/rankit', function () {
    if (auth()->check() && auth()->user()->email === '18jangel18@gmail.com') {
        return redirect()->route('jangel.indexdos');
    }
    return app()->call([ProfilePageController::class, 'show']);
})->name('rankit.profile');

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
        
        // Crear Slot (Código)
        Route::post('/matches/schedule/{tournamentId}', [TournamentParserController::class, 'storeScheduledMatch'])->name('jangel.match.schedule');

        // Actualizar partida (Editar código)
        Route::put('/match/{id}', [TournamentParserController::class, 'updateMatch'])->name('jangel.match.update');
        
        // Procesar Replay
        Route::post('/tournaments/{id}/process-replay', [TournamentParserController::class, 'processReplay'])->name('tournaments.process-replay');
        Route::post('/matches/process/{id}', [TournamentParserController::class, 'processReplay'])->name('jangel.match.process'); // Alias
        
        // Eliminar Partida
        Route::delete('/match/{matchId}', [TournamentParserController::class, 'deleteMatch'])->name('jangel.match.delete');
        
        // API Leaderboard
        Route::get('/api/leaderboard/{tournamentId}', [TournamentParserController::class, 'getLeaderboard'])->name('api.leaderboard');
        Route::get('/api/leaderboard-internal/{tournamentId}', [TournamentParserController::class, 'getLeaderboard'])->name('jangel.api.leaderboard'); // Alias
    });
});
Route::get('/api/live/{id}/data', [PublicTournamentController::class, 'getPublicData'])
    ->name('api.public.data');


    // Esta ruta acepta el parámetro opcional ?code=XYZ
Route::get('/t/{slug}', [PublicTournamentController::class, 'show'])->name('public.tournament.show');

require __DIR__.'/auth.php';