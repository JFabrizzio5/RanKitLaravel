<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TournamentController; // Importamos el nuevo controlador
use App\Http\Controllers\TournamentsController; // Controlador para la vista de detalle del torneo
use App\Http\Controllers\ProfilePageController;
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
});

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

require __DIR__.'/auth.php';