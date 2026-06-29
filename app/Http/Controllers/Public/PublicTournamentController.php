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
        // 1. Buscar por ID o Slug
        $tournament = DB::table('tournaments')->where('id', $id)->first();
        if (!$tournament) {
            $tournament = DB::table('tournaments')->where('slug', $id)->first();
        }
        if (!$tournament) abort(404);

        // --- LÓGICA DE PRIVACIDAD ---
        $hasAccess = true;
        if (isset($tournament->is_private) && $tournament->is_private) {
            $sessionKey  = "t_access_{$tournament->id}";
            $providedCode = $request->query('code');

            if ($providedCode === $tournament->access_code) {
                // Guardar acceso en sesión
                session([$sessionKey => true, "t_attempts_{$tournament->id}" => 0]);
                $hasAccess = true;
            } elseif (session($sessionKey)) {
                $hasAccess = true;
            } else {
                $hasAccess = false; // Puede ver la página pero no los códigos
            }
        }
        // -----------------------------

        $matchesProcessed = DB::table('tournament_matches')
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('raw_data')
            ->count();

        $expected = $tournament->expected_matches ?? 5;
        $tournament->progress = "Partida $matchesProcessed / {$expected}";

        if (!empty($tournament->banner_image)) {
            $tournament->banner_image = asset('public/' . $tournament->banner_image);
        }

        $user = auth()->user();
        $registrationStatus = null; // null = not logged in
        $hasPaidRegistration = false;

        if ($user) {
            $reg = DB::table('tournament_registrations')
                ->where('tournament_id', $tournament->id)
                ->where('email', $user->email)
                ->orderByDesc('created_at')
                ->first();

            $registrationStatus = $reg?->payment_status ?? 'none'; // none, pending, paid, rejected
            $hasPaidRegistration = ($registrationStatus === 'paid');
        }

        // Acceso a códigos de partida
        $sessionKey = "t_access_{$tournament->id}";
        $hasSessionAccess = (bool) session($sessionKey);
        $isPrivate = (bool)($tournament->is_private ?? false);

        // Público: códigos visibles solo con inscripción aceptada
        // Privado: códigos visibles con inscripción aceptada + código de acceso válido
        $hasCodeAccess = $hasPaidRegistration && (!$isPrivate || $hasSessionAccess);

        $attemptsLeft = 3 - (int) session("t_attempts_{$tournament->id}", 0);

        return Inertia::render('Public/TournamentLive', [
            'tournament'         => $tournament,
            'accessCode'         => $request->query('code'),
            'isPrivate'          => $isPrivate,
            'hasAccess'          => $hasCodeAccess,
            'hasPrivateSession'  => $hasSessionAccess,
            'registrationStatus' => $registrationStatus, // null, none, pending, paid, rejected
            'attemptsLeft'       => max(0, $attemptsLeft),
        ]);
    }

    /**
     * POST /t/{id}/verify-code  — Verifica el código de acceso con rate limiting (máx 3 intentos)
     */
    public function verifyCode(Request $request, $id)
    {
        $tournament = DB::table('tournaments')->where('id', $id)->first();
        if (!$tournament || !$tournament->is_private) {
            return response()->json(['success' => false, 'message' => 'Torneo no encontrado.'], 404);
        }

        $attemptsKey = "t_attempts_{$tournament->id}";
        $accessKey   = "t_access_{$tournament->id}";
        $attempts    = (int) session($attemptsKey, 0);

        if ($attempts >= 3) {
            return response()->json([
                'success'      => false,
                'blocked'      => true,
                'message'      => 'Has superado el número máximo de intentos. Solicita un nuevo acceso.',
                'attemptsLeft' => 0,
            ], 429);
        }

        $providedCode = trim($request->input('code', ''));

        if ($providedCode === $tournament->access_code) {
            session([$accessKey => true, $attemptsKey => 0]);
            return response()->json(['success' => true, 'message' => '¡Acceso concedido!']);
        }

        $attempts++;
        session([$attemptsKey => $attempts]);
        $left = 3 - $attempts;

        return response()->json([
            'success'      => false,
            'blocked'      => $left <= 0,
            'message'      => $left > 0 ? "Código incorrecto. Te quedan {$left} intento(s)." : 'Has superado el número máximo de intentos. Solicita un nuevo acceso.',
            'attemptsLeft' => max(0, $left),
        ], 401);
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

        // 3. Determinar si el usuario tiene acceso a los códigos de partida
        // Público: necesita inscripción aceptada (paid)
        // Privado: necesita inscripción aceptada + código de acceso en sesión
        $userObj = auth()->user();
        $hasPaidReg = false;
        if ($userObj) {
            $hasPaidReg = DB::table('tournament_registrations')
                ->where('tournament_id', $id)
                ->where('email', $userObj->email)
                ->where('payment_status', 'paid')
                ->exists();
        }
        $sessionKey = "t_access_{$tournament->id}";
        $isPrivateTournament = (bool)($tournament->is_private ?? false);
        $hasAccess = $hasPaidReg && (!$isPrivateTournament || session($sessionKey));

        $matchesList = DB::table('tournament_matches')
            ->where('tournament_id', $id)
            ->orderBy('created_at', 'desc')
            ->select('id', 'game_mode', 'custom_code', 'raw_data', 'created_at')
            ->get()
            ->values()
            ->map(function($m, $index) use ($hasAccess) {
                return [
                    'id'         => $m->id,
                    'mode'       => strtoupper($m->game_mode),
                    'code'       => $hasAccess ? ($m->custom_code ?? '---') : 'Partida ' . ($index + 1),
                    'status'     => is_null($m->raw_data) ? 'En Curso' : 'Finalizada',
                    'is_active'  => is_null($m->raw_data),
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
                'banner_image' => !empty($tournament->banner_image) ? asset('public/' . $tournament->banner_image) : null,
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
                    DB::raw('MAX(member_names) as members_json'), 
                    DB::raw('COUNT(*) as games_played'),
                    DB::raw('SUM(total_kills) as total_kills'),
                    DB::raw('AVG(total_kills) as avg_kills'),
                    DB::raw('MIN(team_match_stats.rank) as best_placement'),
                    DB::raw('AVG(team_match_stats.rank) as avg_placement'),
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
                    $team->member_names = is_array($members) ? $members : [];
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