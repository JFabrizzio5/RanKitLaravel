<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    /**
     * Muestra el panel de control especial del torneo.
     */
    public function index(): Response
    {
        // Aquí podrías cargar datos reales de la base de datos si los tuvieras
        $equipos = [
            ['nombre' => 'Team Alpha', 'puntos' => 150, 'kills' => 45],
            ['nombre' => 'Omega Squad', 'puntos' => 120, 'kills' => 30],
            ['nombre' => 'Los Guerreros', 'puntos' => 90, 'kills' => 25],
            ['nombre' => 'Sin Limites', 'puntos' => 85, 'kills' => 20],
        ];

        return Inertia::render('Tournament/Dashboard', [
            'equipos' => $equipos
        ]);
    }

    /**
     * Muestra el widget diseñado para OBS (sin layout pesado).
     */
    public function widget(): Response
    {
        // Datos simulados para la tabla de puntuación
        $leaderboard = [
            ['rank' => 1, 'team' => 'Team Alpha', 'score' => 150, 'wins' => 3],
            ['rank' => 2, 'team' => 'Omega Squad', 'score' => 120, 'wins' => 2],
            ['rank' => 3, 'team' => 'Los Guerreros', 'score' => 90, 'wins' => 1],
            ['rank' => 4, 'team' => 'Sin Limites', 'score' => 85, 'wins' => 1],
            ['rank' => 5, 'team' => 'Net Runners', 'score' => 60, 'wins' => 0],
        ];

        return Inertia::render('Tournament/Widget', [
            'leaderboard' => $leaderboard
        ]);
    }
}