<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PublicTournamentController extends Controller
{
    /**
     * Vista HTML principal (/t/{slug})
     */
    public function show(Request $request, $id)
    {
        // 1. Intentamos buscar por ID primero, si falla, buscamos por Slug (para compatibilidad)
        $tournament = DB::table('tournaments')->where('id', $id)->first();
        if (!$tournament) {
            $tournament = DB::table('tournaments')->where('slug', $id)->first();
        }

        if (!$tournament) abort(404);

        // --- LÓGICA DE PRIVACIDAD ---
        if (isset($tournament->is_private) && $tournament->is_private) {
            $providedCode = $request->query('code'); 
            
            if ($providedCode !== $tournament->access_code) {
                return Inertia::render('Public/RestrictedAccess', [
                    'tournamentName' => $tournament->name,
                    'slug' => $tournament->slug ?? $tournament->id,
                    'tournamentId' => $tournament->id
                ]);
            }
        }
        // -----------------------------

        $matchesProcessed = DB::table('tournament_matches')
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('raw_data')
            ->count();

        $expected = $tournament->expected_matches ?? 5;
        $tournament->progress = "Partida $matchesProcessed / {$expected}";

        return Inertia::render('Public/TournamentLive', [
            'tournament' => $tournament,
            'accessCode' => $request->query('code')
        ]);
    }

    /**
     * API JSON para el Frontend (/api/live/{id}/data)
     */
    public function getPublicData(Request $request, $id)
    {
        $tournament = DB::table('tournaments')->where('id', $id)->first();
        if (!$tournament) return response()->json(['error' => 'Not found'], 404);

        // 1. Recibir filtros
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

        // 4. Calcular Ranking (ESTA ES LA LÓGICA QUE FUNCIONA BIEN)
        $ranking = $this->getFullRanking($id, $mode, $type, $matchId, $sortBy);

        $expected = $tournament->expected_matches ?? 5;

        return response()->json([
            'tournament' => [
                'name' => $tournament->name,
                'progress' => "Partida $matchesProcessed / {$expected}",
                'twitch_channel' => $tournament->twitch_channel ?? null,
                // Agregamos esto para poder mostrar la tabla de puntos en el frontend
                'scoring_format' => $tournament->scoring_format ?? null 
            ],
            'matches' => $matchesList,
            'ranking' => $ranking
        ]);
    }

    // --- MÉTODOS WIDGETS ---

    public function widgetGlobal($id)
    {
        return Inertia::render('Widgets/ObsGlobal', ['tournamentId' => (int)$id]);
    }

    public function widgetPlayer($id, $playerName)
    {
        return Inertia::render('Widgets/ObsGlobal', [ 
            'tournamentId' => (int)$id, 
            'defaultSearch' => $playerName 
        ]);
    }

    public function getWidgetStats(Request $request, $id)
    {
        $mode = $request->query('mode', 'all');
        $sortBy = $request->query('sort', 'points'); 
        $type = $request->query('type', 'players'); 
        $limit = $request->query('limit', 10); 
        $search = $request->query('search'); 
        
        $ranking = $this->getFullRanking($id, $mode, $type, null, $sortBy);

        if ($search) {
            $found = $ranking->first(function($item) use ($search, $type) {
                if ($type === 'teams') {
                    foreach ($item->member_names as $member) {
                        if (stripos($member, $search) !== false) return true;
                    }
                    return false;
                }
                return stripos($item->player_name, $search) !== false;
            });

            return response()->json($found ? [$found] : []);
        }
        
        return response()->json($ranking->take((int)$limit)->values());
    }

    // --- LÓGICA CENTRAL DE CÁLCULO (REFERENCIA DE VERDAD) ---
    
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
                    $members = json_decode($team->members_json);
                    $team->member_names = $members;
                    $team->player_name = is_array($members) ? implode(' + ', $members) : 'Equipo Desconocido';
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
                DB::raw('SUM(kills) as total_kills'),
                DB::raw('SUM(CAST(JSON_EXTRACT(extra_stats, "$.totalPoints") AS DECIMAL(10,2))) as total_points'),
                DB::raw('SUM(CAST(JSON_EXTRACT(extra_stats, "$.killPoints") AS DECIMAL(10,2))) as total_kill_points'),
                DB::raw('SUM(CAST(JSON_EXTRACT(extra_stats, "$.placementPoints") AS DECIMAL(10,2))) as total_placement_points'),
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