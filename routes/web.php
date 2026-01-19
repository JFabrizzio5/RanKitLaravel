<?php
use App\Http\Controllers\BellzCupReferralController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TournamentController; // Importamos el nuevo controlador
use App\Http\Controllers\TournamentsController; // Controlador para la vista de detalle del torneo
use App\Http\Controllers\ProfilePageController;
use App\Http\Controllers\Auth\GoogleController;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Inicio', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('Inicio');

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google-callback', [GoogleController::class, 'callback'])->name('google.callback');
// Modificación aquí: Lógica condicional dentro de la ruta dashboard
Route::get('/dashboard', function () {
    // Si es el usuario específico, redirigir a rankit.profile
    if (auth()->user()->email === '18jangel18@gmail.com') {
        return redirect()->route('rankit.profile');
    }

    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Rutas del Torneo Especial (Solo accesibles si estás logueado)
    // Podrías añadir un middleware extra 'can:admin' si quisieras más seguridad
    Route::get('/tournament/dashboard', [TournamentController::class, 'index'])->name('tournament.dashboard');
    Route::get('/tournament/widget', [TournamentController::class, 'widget'])->name('tournament.widget');
});

Route::get('/tournament/{id}', [TournamentsController::class, 'show'])
    ->whereNumber('id')
    ->name('tournaments.show');

Route::get('/profile/rankit', [ProfilePageController::class, 'show'])
    ->name('rankit.profile');

Route::get('/BellzCup', function () {
    return Inertia::render('BellzCup/Index');
})->name('bellzcup.index');




use App\Http\Controllers\Admin\TournamentParserController;

// Panel de Jangel (Seguro)
Route::middleware(['auth'])->group(function () {
    Route::get('/jangel-panel', [TournamentParserController::class, 'index'])->name('jangel.index');
    Route::post('/tournament-create-quick', [TournamentParserController::class, 'createQuickTournament'])->name('jangel.tournament.create');
    Route::post('/replay-sync-net', [TournamentParserController::class, 'syncMatchData'])->name('jangel.replay.sync');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/bellzcup/referidos/redeem', [BellzCupReferralController::class, 'redeem'])
        ->name('bellzcup.referidos.redeem');
});
Route::middleware(['auth'])->group(function () {
    Route::post('/bellzcup/viewer/start', [\App\Http\Controllers\BellzCupViewerController::class, 'start'])
        ->name('bellzcup.viewer.start');

    Route::post('/bellzcup/viewer/heartbeat', [\App\Http\Controllers\BellzCupViewerController::class, 'heartbeat'])
        ->name('bellzcup.viewer.heartbeat');
});


// API para los Widgets (Pública para OBS)
Route::get('/api/tournaments/stats-detailed/{tableName}', [TournamentParserController::class, 'getDetailedStats']);

// Vista del Widget para OBS
Route::get('/widget/{tableName}', function($tableName) {
    return view('tournament-widget', ['tableName' => $tableName]);
});


Route::get('/tournaments/stats-detailed/{tableName}', function($tableName) {
    // Esta Query es el motor de tu panel. Calcula promedios y totales de golpe.
    return DB::table($tableName)
        ->select(
            'player_name',
            DB::raw('COUNT(DISTINCT match_number) as matches_played'),
            DB::raw('SUM(kills) as total_kills'),
            DB::raw('SUM(placement_points) as total_placement_points'),
            DB::raw('SUM(kill_points) as total_kill_points'),
            DB::raw('SUM(total_points) as total_points'),
            DB::raw('ROUND(AVG(rank), 1) as avg_placement'),
            DB::raw('MAX(rank) as best_placement')
        )
        ->groupBy('player_name')
        ->orderBy('total_points', 'desc')
        ->get();
});


Route::middleware(['auth'])->group(function () {
    // Panel de Administración de Jangel
    Route::get('/admin/jangel', [TournamentParserController::class, 'index'])->name('jangel.indexdos');
    Route::post('/admin/tournaments', [TournamentParserController::class, 'store'])->name('jangel.store');
    Route::post('/admin/tournaments/{tableName}/upload', [TournamentParserController::class, 'uploadReplay'])->name('jangel.upload');
      Route::get('/admin/tournaments/{tournament}', [TournamentParserController::class, 'show'])->name('tournaments.show');
    Route::post('/admin/tournaments/{tournament}/process-replay', [TournamentParserController::class, 'processReplay'])->name('tournaments.process-replay');
});

// Ruta pública para ver el Leaderboard (Widget OBS)
Route::get('/api/leaderboard/{tableName}', [TournamentParserController::class, 'getLeaderboard']);

require __DIR__.'/auth.php';