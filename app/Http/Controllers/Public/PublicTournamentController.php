<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PublicTournamentController extends Controller
{
    /**
     * Vista HTML principal (/live/{id})
     */
    public function show($id)
    {
        $tournament = DB::table('tournaments')->where('id', $id)->first();
        if (!$tournament) abort(404);

        $matchesProcessed = DB::table('tournament_matches')
            ->where('tournament_id', $id)
            ->whereNotNull('raw_data')
            ->count();

        $expected = $tournament->expected_matches ?? 5;
        $tournament->progress = "Partida $matchesProcessed / {$expected}";

        return Inertia::render('Public/TournamentLive', [
            'tournament' => $tournament
        ]);
    }

    /**
     * API JSON para el Frontend (/api/live/{id}/data)
     */
    public function getPublicData(Request $request, $id)
    {
        $tournament = DB::table('tournaments')->where('id', $id)->first();
        if (!$tournament) return response()->json(['error' => 'Not found'], 404);

        // 1. Recibir todos los filtros igual que en Admin
        $mode = $request->query('mode', 'all');
        $sortBy = $request->query('sort', 'points');
        $matchId = $request->query('match_id');
        $type = $request->query('type', 'players'); // players | teams

        // 2. Progreso
        $matchesProcessed = DB::table('tournament_matches')
            ->where('tournament_id', $id)
            ->whereNotNull('raw_data')
            ->count();

        // 3. Lista de Partidas
        $matchesList = DB::table('tournament_matches')
            ->where('tournament_id', $id)
            ->orderBy('created_at', 'desc')
            ->select('id', 'game_mode', 'custom_code', 'raw_data', 'created_at')
            ->get()
            ->map(function($m) {
                return [
                    'id' => $m->id,
                    'mode' => strtoupper($m->game_mode),
                    'code' => $m->custom_code ?? '---',
                    'status' => is_null($m->raw_data) ? 'En Curso' : 'Finalizada',
                    'is_active' => is_null($m->raw_data),
                    'created_at' => $m->created_at
                ];
            });

        // 4. Calcular Ranking
        $ranking = $this->getFullRanking($id, $mode, $type, $matchId, $sortBy);

        $expected = $tournament->expected_matches ?? 5;

        // CORRECCIÓN: Usamos `?? null` para evitar error 500 si la columna twitch_channel no existe
        return response()->json([
            'tournament' => [
                'name' => $tournament->name,
                'progress' => "Partida $matchesProcessed / {$expected}",
                'twitch_channel' => $tournament->twitch_channel ?? null 
            ],
            'matches' => $matchesList,
            'ranking' => $ranking
        ]);
    }

    // --- MÉTODOS WIDGETS ---

    // Widget único flexible (ObsGlobal ahora sirve para todo)
    public function widgetGlobal($id)
    {
        return Inertia::render('Widgets/ObsGlobal', ['tournamentId' => (int)$id]);
    }

    // Mantenemos este por retrocompatibilidad
    public function widgetPlayer($id, $playerName)
    {
        return Inertia::render('Widgets/ObsGlobal', [ 
            'tournamentId' => (int)$id, 
            'defaultSearch' => $playerName 
        ]);
    }

    // API JSON POTENCIADA para Widgets OBS
    public function getWidgetStats(Request $request, $id)
    {
        // 1. Filtros Flexibles
        $mode = $request->query('mode', 'all');
        $sortBy = $request->query('sort', 'points'); // points | kills
        $type = $request->query('type', 'players'); // players | teams
        $limit = $request->query('limit', 10); // Cantidad de filas
        $search = $request->query('search'); // Para trackear específico
        
        // 2. Obtener Ranking con Filtros
        $ranking = $this->getFullRanking($id, $mode, $type, null, $sortBy);

        // 3. Lógica de Tracking (Search)
        if ($search) {
            $found = $ranking->first(function($item) use ($search, $type) {
                if ($type === 'teams') {
                    // Busca coincidencia parcial en miembros
                    foreach ($item->member_names as $member) {
                        if (stripos($member, $search) !== false) return true;
                    }
                    return false;
                }
                // Busca coincidencia en nombre de jugador
                return stripos($item->player_name, $search) !== false;
            });

            return response()->json($found ? [$found] : []);
        }
        
        // 4. Retorno Normal (Top List)
        return response()->json($ranking->take((int)$limit)->values());
    }

    // --- LÓGICA CENTRAL DE CÁLCULO ---
    
    protected function getFullRanking($tournamentId, $mode = 'all', $viewType = 'players', $matchId = null, $sortBy = 'points')
    {
        $orderByCol = ($sortBy === 'kills') ? 'total_kills' : 'total_points';
        $secondaryOrder = ($sortBy === 'kills') ? 'total_points' : 'total_kills';

        // --- VISTA EQUIPOS ---
        if ($viewType === 'teams') {
            $query = DB::table('team_match_stats')
                ->join('tournament_matches', 'team_match_stats.tournament_match_id', '=', 'tournament_matches.id')
                ->where('tournament_matches.tournament_id', $tournamentId)
                ->select(
                    'team_signature',
                    DB::raw('MIN(member_names) as members_json'), 
                    DB::raw('COUNT(*) as games_played'),
                    DB::raw('SUM(total_kills) as total_kills'),
                    DB::raw('AVG(total_kills) as avg_kills'),
                    DB::raw('MIN(rank) as best_placement'),
                    DB::raw('AVG(rank) as avg_placement'),
                    DB::raw('SUM(total_points) as total_points'),
                    DB::raw('AVG(total_points) as avg_points')
                );

            if ($matchId) $query->where('tournament_matches.id', $matchId);
            elseif ($mode !== 'all') $query->where('tournament_matches.game_mode', $mode);

            return $query
                ->groupBy('team_signature')
                ->orderByDesc($orderByCol)
                ->orderByDesc($secondaryOrder)
                ->get()
                ->map(function($team) {
                    $team->member_names = json_decode($team->members_json);
                    return $team;
                });
        }

        // --- VISTA JUGADORES ---
        $query = DB::table('player_match_stats')
            ->join('tournament_matches', 'player_match_stats.tournament_match_id', '=', 'tournament_matches.id')
            ->where('tournament_matches.tournament_id', $tournamentId)
            ->where('player_name', '!=', 'Unknown')
            ->select(
                'player_name',
                DB::raw('COUNT(*) as games_played'),
                // Totales
                DB::raw('SUM(kills) as total_kills'),
                DB::raw('SUM(CAST(JSON_EXTRACT(extra_stats, "$.totalPoints") AS DECIMAL(10,2))) as total_points'),
                DB::raw('SUM(CAST(JSON_EXTRACT(extra_stats, "$.killPoints") AS DECIMAL(10,2))) as total_kill_points'),
                DB::raw('SUM(CAST(JSON_EXTRACT(extra_stats, "$.placementPoints") AS DECIMAL(10,2))) as total_placement_points'),
                // Promedios (AVG)
                DB::raw('AVG(kills) as avg_kills'),
                DB::raw('AVG(placement) as avg_placement'),
                DB::raw('AVG(CAST(JSON_EXTRACT(extra_stats, "$.totalPoints") AS DECIMAL(10,2))) as avg_points'),
                DB::raw('AVG(CAST(JSON_EXTRACT(extra_stats, "$.killPoints") AS DECIMAL(10,2))) as avg_kill_points'),
                DB::raw('AVG(CAST(JSON_EXTRACT(extra_stats, "$.placementPoints") AS DECIMAL(10,2))) as avg_placement_points'),
                
                DB::raw('MIN(placement) as best_placement')
            );

        if ($matchId) $query->where('tournament_matches.id', $matchId);
        elseif ($mode !== 'all') $query->where('tournament_matches.game_mode', $mode);

        return $query
            ->groupBy('player_name')
            ->orderByDesc($orderByCol)
            ->orderByDesc($secondaryOrder)
            ->get();
    }
}