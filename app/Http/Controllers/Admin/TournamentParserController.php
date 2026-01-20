<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str; // Importante para generar slugs
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
                $table->string('twitch_channel')->nullable(); // Canal de Twitch opcional
                $table->timestamps();
            });
        } else {
             // Parche caliente para tabla existente: Agrega twitch_channel si falta
             if (!Schema::hasColumn('tournaments', 'twitch_channel')) {
                Schema::table('tournaments', function (Blueprint $table) {
                    $table->string('twitch_channel')->nullable();
                });
             }
        }

        // 2. Tabla de Partidas (Matches)
        if (!Schema::hasTable('tournament_matches')) {
            Schema::create('tournament_matches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tournament_id');
                $table->string('match_id')->unique(); // Aquí guardaremos la firma única
                $table->string('game_mode')->default('solo');
                $table->string('map_name')->nullable();
                $table->string('custom_code')->nullable(); 
                $table->json('raw_data')->nullable(); 
                $table->timestamps();
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

        // Parches en caliente
        if (Schema::hasTable('tournament_matches')) {
            Schema::table('tournament_matches', function (Blueprint $table) {
                if (!Schema::hasColumn('tournament_matches', 'game_mode')) {
                    $table->string('game_mode')->default('solo');
                }
                if (!Schema::hasColumn('tournament_matches', 'custom_code')) {
                    $table->string('custom_code')->nullable();
                }
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
                ->select('id', 'match_id', 'game_mode', 'custom_code', 'raw_data', 'created_at') 
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

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'twitch_channel' => 'nullable|string|max:100'
        ]);
        
        $data = [
            'name' => $request->name,
            'twitch_channel' => $request->twitch_channel,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('tournaments', 'table_name')) {
            $data['table_name'] = Str::slug($request->name) . '_' . time(); 
        }
        if (Schema::hasColumn('tournaments', 'slug')) {
            $data['slug'] = Str::slug($request->name);
        }

        DB::table('tournaments')->insert($data);

        return back()->with('success', 'Torneo creado correctamente.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'twitch_channel' => 'nullable|string|max:100'
        ]);

        $data = [
            'name' => $request->name,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('tournaments', 'twitch_channel')) {
            $data['twitch_channel'] = $request->twitch_channel;
        }

        if (Schema::hasColumn('tournaments', 'slug')) {
            $data['slug'] = Str::slug($request->name);
        }

        DB::table('tournaments')->where('id', $id)->update($data);

        return back()->with('success', 'Torneo actualizado.');
    }

    public function destroy($id)
    {
        $matchCount = DB::table('tournament_matches')->where('tournament_id', $id)->count();

        if ($matchCount > 0) {
            return back()->with('error', 'No se puede eliminar un torneo que tiene partidas registradas. Elimina las partidas primero.');
        }

        DB::table('tournaments')->where('id', $id)->delete();
        return back()->with('success', 'Torneo eliminado.');
    }

    public function storeScheduledMatch(Request $request, $tournamentId)
    {
        $request->validate([
            'custom_code' => 'required|string|max:50',
            'game_mode' => 'required|integer'
        ]);

        $this->ensureDatabaseIsReady();

        DB::table('tournament_matches')->insert([
            'tournament_id' => $tournamentId,
            'match_id' => 'pending_' . uniqid(),
            'game_mode' => $this->getModeName((int)$request->game_mode),
            'custom_code' => $request->custom_code,
            'raw_data' => null, 
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Partida programada creada. Comparte el código con los jugadores.');
    }

    public function updateMatch(Request $request, $id)
    {
        $request->validate([
            'custom_code' => 'required|string|max:50'
        ]);

        DB::table('tournament_matches')->where('id', $id)->update([
            'custom_code' => $request->custom_code,
            'updated_at' => now()
        ]);

        return back()->with('success', 'Código de partida actualizado.');
    }

    public function deleteMatch($matchId)
    {
        try {
            DB::beginTransaction();
            DB::table('player_match_stats')->where('tournament_match_id', $matchId)->delete();
            DB::table('team_match_stats')->where('tournament_match_id', $matchId)->delete();
            DB::table('tournament_matches')->where('id', $matchId)->delete();
            DB::commit();
            
            return back()->with('success', 'Partida eliminada y puntos recalculados.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al eliminar partida: ' . $e->getMessage());
        }
    }

    // --- LÓGICA DE PROCESAMIENTO INTELIGENTE ---
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

            // API Externa
            $response = Http::timeout(120)
                ->attach(
                    'file', 
                    file_get_contents($file->getRealPath()), 
                    $file->getClientOriginalName()
                )
                ->post('http://62.72.3.139:5138/api/FortniteParser/analyze', [
                    'mode' => $mode,
                    'rulesJson' => ''
                ]);

            if (!$response->successful()) {
                throw new \Exception("Error en API externa: " . $response->body());
            }

            $data = $response->json();

            DB::beginTransaction();

            // 1. GENERAR FIRMA ÚNICA DEL CONTENIDO
            // Como el 'fileName' es temporal (tmpyuqkYO.tmp), no sirve para identificar duplicados.
            // Usamos el contenido del 'teamLeaderboard' para crear un hash único de esta partida.
            $contentSignature = md5(json_encode($data['teamLeaderboard'] ?? []));
            $matchUid = 'sig_' . $contentSignature;
            
            $currentMatchId = null;

            // 2. DETECCIÓN DE DUPLICADOS O ACTUALIZACIÓN
            if ($targetMatchId) {
                // Caso A: Overwrite explícito (usuario seleccionó una partida para subir)
                $currentMatchId = $targetMatchId;
                
                DB::table('tournament_matches')->where('id', $currentMatchId)->update([
                    'match_id' => $matchUid, // Actualizamos la firma
                    'raw_data' => json_encode($data),
                    'updated_at' => now(),
                ]);
            } else {
                // Caso B: Subida nueva -> BUSCAR SI YA EXISTE POR FIRMA
                $duplicateMatch = DB::table('tournament_matches')
                    ->where('tournament_id', $id)
                    ->where('match_id', $matchUid) // Buscamos por la firma de contenido
                    ->first();

                if ($duplicateMatch) {
                    // ¡ES DUPLICADA! Usamos la existente para no sumar puntos dobles
                    $currentMatchId = $duplicateMatch->id;
                    DB::table('tournament_matches')->where('id', $currentMatchId)->update([
                        'raw_data' => json_encode($data),
                        'updated_at' => now(),
                    ]);
                } else {
                    // ES NUEVA DE VERDAD
                    $currentMatchId = DB::table('tournament_matches')->insertGetId([
                        'tournament_id' => $id,
                        'match_id' => $matchUid,
                        'game_mode' => $this->getModeName($mode),
                        'map_name' => 'Island',
                        'raw_data' => json_encode($data),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]);
                }
            }

            // 3. LIMPIEZA DE DATOS VIEJOS (Crucial para no duplicar puntos dentro de la misma partida)
            DB::table('player_match_stats')->where('tournament_match_id', $currentMatchId)->delete();
            DB::table('team_match_stats')->where('tournament_match_id', $currentMatchId)->delete();

            // 4. INSERTAR DATOS (Solo la "primera parte" si así lo prefieres, pero esto es estándar)
            // Usamos playerLeaderboard para estadísticas individuales detalladas
            $players = $data['playerLeaderboard'] ?? [];
            foreach ($players as $p) {
                if (($p['isBot'] ?? false) || ($p['playerName'] ?? '') === 'Unknown') {
                    continue;
                }

                DB::table('player_match_stats')->insert([
                    'tournament_match_id' => $currentMatchId,
                    'player_name' => $p['playerName'] ?? 'Unknown',
                    'placement' => $p['leaderboardRank'] ?? 0,
                    'kills' => $p['kills'] ?? 0,
                    'damage_done' => 0,
                    'damage_taken' => 0,
                    'extra_stats' => json_encode([
                        'teamId' => $p['teamId'] ?? -1,
                        'totalPoints' => $p['totalPoints'] ?? 0, // Puntos totales ya calculados por el parser
                        'killPoints' => $p['killPoints'] ?? 0,
                        'placementPoints' => $p['placementPoints'] ?? 0,
                        'knocks' => $p['knocks'] ?? 0,
                    ]),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }

            // Usamos teamLeaderboard para estadísticas de equipo
            $teams = $data['teamLeaderboard'] ?? [];
            foreach ($teams as $t) {
                $members = $t['memberNames'] ?? [];
                
                $isValidTeam = false;
                foreach($members as $m) {
                    if ($m !== 'Unknown') $isValidTeam = true;
                }
                if (!$isValidTeam) continue;

                sort($members); 
                $signature = md5(json_encode($members));

                DB::table('team_match_stats')->insert([
                    'tournament_match_id' => $currentMatchId,
                    'team_id_in_match' => $t['teamId'],
                    'rank' => $t['rank'] ?? ($t['leaderboardRank'] ?? 999),
                    'member_names' => json_encode($members),
                    'team_signature' => $signature,
                    'total_kills' => $t['totalKills'] ?? 0,
                    'total_points' => $t['totalPoints'] ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            
            // Mensaje informativo dependiendo de si fue actualización o creación
            if (isset($duplicateMatch)) {
                return back()->with('success', "Replay ACTUALIZADA (Se detectó duplicado por contenido).");
            }
            return back()->with('success', "Replay procesada correctamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error procesando replay: " . $e->getMessage());
            return back()->with('error', 'Error crítico: ' . $e->getMessage());
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

    public function getLeaderboard(Request $request, $tournamentId)
    {
        $type = $request->query('type', 'players'); // 'players' | 'teams'
        $mode = $request->query('mode', 'all'); 
        $matchId = $request->query('match_id'); 
        $sortBy = $request->query('sort', 'points'); // 'points' | 'kills'

        return $this->getGlobalRanking($tournamentId, $mode, $type, $matchId, $sortBy);
    }

    // Endpoint para Widget Admin
    public function getWidgetStats(Request $request, $tournamentId)
    {
        $stats = $this->getGlobalRanking($tournamentId, 'all', 'players', null, 'points');
        
        return response()->json([
            'tournament_name' => DB::table('tournaments')->where('id', $tournamentId)->value('name'),
            'top_players' => $stats->take(10), // Solo top 10
        ]);
    }

    // Endpoint público para ver partidas y códigos en Admin
    public function getPublicMatches($tournamentId)
    {
        $this->ensureDatabaseIsReady();
        
        $matches = DB::table('tournament_matches')
            ->where('tournament_id', $tournamentId)
            ->orderBy('created_at', 'desc')
            ->select('id', 'game_mode', 'custom_code', 'raw_data', 'created_at')
            ->get()
            ->map(function($m) {
                return [
                    'id' => $m->id,
                    'mode' => $m->game_mode,
                    'code' => $m->custom_code ?? 'Sin código',
                    'status' => is_null($m->raw_data) ? 'Pendiente (En Curso)' : 'Finalizada',
                    'date' => $m->created_at
                ];
            });

        return response()->json($matches);
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

        // Jugadores Individuales
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
                DB::raw('SUM(CAST(JSON_EXTRACT(extra_stats, "$.knocks") AS DECIMAL(10,2))) as total_knocks'),
                // Promedios
                DB::raw('AVG(kills) as avg_kills'),
                DB::raw('AVG(placement) as avg_placement'),
                DB::raw('AVG(CAST(JSON_EXTRACT(extra_stats, "$.totalPoints") AS DECIMAL(10,2))) as avg_points'),
                DB::raw('AVG(CAST(JSON_EXTRACT(extra_stats, "$.killPoints") AS DECIMAL(10,2))) as avg_kill_points'),
                DB::raw('AVG(CAST(JSON_EXTRACT(extra_stats, "$.placementPoints") AS DECIMAL(10,2))) as avg_placement_points'),
                DB::raw('AVG(CAST(JSON_EXTRACT(extra_stats, "$.knocks") AS DECIMAL(10,2))) as avg_knocks'),
                
                DB::raw('SUM(damage_done) as total_damage'),
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