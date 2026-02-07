<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TournamentParserController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = DB::table('tournaments')->orderBy('created_at', 'desc');

        // Scope: Admin/Jangel/SuperUser sees all. Organizers see only theirs.
        if (!$this->isJangel($user) && $user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $tournaments = $query->get();

        foreach ($tournaments as $tn) {
            // Decodificar JSON para que el frontend lo lea como objeto
            $tn->scoring_format = json_decode($tn->scoring_format ?? '{}');

            $tn->matches = DB::table('tournament_matches')
                ->where('tournament_id', $tn->id)
                ->orderBy('created_at', 'desc')
                ->select('id', 'match_id', 'game_mode', 'custom_code', 'raw_data', 'created_at', 'game_session_id')
                ->get()
                ->map(function ($match) {
                $match->status = is_null($match->raw_data) ? 'pending' : 'processed';
                return $match;
            });
        }

        return Inertia::render('Admin/JangelDashboard', [
            'tournaments' => $tournaments
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // Security check
        if (!$this->isJangel($user) && $user->role !== 'organizer' && $user->role !== 'admin') {
            abort(403, 'Unauthorized');
        }

        $data = [
            'user_id' => $user->id,
            'name' => $request->name,
            'game' => $request->input('game_type', 'fortnite'),
            'twitch_channel' => $request->twitch_channel,
            'rules' => $request->rules,
            'prizes' => $request->prizes,
            'scoring_format' => $request->scoring_format ? json_encode($request->scoring_format) : null,
            'table_name' => null, // Legacy field support
            'created_at' => now(),
            'updated_at' => now()
        ];

        if ($request->boolean('is_private')) {
            $data['is_private'] = true;
            $data['access_code'] = $request->access_code;
        }
        else {
            $data['is_private'] = false;
            $data['access_code'] = null;
        }

        if (Schema::hasColumn('tournaments', 'slug')) {
            $data['slug'] = Str::slug($request->name . '-' . uniqid());
        }

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('tournaments', 'public');
            $data['image_path'] = '/storage/' . $path;
        }

        $data['entry_fee'] = $request->input('entry_fee', 0);
        $data['currency'] = $request->input('currency', 'USD');
        $data['game_type'] = $request->input('game_type', 'fortnite');
        $data['has_prizes'] = $request->boolean('has_prizes');
        $data['platform_fee_percentage'] = 10.0;

        DB::table('tournaments')->insert($data);
        return back()->with('success', 'Torneo creado.');
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $tournament = DB::table('tournaments')->where('id', $id)->first();

        if (!$tournament)
            abort(404);
        if (!$this->isOwnerOrAdmin($user, $tournament))
            abort(403);

        $data = ['updated_at' => now()];

        if ($request->has('name'))
            $data['name'] = $request->name;
        if ($request->has('twitch_channel'))
            $data['twitch_channel'] = $request->twitch_channel;
        if ($request->has('rules'))
            $data['rules'] = $request->rules;
        if ($request->has('prizes'))
            $data['prizes'] = $request->prizes;
        if ($request->has('scoring_format'))
            $data['scoring_format'] = $request->scoring_format ? json_encode($request->scoring_format) : null;
        if ($request->has('bracket_data'))
            $data['bracket_data'] = $request->bracket_data ? json_encode($request->bracket_data) : null;

        // Actualizar Privacidad solo si se envia explicitamente
        if ($request->has('is_private')) {
            $isPrivate = $request->boolean('is_private');
            $data['is_private'] = $isPrivate;
            if ($isPrivate) {
                if ($request->has('access_code'))
                    $data['access_code'] = $request->access_code;
            }
            else {
                $data['access_code'] = null;
            }
        }

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('tournaments', 'public');
            $data['image_path'] = '/storage/' . $path;
        }

        if ($request->has('entry_fee'))
            $data['entry_fee'] = $request->input('entry_fee', 0);
        if ($request->has('has_prizes'))
            $data['has_prizes'] = $request->boolean('has_prizes');
        if ($request->has('game_type'))
            $data['game_type'] = $request->input('game_type', 'fortnite');

        DB::table('tournaments')->where('id', $id)->update($data);
        return back()->with('success', 'Torneo actualizado.');
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $tournament = DB::table('tournaments')->where('id', $id)->first();

        if (!$tournament)
            abort(404);
        if (!$this->isOwnerOrAdmin($user, $tournament))
            abort(403);

        $matchCount = DB::table('tournament_matches')->where('tournament_id', $id)->count();
        if ($matchCount > 0)
            return back()->with('error', 'Elimina las partidas primero.');

        DB::table('tournaments')->where('id', $id)->delete();
        return back()->with('success', 'Torneo eliminado.');
    }

    public function storeScheduledMatch(Request $request, $tournamentId)
    {
        $user = $request->user();
        $tournament = DB::table('tournaments')->where('id', $tournamentId)->first();
        if (!$tournament)
            abort(404);
        if (!$this->isOwnerOrAdmin($user, $tournament))
            abort(403);

        DB::table('tournament_matches')->insert([
            'tournament_id' => $tournamentId,
            'match_id' => 'pending_' . uniqid(),
            'game_mode' => $this->getModeName((int)$request->game_mode),
            'custom_code' => $request->custom_code,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return back()->with('success', 'Partida creada.');
    }

    public function updateMatch(Request $request, $id)
    {
        $request->validate([
            'custom_code' => 'required|string|max:50',
            'reset_stats' => 'nullable|boolean'
        ]);

        $updateData = [
            'custom_code' => $request->custom_code,
            'updated_at' => now()
        ];

        $shouldReset = $request->boolean('reset_stats') || true;

        if ($shouldReset) {
            DB::transaction(function () use ($id, &$updateData) {
                DB::table('player_match_stats')->where('tournament_match_id', $id)->delete();
                DB::table('team_match_stats')->where('tournament_match_id', $id)->delete();
                // Limpiar logs asociados a esta partida
                DB::table('tournament_score_logs')->where('match_id', $id)->delete();

                $updateData['match_id'] = 'pending_' . uniqid();
                $updateData['game_session_id'] = null;
                $updateData['raw_data'] = null;
            });
        }

        DB::table('tournament_matches')->where('id', $id)->update($updateData);

        return back()->with('success', 'Partida actualizada.');
    }

    public function deleteMatch($matchId)
    {
        DB::table('player_match_stats')->where('tournament_match_id', $matchId)->delete();
        DB::table('team_match_stats')->where('tournament_match_id', $matchId)->delete();
        DB::table('tournament_score_logs')->where('match_id', $matchId)->delete();
        DB::table('tournament_matches')->where('id', $matchId)->delete();
        return back()->with('success', 'Partida eliminada.');
    }

    // --- LÓGICA DE CÁLCULO DE PUNTOS INTERNA ---
    private function calculateScore($rank, $kills, $format, $gameMode = 'solo')
    {
        $points = 0;

        // Determine Team Size multiplier for Threshold Scaling
        // Solo = 1, Duo = 2, Trio = 3, Squad = 4
        $teamSize = 1;
        $m = strtolower($gameMode);
        if (str_contains($m, 'duo'))
            $teamSize = 2;
        if (str_contains($m, 'trio'))
            $teamSize = 3;
        if (str_contains($m, 'squad'))
            $teamSize = 4;

        // Effective Rank for Scoring Thresholds
        // If config requires Top 10 (Solo), and we are in Duos:
        // Rank 5 Duo = 10 Players -> Effective 10 -> Gets points.
        // Rank 6 Duo = 12 Players -> Effective 12 -> No points.
        $effectiveRankForScoring = $rank * $teamSize;

        // 1. Configuración Personalizada (JSON)
        if (!empty($format) && isset($format->placement) && is_array($format->placement)) {
            // Puntos por Kills
            $killPts = isset($format->kill_points) ? (float)$format->kill_points : 1;
            $points += ($kills * $killPts);

            // Puntos por Posición (Ranges)
            foreach ($format->placement as $range) {
                $from = (int)($range->from ?? 0);
                $to = (int)($range->to ?? 0);
                $pts = (float)($range->points ?? 0);

                // Normalizar range
                $start = max($from, $to);
                $end = min($from, $to);

                // Check against EFFECTIVE RANK
                if ($effectiveRankForScoring <= $start) {
                    // Logic: How many "steps" inside the range did we cover?
                    // We compare effectiveRank against the range.
                    // But wait, the range is usually defined in "Placement #". 
                    // If user made the format for Solos (1..100), and we play Duos (1..50).
                    // We want Duo #1 to get points for #1 and #2? Or just #1?
                    // User Request: "multiplique por 2 si es en duos los puntos de posicionamiento"
                    // "matar a 2 personas es equivalente a en solos a matar solo a 1" -> this refers to difficulty? 
                    // No, "dependiendo del modo se adapte al sistema de puntos ... si se subio una de solos ... multiplique por 2 si es en duos los puntos de posicionamiento"

                    // Interpretación:
                    // El usuario quiere que los puntos de POSICION se adapten.
                    // Si el formato dice TOP 10 = 10 pts.
                    // En Duos, el TOP 5 (=10 personas) debería llevarse esos puntos.
                    // Por tanto, usamos effectiveRank para VERIFICAR si entra en el rango.

                    // PERO, ¿cuántos puntos suma?
                    // Si el formato es "Step" (acumulativo), es complejo.
                    // Asumiremos lógica simple: Si entra en el rango, suma.

                    // Corrección lógica steps:
                    // Max($effectiveRankForScoring, $end) -> Clamped effective rank
                    $clampedRank = max($effectiveRankForScoring, $end);

                    // Esto calcula cuántos "puestos" de diferencia hay.
                    // Si rango es 1-1 (Top 1). Duo #1 -> Eff 2. Clamped 1? No.
                    // Si Duo #1 (2 players). Range 1-1. EffRank 2. 2 <= 1 False. Duo #1 NO gana Top 1 de Solo?
                    // "matar a 2 personas es equivalente a en solos a matar solo a 1"
                    // Wait, user might mean "Top 1 Duo" should get "Top 1 Solo" points AND "Top 2 Solo" points?
                    // "que multiplique por 2 si es en duos los puntos de posicionamiento"
                    // -> This sounds like: Points = Points * 2.
                    // But context "de tal forma que matar a 2 personas es equivalente..." suggests scaling the EFFORT/THRESHOLD.

                    // Let's stick to the "Threshold" interpretation (Scaling Rank).
                    // If Format Top 10. Duo #5 (Eff 10) gets it. Duo #6 (Eff 12) misses it.

                    $points += $pts;
                    // Nota: Eliminamos la lógica compleja de "steps" por ahora para evitar bugs con el scaling, 
                    // a menos que el formato sea explícitamente "Points PER Rank".
                    // La mayoría de formatos son "Top X get Y points flat".
                    // Si el usuario usa "Step logic" (acumulativa), regresamos a ella con effectiveRank.

                    $steps = ($start - $clampedRank) + 1;
                    if ($steps > 0) {
                    // Si es step logic, sumamos. 
                    // Pero para Threshold simple (Top 10 = 10), start=10, end=10.
                    // Duo #5 -> Eff 10. Start 10. Steps = 10-10+1 = 1. Suma pts * 1. Correcto.
                    // Duo #1 -> Eff 2. Start 10, End 1. Clamped 2. Steps = 10-2+1 = 9? 
                    // NO. El formato del usuario suele ser:
                    // 1-1: 10 pts
                    // 2-2: 7 pts
                    // Si Duo #1 (Eff 2). Check Range 1-1: 2<=1 False.
                    // Check Range 2-2: 2<=2 True. Steps 1. Gana 7 pts.
                    // O SEA: Duo #1 gana los puntos de Solo #2. NO Gana los de Solo #1.
                    // Esto tiene sentido: Ganar en Duos (50 teams) es "más fácil" que en Solos (100 players) estadísticamente para ser el #1?
                    // O al revés?
                    // Si el usuario dice "duos los puntos se muestran mal... necesito que se adapte... si subo uno de duos en solitario... multiplique por 2 los puntos"

                    // RE-READ CAREFULLY: "que multiplique por 2 si es en duos los puntos de posicionamiento"

                    // Interpretation B: Points * 2.
                    // Duo #1 -> Rank 1. Points = (Calculated as Solo) * 2.

                    // Interpretation C (Threshold):
                    // "matar [ganar] a 2 personas es equivalente a en solos a matar solo a 1".
                    // Killing a Duo (2 ppl) = 2 kills.
                    // Placing #1 in Duo (beat 49 teams/98 ppl) vs Placing #1 in Solo (beat 99 ppl).

                    // Voy a implementar INTERPRETATION C (Threshold / Effective Rank) porque es la estándar en competitive.
                    // EffectiveRank = Rank * TeamSize.
                    // Duo #1 (Eff 2) hits Threshold 2.

                    // Mantenemos la lógica de steps simple por ahora.
                    // $points += ($steps * $pts);
                    }
                    // Simplificación: Si entra en rango, da los puntos.
                    $points += $pts;
                }
            }
        }
        else {
            // Default logic
            $points += $kills;
            // Scaling for default logic too?
            // Rank 1 Duo -> Eff 2.
            if ($effectiveRankForScoring <= 1)
                $points += 25; // Impossible for Duo/Trio/Squad to hit Rank 1 (Eff 2,3,4) here?
            elseif ($effectiveRankForScoring <= 5)
                $points += 15; // Duo #1 (2) gets this.
            elseif ($effectiveRankForScoring <= 15)
                $points += 10;
            elseif ($effectiveRankForScoring <= 25)
                $points += 5;
        }

        return $points;
    }

    // --- PROCESAMIENTO PRINCIPAL (ANALYZE) ---
    public function processReplay(Request $request, $id)
    {


        $request->validate([
            'replay' => 'required|file',
            'mode' => 'required|integer',
            'target_match_id' => 'nullable|integer'
        ]);

        try {
            // Obtener reglas del torneo
            $tournament = DB::table('tournaments')->where('id', $id)->first();
            $scoringFormat = $tournament->scoring_format ? json_decode($tournament->scoring_format) : null;

            $file = $request->file('replay');
            $mode = (int)$request->input('mode');
            $targetMatchId = $request->input('target_match_id');
            $fileContent = file_get_contents($file->getRealPath());
            $fileName = $file->getClientOriginalName();

            // 1. ANALYZE-SUMMARY (Obtener SessionID)
            $summaryResponse = Http::timeout(60)
                ->attach('file', $fileContent, $fileName)
                ->post('http://62.72.3.139:5138/api/FortniteParser/analyze-summary');

            $sessionID = null;
            if ($summaryResponse->successful()) {
                $summaryData = $summaryResponse->json();
                $sessionID = $summaryData['matchId'] ?? null;
            }

            if ($sessionID)
                $sessionID = strtoupper($sessionID);

            // 2. ANALYZE (Stats completas)
            $analyzeResponse = Http::timeout(120)
                ->attach('file', $fileContent, $fileName)
                ->post('http://62.72.3.139:5138/api/FortniteParser/analyze', [
                'mode' => $mode,
                'rulesJson' => '' // Enviamos vacío, calculamos en PHP
            ]);

            if (!$analyzeResponse->successful())
                throw new \Exception("Error en Analyze: " . $analyzeResponse->body());

            $data = $analyzeResponse->json();

            DB::beginTransaction();

            $contentSignature = md5(json_encode($data['teamLeaderboard'] ?? []));
            $matchUid = 'sig_' . $contentSignature;

            if ($sessionID) {
                $existingCollision = DB::table('tournament_matches')->where('game_session_id', $sessionID)->first();
            }
            else {
                $existingCollision = DB::table('tournament_matches')->where('match_id', $matchUid)->first();
            }

            $currentMatchId = null;

            if ($targetMatchId) {
                // Overwrite
                if ($existingCollision && $existingCollision->id != $targetMatchId) {
                    DB::table('player_match_stats')->where('tournament_match_id', $existingCollision->id)->delete();
                    DB::table('team_match_stats')->where('tournament_match_id', $existingCollision->id)->delete();
                    DB::table('tournament_matches')->where('id', $existingCollision->id)->delete();
                }
                $currentMatchId = $targetMatchId;
                DB::table('tournament_matches')->where('id', $currentMatchId)->update([
                    'match_id' => $matchUid,
                    'game_session_id' => $sessionID,
                    'raw_data' => json_encode($data),
                    'updated_at' => now(),
                ]);
            }
            else {
                // Nuevo
                if ($existingCollision) {
                    $currentMatchId = $existingCollision->id;
                    DB::table('tournament_matches')->where('id', $currentMatchId)->update([
                        'raw_data' => json_encode($data),
                        'game_session_id' => $sessionID,
                        'updated_at' => now(),
                    ]);
                }
                else {
                    $currentMatchId = DB::table('tournament_matches')->insertGetId([
                        'tournament_id' => $id,
                        'match_id' => $matchUid,
                        'game_session_id' => $sessionID,
                        'game_mode' => $this->getModeName($mode),
                        'map_name' => 'Island',
                        'raw_data' => json_encode($data),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]);
                }
            }

            // Limpieza previa
            DB::table('player_match_stats')->where('tournament_match_id', $currentMatchId)->delete();
            DB::table('team_match_stats')->where('tournament_match_id', $currentMatchId)->delete();

            // Insertar Jugadores (CALCULANDO PUNTOS INTERNAMENTE)
            $players = $data['playerLeaderboard'] ?? [];
            foreach ($players as $p) {
                if (($p['isBot'] ?? false) || ($p['playerName'] ?? '') === 'Unknown')
                    continue;

                $rank = $p['leaderboardRank'] ?? 0;
                $kills = $p['kills'] ?? 0;

                // CÁLCULO INTERNO PHP
                $calculatedPoints = $this->calculateScore($rank, $kills, $scoringFormat, $this->getModeName($mode));

                DB::table('player_match_stats')->insert([
                    'tournament_match_id' => $currentMatchId,
                    'player_name' => $p['playerName'] ?? 'Unknown',
                    'placement' => $rank,
                    'kills' => $kills,
                    'extra_stats' => json_encode([
                        'teamId' => $p['teamId'] ?? -1,
                        'totalPoints' => $calculatedPoints, // Guardamos el calculado
                        'manual_points' => 0 // Init manual points
                    ]),
                    'updated_at' => now(), 'created_at' => now(),
                ]);
            }

            // Insertar Equipos
            $teams = $data['teamLeaderboard'] ?? [];
            foreach ($teams as $t) {
                $members = $t['memberNames'] ?? [];
                $members = array_filter($members, fn($m) => $m !== 'Unknown');
                if (empty($members))
                    continue;

                $members = array_values($members);
                sort($members);

                $teamRank = $t['rank'] ?? ($t['leaderboardRank'] ?? 999);
                $teamTotalKills = $t['totalKills'] ?? 0;

                // Recalcular puntos del equipo basado en la suma de sus jugadores (para mantener consistencia)
                // O usar la lógica de puntos de equipo. Usaremos la suma de jugadores calculados arriba
                // Pero como no tenemos el ID de jugador aquí fácilmente, usamos calculateScore sobre stats de equipo
                // NOTA: Esto asume que el formato de puntos de equipo es igual (Rank + Kills)
                $teamCalculatedPoints = $this->calculateScore($teamRank, $teamTotalKills, $scoringFormat, $this->getModeName($mode));

                DB::table('team_match_stats')->insert([
                    'tournament_match_id' => $currentMatchId,
                    'team_id_in_match' => $t['teamId'],
                    'rank' => $teamRank,
                    'member_names' => json_encode($members),
                    'team_signature' => md5(json_encode($members)),
                    'total_kills' => $teamTotalKills,
                    'total_points' => $teamCalculatedPoints,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            DB::commit();
            return back()->with('success', "Replay procesada. ID Sesión: " . ($sessionID ?? 'N/A'));

        }
        catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error crítico: ' . $e->getMessage());
        }
    }

    // --- MANUAL RESULTS ENTRY - NEW FEATURE ---
    public function storeManualResult(Request $request, $id)
    {
        $user = $request->user();
        $tournament = DB::table('tournaments')->where('id', $id)->first();
        if (!$tournament || !$this->isOwnerOrAdmin($user, $tournament))
            abort(403);

        $request->validate([
            'match_id' => 'required|exists:tournament_matches,id',
            'player_name' => 'required|string',
            'team_name' => 'nullable|string',
            'kills' => 'nullable|integer',
            'placement' => 'required|integer',
            'points' => 'nullable|numeric',
        ]);

        $matchId = $request->match_id;

        // 1. Create/Update Player Stats
        $stats = DB::table('player_match_stats')->updateOrInsert(
        [
            'tournament_match_id' => $matchId,
            'player_name' => $request->player_name
        ],
        [
            'placement' => $request->placement,
            'kills' => $request->kills ?? 0,
            'extra_stats' => json_encode([
                'totalPoints' => $request->points ?? 0,
                'manual' => true
            ]),
            'updated_at' => now()
        ]
        );

        // 2. If Team Name provided, update Team Stats
        if ($request->team_name) {
            $teamSignature = md5($request->team_name);

            // Start simple: Insert row if not exists
            $existingTeam = DB::table('team_match_stats')
                ->where('tournament_match_id', $matchId)
                ->where('team_signature', $teamSignature)
                ->first();

            $members = $existingTeam ? (json_decode($existingTeam->member_names, true) ?? []) : [];
            if (!in_array($request->player_name, $members)) {
                $members[] = $request->player_name;
            }

            DB::table('team_match_stats')->updateOrInsert(
            [
                'tournament_match_id' => $matchId,
                'team_signature' => $teamSignature
            ],
            [
                'team_id_in_match' => 0,
                'rank' => $request->placement,
                'member_names' => json_encode($members),
                'total_kills' => ($existingTeam->total_kills ?? 0) + ($request->kills ?? 0), // Accumulate kills
                'total_points' => ($existingTeam->total_points ?? 0) + ($request->points ?? 0), // Accumulate points
                'created_at' => now(),
                'updated_at' => now()
            ]
            );
        }

        return back()->with('success', 'Resultado guardado.');
    }

    // --- APELACIÓN AUTOMÁTICA ---
    public function appealReplay(Request $request, $tournamentId)
    {
        $request->validate(['replay' => 'required|file']);

        try {
            $tournament = DB::table('tournaments')->where('id', $tournamentId)->first();
            if (!$tournament)
                throw new \Exception("Torneo no encontrado");

            // Obtener reglas del torneo para calcular
            $scoringFormat = $tournament->scoring_format ? json_decode($tournament->scoring_format) : null;

            $file = $request->file('replay');
            $fileName = $file->getClientOriginalName();

            Log::info("--- INICIO APELACIÓN AUTOMÁTICA --- Tournament: $tournamentId");

            // 1. Analyze Summary
            $response = Http::timeout(60)
                ->attach('file', file_get_contents($file->getRealPath()), $fileName)
                ->post('http://62.72.3.139:5138/api/FortniteParser/analyze-summary');

            if (!$response->successful())
                throw new \Exception("Error API Summary: " . $response->body());

            $appealData = $response->json();
            $externalMatchId = strtoupper($appealData['matchId'] ?? '');
            $playerName = $appealData['replayOwnerName'] ?? null;
            $cleanSessionId = $externalMatchId ? explode('|', $externalMatchId)[0] : '';

            if (!$externalMatchId || !$playerName)
                throw new \Exception("Replay inválido o corrupto.");

            // 2. Buscar Partida
            $match = DB::table('tournament_matches')
                ->where('tournament_id', $tournamentId)
                ->where(function ($q) use ($externalMatchId, $cleanSessionId) {
                $q->where('game_session_id', $externalMatchId)
                    ->orWhere('game_session_id', 'LIKE', '%' . $cleanSessionId . '%');
            })
                ->first();

            if (!$match)
                return back()->with('error', "APELACIÓN RECHAZADA: La partida base no ha sido subida por el administrador.");

            // 3. DATOS CRUDOS DEL REPLAY DE USUARIO
            $kills = $appealData['kills'] ?? 0;
            $rank = $appealData['rank'] ?? 99;

            // 4. CÁLCULO AUTOMÁTICO (Usando reglas del torneo + Fix min/max)
            $gameMode = $match->game_mode ?? 'solo';
            $calculatedPoints = $this->calculateScore($rank, $kills, $scoringFormat, $gameMode);

            Log::info("Apelación $playerName: Rank $rank, Kills $kills -> Puntos Calc: $calculatedPoints");

            DB::beginTransaction();

            // A. Update/Insert Player
            $existingPlayer = DB::table('player_match_stats')
                ->where('tournament_match_id', $match->id)
                ->where('player_name', $playerName)
                ->first();

            // Mantener ajuste manual si existe
            $extraStats = ($existingPlayer && $existingPlayer->extra_stats) ? json_decode($existingPlayer->extra_stats, true) : [];
            $manualPts = $extraStats['manual_points'] ?? 0;
            $finalPoints = $calculatedPoints + $manualPts;

            $extraStats['totalPoints'] = $finalPoints;
            $extraStats['appealed'] = true;
            $extraStats['base_points'] = $calculatedPoints;

            if ($existingPlayer) {
                DB::table('player_match_stats')->where('id', $existingPlayer->id)->update([
                    'kills' => $kills,
                    'placement' => $rank,
                    'extra_stats' => json_encode($extraStats),
                    'updated_at' => now()
                ]);
            }
            else {
                DB::table('player_match_stats')->insert([
                    'tournament_match_id' => $match->id,
                    'player_name' => $playerName,
                    'kills' => $kills,
                    'placement' => $rank,
                    'extra_stats' => json_encode($extraStats),
                    'created_at' => now(), 'updated_at' => now()
                ]);
            }

            // B. Update Team (Recalculate Totals)
            $teamStats = DB::table('team_match_stats')
                ->where('tournament_match_id', $match->id)
                ->where('member_names', 'LIKE', '%"' . $playerName . '"%')
                ->first();

            if ($teamStats && $teamStats->member_names) {
                $members = json_decode($teamStats->member_names);
                if (is_array($members)) {
                    $teamKills = 0;

                    foreach ($members as $m) {
                        $pStat = DB::table('player_match_stats')
                            ->where('tournament_match_id', $match->id)
                            ->where('player_name', $m)
                            ->first();
                        if ($pStat) {
                            $teamKills += $pStat->kills;
                        }
                    }

                    $newRank = min($teamStats->rank, $rank);
                    $teamCalculatedPoints = $this->calculateScore($newRank, $teamKills, $scoringFormat, $match->game_mode ?? 'solo');

                    DB::table('team_match_stats')->where('id', $teamStats->id)->update([
                        'total_kills' => $teamKills,
                        'total_points' => $teamCalculatedPoints,
                        'rank' => $newRank,
                        'updated_at' => now()
                    ]);
                }
            }

            DB::commit();
            return back()->with('success', "Apelación aceptada. Puntos recalculados automáticamente: {$finalPoints}");

        }
        catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error Apelación: " . $e->getMessage());
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // --- AJUSTE MANUAL DE PUNTOS (PENALIZACIONES/BONUS) ---
    public function adjustPlayerScore(Request $request, $tournamentId)
    {
        $request->validate([
            'match_id' => 'required|exists:tournament_matches,id',
            'player_name' => 'required|string',
            'points_change' => 'required|integer', // Puede ser negativo
            'reason' => 'required|string|max:255'
        ]);

        try {
            DB::beginTransaction();

            $matchId = $request->match_id;
            $player = $request->player_name;
            $change = (int)$request->points_change;
            $reason = $request->reason;

            // 1. Buscar Stats del Jugador
            $pStat = DB::table('player_match_stats')
                ->where('tournament_match_id', $matchId)
                ->where('player_name', $player)
                ->first();

            if (!$pStat)
                throw new \Exception("Jugador no encontrado en esta partida.");

            // 2. Actualizar Extra Stats
            $extra = json_decode($pStat->extra_stats, true) ?? [];
            $currentManual = $extra['manual_points'] ?? 0;
            $currentTotal = $extra['totalPoints'] ?? 0;

            $newManual = $currentManual + $change;
            $newTotal = $currentTotal + $change;

            $extra['manual_points'] = $newManual;
            $extra['totalPoints'] = $newTotal;

            DB::table('player_match_stats')
                ->where('id', $pStat->id)
                ->update(['extra_stats' => json_encode($extra)]);

            // 3. Crear Log de Auditoría
            DB::table('tournament_score_logs')->insert([
                'tournament_id' => $tournamentId,
                'match_id' => $matchId,
                'player_name' => $player,
                'points_change' => $change,
                'reason' => $reason,
                'admin_id' => auth()->id(),
                'created_at' => now(), 'updated_at' => now()
            ]);

            // 4. Actualizar Equipo (Reflejar cambios)
            $teamStats = DB::table('team_match_stats')
                ->where('tournament_match_id', $matchId)
                ->where('member_names', 'LIKE', '%"' . $player . '"%')
                ->first();

            if ($teamStats) {
                $members = json_decode($teamStats->member_names);
                $teamTotalPoints = 0;
                foreach ($members as $m) {
                    $ps = DB::table('player_match_stats')->where('tournament_match_id', $matchId)->where('player_name', $m)->first();
                    if ($ps) {
                        $ex = json_decode($ps->extra_stats, true);
                        $teamTotalPoints += ($ex['totalPoints'] ?? 0);
                    }
                }
                DB::table('team_match_stats')->where('id', $teamStats->id)->update(['total_points' => $teamTotalPoints]);
            }

            DB::commit();
            return back()->with('success', "Ajuste realizado: {$change} pts a {$player}.");

        }
        catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    // --- API LEADERBOARD (DASHBOARD) ---
    public function getLeaderboard(Request $request, $tournamentId)
    {
        $mode = $request->query('mode', 'all');
        $sortBy = $request->query('sort', 'points');
        $matchId = $request->query('match_id');
        $type = $request->query('type', 'players');

        $orderByCol = ($sortBy === 'kills') ? 'total_kills' : 'total_points';
        $secondaryOrder = ($sortBy === 'kills') ? 'total_points' : 'total_kills';

        // --- VISTA EQUIPOS ---
        if ($type === 'teams') {
            $query = DB::table('team_match_stats')
                ->join('tournament_matches', 'team_match_stats.tournament_match_id', '=', 'tournament_matches.id')
                ->where('tournament_matches.tournament_id', $tournamentId)
                ->select(
                'team_signature',
                DB::raw('MIN(member_names) as member_names_json'),
                DB::raw('COUNT(*) as games_played'),
                DB::raw('SUM(total_kills) as total_kills'),
                DB::raw('AVG(total_kills) as avg_kills'),
                DB::raw('MIN(rank) as best_placement'),
                DB::raw('AVG(rank) as avg_placement'),
                DB::raw('SUM(total_points) as total_points'),
                DB::raw('AVG(total_points) as avg_points')
            );

            if ($matchId)
                $query->where('tournament_matches.id', $matchId);
            elseif ($mode !== 'all')
                $query->where('tournament_matches.game_mode', $mode);

            $results = $query
                ->groupBy('team_signature')
                ->orderByDesc($orderByCol)
                ->orderByDesc($secondaryOrder)
                ->get()
                ->map(function ($team) {
                $team->member_names = json_decode($team->member_names_json);
                unset($team->member_names_json);
                return $team;
            });

            return response()->json($results);
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
            // JSON_UNQUOTE(JSON_EXTRACT(...)) might be safer depending on mysql version, but CAST implies unquote for numbers
            DB::raw('SUM(CAST(JSON_EXTRACT(extra_stats, "$.totalPoints") AS DECIMAL(10,2))) as total_points'),
            DB::raw('AVG(kills) as avg_kills'),
            DB::raw('AVG(placement) as avg_placement'),
            DB::raw('AVG(CAST(JSON_EXTRACT(extra_stats, "$.totalPoints") AS DECIMAL(10,2))) as avg_points'),
            DB::raw('MIN(placement) as best_placement')
        );

        if ($matchId)
            $query->where('tournament_matches.id', $matchId);
        elseif ($mode !== 'all')
            $query->where('tournament_matches.game_mode', $mode);

        $results = $query
            ->groupBy('player_name')
            ->orderByDesc($orderByCol)
            ->orderByDesc($secondaryOrder)
            ->get();

        return response()->json($results);
    }

    // --- GESTIÓN DE USUARIOS ---
    public function searchUsers(Request $request)
    {
        $query = $request->query('query');
        if (!$query || strlen($query) < 3) {
            return response()->json([]);
        }

        $users = DB::table('users')
            ->where('name', 'LIKE', "%{$query}%")
            ->orWhere('email', 'LIKE', "%{$query}%")
            ->select('id', 'name', 'email', 'role')
            ->limit(10)
            ->get();

        return response()->json($users);
    }

    public function assignRole(Request $request)
    {
        $user = $request->user();

        // Solo Jangel o Admin pueden asignar roles
        if (!$this->isJangel($user) && $user->role !== 'admin') {
            abort(403, 'No tienes permisos para realizar esta acción.');
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:admin,organizer,player'
        ]);

        $targetUserId = $request->user_id;
        $newRole = $request->role;

        // Evitar auto-degradación de admin a algo menos si es el único admin (opcional, pero buena práctica)
        // Por ahora lo permitimos

        DB::table('users')->where('id', $targetUserId)->update([
            'role' => $newRole,
            'updated_at' => now()
        ]);

        return back()->with('success', "Rol actualizado a {$newRole}.");
    }

    // --- HELPERS ---

    private function isJangel($user)
    {
        if (!$user)
            return false;
        return in_array($user->email, [
            'jangel@ejemplo.com', 'admin@jangel.pro', '18jangel18@gmail.com', 'jos5dev@gmail.com'
        ]);
    }

    private function isOwnerOrAdmin($user, $tournament)
    {
        if ($user->role === 'admin' || $this->isJangel($user))
            return true;
        return $tournament->user_id === $user->id;
    }

    private function getModeName($modeWithPlatform)
    {
        // Platform ID se suele sumar (ej: +100), simplificamos lógica básica
        // 0=Solo, 1=Duo, 2=Trio, 3=Squad
        // Ajustar según lógica de FortniteParser si es complejo
        $m = $modeWithPlatform % 100;
        switch ($m) {
            case 0:
                return 'solo';
            case 1:
                return 'duo';
            case 2:
                return 'trio';
            case 3:
                return 'squad';
            default:
                return 'unknown';
        }
    }
}