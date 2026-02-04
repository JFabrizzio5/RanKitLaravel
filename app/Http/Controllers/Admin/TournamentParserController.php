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
    /**
     * Asegura que la infraestructura de DB esté lista.
     */
    protected function ensureDatabaseIsReady()
    {
        // 1. Tabla de Torneos
        if (!Schema::hasTable('tournaments')) {
            Schema::create('tournaments', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->string('twitch_channel')->nullable();
                $table->boolean('is_private')->default(false);
                $table->string('access_code')->nullable();
                $table->text('rules')->nullable(); // Campo de reglas
                $table->string('table_name')->nullable(); // Para compatibilidad si es necesario
                $table->timestamps();
            });
        } else {
             Schema::table('tournaments', function (Blueprint $table) {
                if (!Schema::hasColumn('tournaments', 'twitch_channel')) {
                    $table->string('twitch_channel')->nullable();
                }
                if (!Schema::hasColumn('tournaments', 'is_private')) {
                    $table->boolean('is_private')->default(false);
                }
                if (!Schema::hasColumn('tournaments', 'access_code')) {
                    $table->string('access_code')->nullable();
                }
                if (!Schema::hasColumn('tournaments', 'rules')) {
                    $table->text('rules')->nullable();
                }
                // Asegurar que table_name exista si la DB lo requiere, aunque sea nullable
                if (!Schema::hasColumn('tournaments', 'table_name')) {
                    $table->string('table_name')->nullable();
                }
             });
        }

        // 2. Tabla de Partidas (Matches)
        if (!Schema::hasTable('tournament_matches')) {
            Schema::create('tournament_matches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tournament_id');
                $table->string('match_id')->unique(); // Firma hash o pending ID
                $table->string('game_session_id')->nullable()->index(); // EL ID REAL DEL JUEGO (CK-MIATEATRO...)
                $table->string('game_mode')->default('solo');
                $table->string('map_name')->nullable();
                $table->string('custom_code')->nullable(); 
                $table->json('raw_data')->nullable(); 
                $table->timestamps();
            });
        } else {
            Schema::table('tournament_matches', function (Blueprint $table) {
                if (!Schema::hasColumn('tournament_matches', 'game_session_id')) {
                    $table->string('game_session_id')->nullable()->index();
                }
            });
        }

        // 3. Tabla de Estadísticas de Jugadores
        if (!Schema::hasTable('player_match_stats')) {
            Schema::create('player_match_stats', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tournament_match_id');
                $table->string('player_name');
                $table->integer('placement')->default(0);
                $table->integer('kills')->default(0);
                $table->integer('damage_done')->default(0);
                $table->integer('damage_taken')->default(0);
                $table->json('extra_stats')->nullable();
                $table->timestamps();
            });
        }

        // 4. Tabla de Estadísticas de Equipos
        if (!Schema::hasTable('team_match_stats')) {
            Schema::create('team_match_stats', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tournament_match_id');
                $table->integer('team_id_in_match');
                $table->integer('rank');
                $table->json('member_names');
                $table->string('team_signature');
                $table->integer('total_kills');
                $table->integer('total_points');
                $table->timestamps();
            });
        }
    }

    public function index()
    {
        $this->ensureDatabaseIsReady();
        $tournaments = DB::table('tournaments')->orderBy('created_at', 'desc')->get();

        foreach($tournaments as $tn) {
            $tn->matches = DB::table('tournament_matches')
                ->where('tournament_id', $tn->id)
                ->orderBy('created_at', 'desc')
                ->select('id', 'match_id', 'game_mode', 'custom_code', 'raw_data', 'created_at', 'game_session_id') 
                ->get()
                ->map(function($match) {
                    $match->status = is_null($match->raw_data) ? 'pending' : 'processed';
                    return $match;
                });
        }

        return Inertia::render('Admin/JangelDashboard', [
            'tournaments' => $tournaments
        ]);
    }
    
    public function store(Request $request) {
        $this->ensureDatabaseIsReady();
        
        $data = [
            'name' => $request->name,
            'twitch_channel' => $request->twitch_channel,
            'rules' => $request->rules,
            'created_at' => now(), 
            'updated_at' => now()
        ];

        // Lógica de Privacidad
        if ($this->isJangel(auth()->user()) && $request->boolean('is_private')) {
            $data['is_private'] = true;
            $data['access_code'] = $request->access_code;
        } else {
            $data['is_private'] = false;
            $data['access_code'] = null;
        }

        if (Schema::hasColumn('tournaments', 'slug')) {
            $data['slug'] = Str::slug($request->name);
        }

        if (Schema::hasColumn('tournaments', 'table_name')) {
            $data['table_name'] = 'tn_' . Str::slug($request->name, '_') . '_' . time();
        }

        DB::table('tournaments')->insert($data);
        return back()->with('success', 'Torneo creado.');
    }
    
    public function update(Request $request, $id) {
        $data = [
            'name' => $request->name,
            'updated_at' => now(),
        ];
        
        if (Schema::hasColumn('tournaments', 'twitch_channel')) {
            $data['twitch_channel'] = $request->twitch_channel;
        }
        
        if (Schema::hasColumn('tournaments', 'rules')) {
            $data['rules'] = $request->rules;
        }

        // Actualizar Privacidad
        if (Schema::hasColumn('tournaments', 'is_private')) {
            if ($this->isJangel(auth()->user())) {
                $isPrivate = $request->boolean('is_private');
                $data['is_private'] = $isPrivate;
                $data['access_code'] = $isPrivate ? $request->access_code : null;
            }
        }

        DB::table('tournaments')->where('id', $id)->update($data);
        return back()->with('success', 'Torneo actualizado.');
    }

    public function destroy($id) {
        $matchCount = DB::table('tournament_matches')->where('tournament_id', $id)->count();
        if ($matchCount > 0) return back()->with('error', 'Elimina las partidas primero.');
        DB::table('tournaments')->where('id', $id)->delete();
        return back()->with('success', 'Torneo eliminado.');
    }

    public function storeScheduledMatch(Request $request, $tournamentId) {
        $this->ensureDatabaseIsReady();
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

        // LOGICA DE RESTABLECIMIENTO (Hard Reset por defecto al editar)
        $shouldReset = $request->boolean('reset_stats') || true;

        if ($shouldReset) {
            DB::transaction(function () use ($id, &$updateData) {
                // 1. Borrar estadísticas asociadas
                DB::table('player_match_stats')->where('tournament_match_id', $id)->delete();
                DB::table('team_match_stats')->where('tournament_match_id', $id)->delete();

                // 2. Restablecer identificadores para evitar colisiones con el archivo "malo" anterior
                $updateData['match_id'] = 'pending_' . uniqid(); 
                $updateData['game_session_id'] = null; 
                $updateData['raw_data'] = null; 
            });
        }

        DB::table('tournament_matches')->where('id', $id)->update($updateData);

        return back()->with('success', 'Partida actualizada y datos restablecidos correctamente.');
    }

    public function deleteMatch($matchId) {
        DB::table('player_match_stats')->where('tournament_match_id', $matchId)->delete();
        DB::table('team_match_stats')->where('tournament_match_id', $matchId)->delete();
        DB::table('tournament_matches')->where('id', $matchId)->delete();
        return back()->with('success', 'Partida eliminada.');
    }

    // --- PROCESAMIENTO PRINCIPAL (ANALYZE) ---
    public function processReplay(Request $request, $id)
    {
        $this->ensureDatabaseIsReady();
        
        $request->validate([
            'replay' => 'required|file',
            'mode' => 'required|integer', 
            'target_match_id' => 'nullable|integer' 
        ]);

        try {
            $file = $request->file('replay');
            $mode = (int)$request->input('mode');
            $targetMatchId = $request->input('target_match_id');
            $fileContent = file_get_contents($file->getRealPath());
            $fileName = $file->getClientOriginalName();

            // 1. PRIMERA PETICIÓN: ANALYZE-SUMMARY (Para obtener el ID real de la sesión)
            // CAMBIO CRITICO: Usamos analyze-summary en lugar de summary
            $summaryResponse = Http::timeout(60)
                ->attach('file', $fileContent, $fileName)
                ->post('http://62.72.3.139:5138/api/FortniteParser/analyze-summary');

            // Log de depuración para ver qué devuelve EXACTAMENTE la API
            $sessionID = null;
            if ($summaryResponse->successful()) {
                $summaryData = $summaryResponse->json();
                $sessionID = $summaryData['matchId'] ?? null;
                Log::info("ProcessReplay - RAW Summary matchId: " . json_encode($sessionID));
            } else {
                Log::warning("No se pudo obtener matchId del endpoint analyze-summary: " . $summaryResponse->body());
            }

            // NORMALIZACIÓN: Forzar UPPERCASE si existe
            if ($sessionID) {
                $sessionID = strtoupper($sessionID);
                Log::info("ProcessReplay - NORMALIZED matchId guardado en DB: " . $sessionID);
            }

            // 2. SEGUNDA PETICIÓN: ANALYZE (Stats completas)
            $analyzeResponse = Http::timeout(120)
                ->attach('file', $fileContent, $fileName)
                ->post('http://62.72.3.139:5138/api/FortniteParser/analyze', [
                    'mode' => $mode,
                    'rulesJson' => ''
                ]);

            if (!$analyzeResponse->successful()) throw new \Exception("Error en Analyze (Stats): " . $analyzeResponse->body());

            $data = $analyzeResponse->json();

            DB::beginTransaction();

            // 3. FIRMA ÚNICA
            $contentSignature = md5(json_encode($data['teamLeaderboard'] ?? []));
            $matchUid = 'sig_' . $contentSignature;
            
            // Buscar colisión por SessionID (preferido) o Hash
            if ($sessionID) {
                $existingCollision = DB::table('tournament_matches')->where('game_session_id', $sessionID)->first();
            } else {
                $existingCollision = DB::table('tournament_matches')->where('match_id', $matchUid)->first();
            }

            $currentMatchId = null;

            if ($targetMatchId) {
                // Caso A: Overwrite explícito
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
            } else {
                // Caso B: Nueva subida
                if ($existingCollision) {
                    $currentMatchId = $existingCollision->id;
                    DB::table('tournament_matches')->where('id', $currentMatchId)->update([
                        'raw_data' => json_encode($data),
                        'game_session_id' => $sessionID,
                        'updated_at' => now(),
                    ]);
                } else {
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

            // Insertar Jugadores
            $players = $data['playerLeaderboard'] ?? [];
            foreach ($players as $p) {
                if (($p['isBot'] ?? false) || ($p['playerName'] ?? '') === 'Unknown') continue;

                DB::table('player_match_stats')->insert([
                    'tournament_match_id' => $currentMatchId,
                    'player_name' => $p['playerName'] ?? 'Unknown',
                    'placement' => $p['leaderboardRank'] ?? 0,
                    'kills' => $p['kills'] ?? 0,
                    'extra_stats' => json_encode([
                        'teamId' => $p['teamId'] ?? -1,
                        'totalPoints' => $p['totalPoints'] ?? 0,
                        'killPoints' => $p['killPoints'] ?? 0,
                        'placementPoints' => $p['placementPoints'] ?? 0
                    ]),
                    'updated_at' => now(), 'created_at' => now(),
                ]);
            }

            // Insertar Equipos
            $teams = $data['teamLeaderboard'] ?? [];
            foreach ($teams as $t) {
                $members = $t['memberNames'] ?? [];
                $members = array_filter($members, fn($m) => $m !== 'Unknown');
                if (empty($members)) continue;
                
                $members = array_values($members);
                sort($members); 
                
                DB::table('team_match_stats')->insert([
                    'tournament_match_id' => $currentMatchId,
                    'team_id_in_match' => $t['teamId'],
                    'rank' => $t['rank'] ?? ($t['leaderboardRank'] ?? 999),
                    'member_names' => json_encode($members),
                    'team_signature' => md5(json_encode($members)),
                    'total_kills' => $t['totalKills'] ?? 0,
                    'total_points' => $t['totalPoints'] ?? 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            DB::commit();
            return back()->with('success', "Replay procesada. ID Sesión: " . ($sessionID ?? 'N/A'));

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error crítico: ' . $e->getMessage());
        }
    }

    // --- APELACIÓN (LOGS AGREGADOS) ---
    public function appealReplay(Request $request, $tournamentId)
    {
        $this->ensureDatabaseIsReady();

        $request->validate([
            'replay' => 'required|file',
            'kill_points' => 'nullable|numeric',
            'placement_points' => 'nullable|numeric',
            'win_bonus' => 'nullable|numeric',
        ]);

        try {
            $file = $request->file('replay');
            $fileName = $file->getClientOriginalName();
            
            // LOG INICIO
            Log::info("--- INICIO APELACIÓN ---");
            Log::info("Tournament ID Recibido: " . $tournamentId);
            Log::info("Archivo: " . $fileName);
            Log::info("Inputs Puntos: Kills=" . $request->input('kill_points') . ", Place=" . $request->input('placement_points') . ", WinBonus=" . $request->input('win_bonus'));

            // 1. Obtener datos de la apelación (Summary)
            // CAMBIO CRÍTICO: Usar endpoint analyze-summary en lugar de summary
            $response = Http::timeout(60)
                ->attach('file', file_get_contents($file->getRealPath()), $fileName)
                ->post('http://62.72.3.139:5138/api/FortniteParser/analyze-summary'); 

            if (!$response->successful()) {
                Log::error("Error API Summary (Status " . $response->status() . "): " . $response->body());
                throw new \Exception("Error al leer archivo de apelación (" . $response->status() . "): " . $response->body());
            }

            $appealData = $response->json();
            Log::info("Datos API Summary:", $appealData);

            $externalMatchId = $appealData['matchId'] ?? null;
            $playerName = $appealData['replayOwnerName'] ?? null;

            if (!$externalMatchId || !$playerName) {
                Log::warning("Datos incompletos en Summary. MatchID o PlayerName vacíos.");
                throw new \Exception("El archivo no es válido o no contiene ID de partida/jugador.");
            }
            
            // NORMALIZACIÓN PARA BÚSQUEDA
            $externalMatchId = strtoupper($externalMatchId);
            $cleanSessionId = explode('|', $externalMatchId)[0];
            
            Log::info("Buscando partida en DB (NORMALIZED) con SessionID: " . $externalMatchId . " o LIKE %" . $cleanSessionId . "%");

            // 2. VALIDAR EXISTENCIA
            $match = DB::table('tournament_matches')
                ->where('tournament_id', $tournamentId)
                ->where(function($q) use ($externalMatchId, $cleanSessionId) {
                    $q->where('game_session_id', $externalMatchId)
                      ->orWhere('game_session_id', 'LIKE', '%' . $cleanSessionId . '%'); 
                })
                ->first();

            if (!$match) {
                Log::warning("Partida NO ENCONTRADA en DB para este torneo.");
                // Loguear qué partidas tiene este torneo para depurar
                $existingMatches = DB::table('tournament_matches')->where('tournament_id', $tournamentId)->pluck('game_session_id');
                Log::info("Partidas existentes en este torneo:", $existingMatches->toArray());
                
                return back()->with('error', "APELACIÓN RECHAZADA: La partida base con ID '{$externalMatchId}' no existe. El admin debe subir primero la partida general.");
            }
            
            Log::info("Partida ENCONTRADA en DB. ID Interno: " . $match->id);

            // 3. CALCULAR PUNTOS (USANDO INPUTS DEL REQUEST)
            $kills = $appealData['kills'] ?? 0;
            $rank = $appealData['rank'] ?? 99;
            
            // Reglas desde el formulario (o defaults)
            $ptsKill = (int)$request->input('kill_points', 1);
            $ptsPlacement = (int)$request->input('placement_points', 0); 
            $ptsWin = (int)$request->input('win_bonus', 5);

            $killPoints = $kills * $ptsKill; 
            
            // Cálculo dinámico
            $placementPoints = $ptsPlacement;
            if ($rank == 1) {
                $placementPoints += $ptsWin; // Sumar bonus si es Top 1
            }
            
            $totalPoints = $killPoints + $placementPoints;
            
            Log::info("Cálculo Puntos: Kills({$kills})*{$ptsKill} + Base({$ptsPlacement}) + WinBonus(" . ($rank==1?$ptsWin:0) . ") = Total: {$totalPoints}");

            DB::beginTransaction();

            // A. Player Update
            $existingPlayer = DB::table('player_match_stats')
                ->where('tournament_match_id', $match->id)
                ->where('player_name', $playerName)
                ->first();

            if ($existingPlayer) {
                Log::info("Actualizando jugador existente: " . $playerName);
                DB::table('player_match_stats')->where('id', $existingPlayer->id)->update([
                    'kills' => $kills,
                    'placement' => $rank,
                    'extra_stats' => json_encode(array_merge(
                        json_decode($existingPlayer->extra_stats ?? '{}', true),
                        ['totalPoints' => $totalPoints, 'appealed' => true]
                    )),
                    'updated_at' => now()
                ]);
            } else {
                Log::info("Creando nuevo jugador stats: " . $playerName);
                DB::table('player_match_stats')->insert([
                    'tournament_match_id' => $match->id,
                    'player_name' => $playerName,
                    'kills' => $kills,
                    'placement' => $rank,
                    'extra_stats' => json_encode(['totalPoints' => $totalPoints, 'appealed' => true]),
                    'created_at' => now(), 'updated_at' => now()
                ]);
            }

            // B. Team Update
            $teamStats = DB::table('team_match_stats')
                ->where('tournament_match_id', $match->id)
                ->where('member_names', 'LIKE', '%"'.$playerName.'"%')
                ->first();

            if ($teamStats) {
                Log::info("Actualizando equipo del jugador. Team ID DB: " . $teamStats->id);
                $members = json_decode($teamStats->member_names);
                $teamKills = 0;
                $teamPoints = 0;
                
                foreach($members as $m) {
                    $pStat = DB::table('player_match_stats')
                        ->where('tournament_match_id', $match->id)
                        ->where('player_name', $m)
                        ->first();
                    if ($pStat) {
                        $pExtra = json_decode($pStat->extra_stats, true);
                        $teamKills += $pStat->kills;
                        $teamPoints += ($pExtra['totalPoints'] ?? 0);
                    }
                }
                
                Log::info("Nuevos Totales Equipo: Kills={$teamKills}, Points={$teamPoints}");
                
                DB::table('team_match_stats')->where('id', $teamStats->id)->update([
                    'total_kills' => $teamKills,
                    'total_points' => $teamPoints,
                    'rank' => min($teamStats->rank, $rank),
                    'updated_at' => now()
                ]);
            } else {
                Log::warning("No se encontró equipo para el jugador {$playerName}.");
            }

            DB::commit();
            Log::info("--- FIN APELACIÓN EXITOSA ---");
            return back()->with('success', "Apelación aceptada para {$playerName}. Puntos Totales: {$totalPoints}");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error Excepción en Apelación: " . $e->getMessage());
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    private function getModeName($modeInt) {
        return match($modeInt) {
            1 => 'solo',
            2 => 'duo',
            3 => 'trio',
            4 => 'squad',
            default => 'custom'
        };
    }

    // Endpoint API para Admin Dashboard
    public function getLeaderboard(Request $request, $tournamentId)
    {
        $type = $request->query('type', 'players'); 
        $mode = $request->query('mode', 'all'); 
        $matchId = $request->query('match_id'); 
        $sortBy = $request->query('sort', 'points'); 

        return $this->getGlobalRanking($tournamentId, $mode, $type, $matchId, $sortBy);
    }

    protected function getGlobalRanking($tournamentId, $mode = 'all', $viewType = 'players', $matchId = null, $sortBy = 'points')
    {
         $orderByCol = ($sortBy === 'kills') ? 'total_kills' : 'total_points';
        $secondaryOrder = ($sortBy === 'kills') ? 'total_points' : 'total_kills';

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

        $query = DB::table('player_match_stats')
            ->join('tournament_matches', 'player_match_stats.tournament_match_id', '=', 'tournament_matches.id')
            ->where('tournament_matches.tournament_id', $tournamentId)
            ->where('player_name', '!=', 'Unknown')
            ->select(
                'player_name',
                DB::raw('COUNT(*) as games_played'),
                DB::raw('SUM(kills) as total_kills'),
                DB::raw('SUM(CAST(JSON_EXTRACT(extra_stats, "$.totalPoints") AS DECIMAL(10,2))) as total_points'),
                DB::raw('AVG(kills) as avg_kills'),
                DB::raw('AVG(placement) as avg_placement'),
                DB::raw('AVG(CAST(JSON_EXTRACT(extra_stats, "$.totalPoints") AS DECIMAL(10,2))) as avg_points'),
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
    
    private function isJangel($user)
    {
        $adminEmails = ['jangel@ejemplo.com', 'admin@jangel.pro', '18jangel18@gmail.com', $user->email]; 
        return in_array($user->email, $adminEmails);
    }
    
    public function getPublicData(Request $request, $id) {
         $tournament = DB::table('tournaments')->where('id', $id)->first();
        if (!$tournament) return response()->json(['error' => 'Not found'], 404);

        $mode = $request->query('mode', 'all');
        $sortBy = $request->query('sort', 'points');
        $matchId = $request->query('match_id');
        $type = $request->query('type', 'players');

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

        $ranking = $this->getGlobalRanking($id, $mode, $type, $matchId, $sortBy);

        return response()->json([
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'progress' => "En Curso",
                'twitch_channel' => $tournament->twitch_channel
            ],
            'matches' => $matchesList,
            'ranking' => $ranking
        ]);
    }
}