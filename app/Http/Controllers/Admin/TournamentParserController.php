8
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
                $table->longText('rules')->nullable(); // Reglas
                $table->longText('prizes')->nullable(); // Premios
                $table->json('scoring_format')->nullable(); // Configuración de puntos JSON
                $table->string('table_name')->nullable();
                $table->timestamps();
            });
        } else {
             Schema::table('tournaments', function (Blueprint $table) {
                if (!Schema::hasColumn('tournaments', 'twitch_channel')) $table->string('twitch_channel')->nullable();
                if (!Schema::hasColumn('tournaments', 'is_private')) $table->boolean('is_private')->default(false);
                if (!Schema::hasColumn('tournaments', 'access_code')) $table->string('access_code')->nullable();
                if (!Schema::hasColumn('tournaments', 'rules')) $table->longText('rules')->nullable();
                if (!Schema::hasColumn('tournaments', 'prizes')) $table->longText('prizes')->nullable();
                if (!Schema::hasColumn('tournaments', 'scoring_format')) $table->json('scoring_format')->nullable();
                if (!Schema::hasColumn('tournaments', 'table_name')) $table->string('table_name')->nullable();
                
                // Nuevas Columnas (Fase 2)
                if (!Schema::hasColumn('tournaments', 'user_id')) $table->foreignId('user_id')->nullable()->after('id'); // Owner
                if (!Schema::hasColumn('tournaments', 'entry_fee')) $table->integer('entry_fee')->default(0); // Cents
                if (!Schema::hasColumn('tournaments', 'currency')) $table->string('currency')->default('mxn');
             });
        }

        // 1.5. Tabla de Registros (Pagos de Entrada)
        if (!Schema::hasTable('tournament_registrations')) {
            Schema::create('tournament_registrations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->boolean('has_paid')->default(false);
                $table->string('payment_intent_id')->nullable();
                $table->integer('amount_paid')->default(0);
                $table->string('currency')->default('mxn');
                $table->timestamps();

                $table->unique(['user_id', 'tournament_id']);
            });
        }

        // Actualización de Usuarios (Roles y Stripe Connect)
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'role')) $table->string('role')->default('player'); // player, organizer, admin
                if (!Schema::hasColumn('users', 'stripe_connect_id')) $table->string('stripe_connect_id')->nullable(); // Para recibir pagos
            });
        }

        // 2. Tabla de Partidas (Matches)
        if (!Schema::hasTable('tournament_matches')) {
            Schema::create('tournament_matches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tournament_id');
                $table->string('match_id')->unique();
                $table->string('game_session_id')->nullable()->index();
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
                $table->json('extra_stats')->nullable(); // Aquí guardaremos manual_points
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

        // 5. Tabla de Logs de Ajustes Manuales (Audit)
        if (!Schema::hasTable('tournament_score_logs')) {
            Schema::create('tournament_score_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tournament_id');
                $table->unsignedBigInteger('match_id')->nullable();
                $table->string('player_name');
                $table->integer('points_change'); // +10, -5, etc.
                $table->text('reason');
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->timestamps();
            });
        }

        // 6. Tabla de Premios (Prizes)
        if (!Schema::hasTable('tournament_prizes')) {
            Schema::create('tournament_prizes', function (Blueprint $table) {
                $table->id();
                 // Constrained to tournaments table which is defined above
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Winner
                $table->string('title'); // e.g. "1st Place"
                $table->unsignedInteger('amount'); // In cents
                $table->string('currency')->default('usd');
                $table->enum('status', ['pending', 'ready', 'paid', 'failed'])->default('pending');
                $table->string('stripe_transfer_id')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                // Index for faster queries
                $table->index(['tournament_id', 'status']);
            });
        }
    }

    public function index()
    {
        $this->ensureDatabaseIsReady();
        $tournaments = DB::table('tournaments')->orderBy('created_at', 'desc')->get();

        foreach($tournaments as $tn) {
            // Decodificar JSON para que el frontend lo lea como objeto
            $tn->scoring_format = json_decode($tn->scoring_format ?? '{}');
            
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
            'prizes' => $request->prizes,
            'scoring_format' => $request->scoring_format ? json_encode($request->scoring_format) : null,
            'created_at' => now(), 
            'updated_at' => now()
        ];

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

        DB::table('tournaments')->insert($data);
        return back()->with('success', 'Torneo creado.');
    }
    
    public function update(Request $request, $id) {
        $data = [
            'name' => $request->name,
            'twitch_channel' => $request->twitch_channel,
            'rules' => $request->rules,
            'prizes' => $request->prizes,
            'scoring_format' => $request->scoring_format ? json_encode($request->scoring_format) : null,
            'updated_at' => now(),
        ];

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

    public function deleteMatch($matchId) {
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
        
        // 1. Configuración Personalizada (JSON)
        if (!empty($format) && isset($format->placement) && is_array($format->placement)) {
            // Puntos por Kills
            $killPts = isset($format->kill_points) ? (float)$format->kill_points : 1;
            $points += ($kills * $killPts);

            // Puntos por Posición (Ranges)
            foreach ($format->placement as $range) {
                // Estructura esperada: { "from": 1, "to": 1, "points": 10 }
                $from = (int)($range->from ?? 0);
                $to = (int)($range->to ?? 0);
                $pts = (float)($range->points ?? 0);

                // LÓGICA ACUMULATIVA POR "ESCALÓN" (Step)
                // Se otorgan puntos por CADA posición superada o igualada dentro del rango.
                // Ejemplo: Top 5 a 2 (3 pts).
                // Rank 6: 0 pts.
                // Rank 5: Suma 3. 
                // Rank 4: Suma 6 (3 por el #5 + 3 por el #4).
                // Rank 1: Suma 12 (3 por el #5, #4, #3, #2).
                
                // Normalizar: start = peor ranking (mayor numero), end = mejor ranking (menor numero)
                $start = max($from, $to); 
                $end = min($from, $to);

                if ($rank <= $start) {
                    // Si el jugador está dentro o por encima del rango (rank es numéricamente menor o igual a start)
                    
                    // El rango efectivo termina en max($rank, $end) porque si el jugador es Top 1
                    // y el rango termina en 2, solo acumula hasta el 2 (no suma más allá del límite del rango).
                    // Pero si el jugador es Top 4, acumula desde start(5) hasta 4.
                    
                    $effectiveRank = max($rank, $end);
                    $steps = ($start - $effectiveRank) + 1;
                    if ($steps > 0) {
                        $points += ($steps * $pts);
                    }
                }
            }
        } 
        // 2. Modo Automático (Compensación Default si no hay config)
        else {
            $points += $kills; // 1 punto por kill base

            if ($rank == 1) $points += 25;
            elseif ($rank <= 5) $points += 15;
            elseif ($rank <= 15) $points += 10;
            elseif ($rank <= 25) $points += 5;
        }

        return $points;
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

            if ($sessionID) $sessionID = strtoupper($sessionID);

            // 2. ANALYZE (Stats completas)
            $analyzeResponse = Http::timeout(120)
                ->attach('file', $fileContent, $fileName)
                ->post('http://62.72.3.139:5138/api/FortniteParser/analyze', [
                    'mode' => $mode,
                    'rulesJson' => '' // Enviamos vacío, calculamos en PHP
                ]);

            if (!$analyzeResponse->successful()) throw new \Exception("Error en Analyze: " . $analyzeResponse->body());

            $data = $analyzeResponse->json();

            DB::beginTransaction();

            $contentSignature = md5(json_encode($data['teamLeaderboard'] ?? []));
            $matchUid = 'sig_' . $contentSignature;
            
            if ($sessionID) {
                $existingCollision = DB::table('tournament_matches')->where('game_session_id', $sessionID)->first();
            } else {
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
            } else {
                // Nuevo
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

            // Insertar Jugadores (CALCULANDO PUNTOS INTERNAMENTE)
            $players = $data['playerLeaderboard'] ?? [];
            foreach ($players as $p) {
                if (($p['isBot'] ?? false) || ($p['playerName'] ?? '') === 'Unknown') continue;

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
                if (empty($members)) continue;
                
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

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error crítico: ' . $e->getMessage());
        }
    }

    // --- APELACIÓN AUTOMÁTICA ---
    public function appealReplay(Request $request, $tournamentId)
    {
        $this->ensureDatabaseIsReady();
        $request->validate(['replay' => 'required|file']);

        try {
            $tournament = DB::table('tournaments')->where('id', $tournamentId)->first();
            if (!$tournament) throw new \Exception("Torneo no encontrado");
            
            // Obtener reglas del torneo para calcular
            $scoringFormat = $tournament->scoring_format ? json_decode($tournament->scoring_format) : null;

            $file = $request->file('replay');
            $fileName = $file->getClientOriginalName();
            
            Log::info("--- INICIO APELACIÓN AUTOMÁTICA --- Tournament: $tournamentId");

            // 1. Analyze Summary
            $response = Http::timeout(60)
                ->attach('file', file_get_contents($file->getRealPath()), $fileName)
                ->post('http://62.72.3.139:5138/api/FortniteParser/analyze-summary'); 

            if (!$response->successful()) throw new \Exception("Error API Summary: " . $response->body());

            $appealData = $response->json();
            $externalMatchId = strtoupper($appealData['matchId'] ?? '');
            $playerName = $appealData['replayOwnerName'] ?? null;
            $cleanSessionId = explode('|', $externalMatchId)[0];

            if (!$externalMatchId || !$playerName) throw new \Exception("Replay inválido o corrupto.");

            // 2. Buscar Partida
            $match = DB::table('tournament_matches')
                ->where('tournament_id', $tournamentId)
                ->where(function($q) use ($externalMatchId, $cleanSessionId) {
                    $q->where('game_session_id', $externalMatchId)
                      ->orWhere('game_session_id', 'LIKE', '%' . $cleanSessionId . '%'); 
                })
                ->first();

            if (!$match) return back()->with('error', "APELACIÓN RECHAZADA: La partida base no ha sido subida por el administrador.");

            // 3. DATOS CRUDOS DEL REPLAY DE USUARIO
            $kills = $appealData['kills'] ?? 0;
            $rank = $appealData['rank'] ?? 99;

            // 4. CÁLCULO AUTOMÁTICO (Usando reglas del torneo + Fix min/max)
            $calculatedPoints = $this->calculateScore($rank, $kills, $scoringFormat, $match->game_mode);

            Log::info("Apelación $playerName: Rank $rank, Kills $kills -> Puntos Calc: $calculatedPoints");

            DB::beginTransaction();

            // A. Update/Insert Player
            $existingPlayer = DB::table('player_match_stats')
                ->where('tournament_match_id', $match->id)
                ->where('player_name', $playerName)
                ->first();
            
            // Mantener ajuste manual si existe
            $extraStats = $existingPlayer ? json_decode($existingPlayer->extra_stats, true) : [];
            $manualPts = $extraStats['manual_points'] ?? 0;
            $finalPoints = $calculatedPoints + $manualPts;

            $extraStats['totalPoints'] = $finalPoints;
            $extraStats['appealed'] = true;
            // Guardamos tambien los puntos base sin manual para referencia
            $extraStats['base_points'] = $calculatedPoints; 

            if ($existingPlayer) {
                DB::table('player_match_stats')->where('id', $existingPlayer->id)->update([
                    'kills' => $kills,
                    'placement' => $rank,
                    'extra_stats' => json_encode($extraStats),
                    'updated_at' => now()
                ]);
            } else {
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
            // Buscar equipo donde esté este jugador
            $teamStats = DB::table('team_match_stats')
                ->where('tournament_match_id', $match->id)
                ->where('member_names', 'LIKE', '%"'.$playerName.'"%')
                ->first();

            if ($teamStats) {
                $members = json_decode($teamStats->member_names);
                $teamKills = 0;
                $teamPoints = 0;
                
                // Recalcular todo el equipo
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
                
                // Actualizar stats del equipo
                $newRank = min($teamStats->rank, $rank);
                
                DB::table('team_match_stats')->where('id', $teamStats->id)->update([
                    'total_kills' => $teamKills,
                    'total_points' => $teamPoints,
                    'rank' => $newRank,
                    'updated_at' => now()
                ]);
            }

            DB::commit();
            return back()->with('success', "Apelación aceptada. Puntos recalculados automáticamente: {$finalPoints}");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error Apelación: " . $e->getMessage());
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // --- AJUSTE MANUAL DE PUNTOS (PENALIZACIONES/BONUS) ---
    public function adjustPlayerScore(Request $request, $tournamentId)
    {
        $this->ensureDatabaseIsReady();
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

            if (!$pStat) throw new \Exception("Jugador no encontrado en esta partida.");

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
                ->where('member_names', 'LIKE', '%"'.$player.'"%')
                ->first();
            
            if ($teamStats) {
                $members = json_decode($teamStats->member_names);
                $teamTotalPoints = 0;
                foreach($members as $m) {
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

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    private function getModeName($modeInt) {
        return match($modeInt) {
            1 => 'solo', 2 => 'duo', 3 => 'trio', 4 => 'squad', default => 'custom'
        };
    }

    public function getLeaderboard(Request $request, $tournamentId)
    {
        $type = $request->query('type', 'players'); 
        $mode = $request->query('mode', 'all'); 
        $matchId = $request->query('match_id'); 
        $sortBy = $request->query('sort', 'points'); 

        return $this->getGlobalRanking($tournamentId, $mode, $type, $matchId, $sortBy);
    }

    // --- CORRECCIÓN AQUÍ: Usamos exactamente la misma lógica que en PublicTournamentController ---
    protected function getGlobalRanking($tournamentId, $mode = 'all', $viewType = 'players', $matchId = null, $sortBy = 'points')
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
        // Usamos la misma lógica precisa del público para asegurar consistencia
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
    
    private function isJangel($user)
    {
        $adminEmails = ['jangel@ejemplo.com', 'admin@jangel.pro', '18jangel18@gmail.com', $user->email]; 
        return in_array($user->email, $adminEmails);
    }
    
    // Método getPublicData duplicado para soporte, pero generalmente se usa el PublicController
    public function getPublicData(Request $request, $id) {
         // ... (implementación similar si se usa desde aquí)
         // Por ahora devolvemos vacío ya que se usa PublicTournamentController
         return response()->json([]);
    }

    // --- GESTIÓN DE USUARIOS (ADMIN JANGEL) ---
    public function searchUsers(Request $request)
    {
        // Solo Jangel puede buscar
        if (!$this->isJangel(auth()->user())) abort(403);

        $query = $request->query('query');
        if (!$query) return response()->json([]);

        $users = DB::table('users')
            ->where('email', 'LIKE', "%{$query}%")
            ->orWhere('name', 'LIKE', "%{$query}%")
            ->select('id', 'name', 'email', 'role', 'stripe_connect_id')
            ->limit(10)
            ->get();
            
        return response()->json($users);
    }

    public function assignRole(Request $request)
    {
        // Solo Jangel puede asignar
        if (!$this->isJangel(auth()->user())) abort(403);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:player,organizer,admin'
        ]);

        DB::table('users')->where('id', $request->user_id)->update([
            'role' => $request->role,
            'updated_at' => now()
        ]);

        return back()->with('success', "Rol actualizado correctamente.");
    }
}