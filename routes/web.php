<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TournamentController; // Controlador para vistas frontend/dashboard user
use App\Http\Controllers\TournamentsController; // Controlador para mostrar detalle público
use App\Http\Controllers\Admin\TournamentParserController; // NUEVO: Controlador Admin potente
use App\Http\Controllers\ProfilePageController;
use App\Http\Controllers\Auth\GoogleController;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB; // Necesario para la ruta closure de widget rápido si se usa

// --- Rutas Públicas e Iniciales ---
Route::get('/', function () {
    return Inertia::render('Inicio', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('Inicio');

// --- Autenticación Social ---
Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google-callback', [GoogleController::class, 'callback'])->name('google.callback');

// --- Dashboard de Usuario Normal ---
Route::get('/dashboard', function () {
    // Redirección especial para admin específico
    if (auth()->check() && auth()->user()->email === '18jangel18@gmail.com') {
        return redirect()->route('jangel.indexdos'); // Redirige al panel de admin
    }
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// --- Grupo de Rutas Autenticadas ---
Route::middleware('auth')->group(function () {
    // Perfil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Vistas de Torneo (Frontend Usuario)
    Route::get('/tournament/dashboard', [TournamentController::class, 'index'])->name('tournament.dashboard');
    Route::get('/tournament/widget', [TournamentController::class, 'widget'])->name('tournament.widget');
    
    // --- PANEL DE ADMINISTRACIÓN (Jangel) ---
    // Listado principal
    Route::get('/admin/jangel', [TournamentParserController::class, 'index'])->name('jangel.indexdos');
    
    // Crear Torneo
    Route::post('/admin/tournaments', [TournamentParserController::class, 'store'])->name('jangel.store');
    
    // Ver Torneo (Admin)
    Route::get('/admin/tournaments/{tournament}', [TournamentParserController::class, 'show'])->name('tournaments.show');
    
    // PROCESAR REPLAY (Lógica Core)
    Route::post('/admin/tournaments/{tournament}/process-replay', [TournamentParserController::class, 'processReplay'])->name('tournaments.process-replay');
    
    // ELIMINAR PARTIDA (Nueva funcionalidad)
    Route::delete('/admin/match/{id}', [TournamentParserController::class, 'deleteMatch'])->name('jangel.match.delete');
});

// --- Rutas Públicas de Visualización ---

// Perfil Rankit
Route::get('/profile/rankit', [ProfilePageController::class, 'show'])->name('rankit.profile');

// Copa Bellz
Route::get('/BellzCup', function () {
    return Inertia::render('BellzCup/Index');
})->name('bellzcup.index');

// Detalle de Torneo Público
Route::get('/tournament/{id}', [TournamentsController::class, 'show'])
    ->whereNumber('id')
    ->name('tournaments.show');


// --- APIs para Frontend y Widgets (JSON) ---

// API Leaderboard (Usada por el Dashboard Vue para refrescar la tabla)
Route::get('/api/leaderboard/{tournamentId}', [TournamentParserController::class, 'getLeaderboard'])->name('api.leaderboard');

// Widget para OBS (Vista simple sin layout)
Route::get('/widget/{tableName}', function($tableName) {
    // Nota: Si pasas a usar IDs en lugar de tableNames, deberás ajustar esto.
    // Por ahora, si usas IDs, puedes pasar el ID como tableName.
    return view('tournament-widget', ['tableName' => $tableName]);
});

require __DIR__.'/auth.php';