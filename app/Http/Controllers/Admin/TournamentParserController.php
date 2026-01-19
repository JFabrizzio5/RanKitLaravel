<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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
                $table->timestamps();
            });
        }

        // 2. Tabla de Partidas (Matches)
        if (!Schema::hasTable('tournament_matches')) {
            Schema::create('tournament_matches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tournament_id');
                $table->string('match_id')->unique();
                $table->string('game_mode')->default('solo');
                $table->string('map_name')->nullable();
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
        Schema::table('tournament_matches', function (Blueprint $table) {
            if (!Schema::hasColumn('tournament_matches', 'game_mode')) {
                $table->string('game_mode')->default('solo')->after('match_id');
            }
        });
    }

    public function index()
    {
        $this->ensureDatabaseIsReady();
        
        $tournaments = DB::table('tournaments')->orderBy('created_at', 'desc')->get();

        foreach($tournaments as $tn) {
            $tn->matches = DB::table('tournament_matches')
                ->where('tournament_id', $tn->id)
                ->orderBy('created_at', 'desc')
                ->select('id', 'match_id', 'game_mode', 'created_at')
                ->get();
        }

        return Inertia::render('Admin/JangelDashboard', [
            'tournaments' => $tournaments
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        
        DB::table('tournaments')->insert([
            'name' => $request->name,
            'slug' => \Illuminate\Support\Str::slug($request->name),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Torneo creado correctamente.');
    }

    public function show($id)
    {
        $this->ensureDatabaseIsReady();
        $tournament = DB::table('tournaments')->where('id', $id)->first();

        if (!$tournament) {
            return redirect()->route('jangel.indexdos')->with('error', 'Torneo no encontrado.');
        }

        return Inertia::render('Admin/TournamentDetail', [
            'tournament' => $tournament,
            // Por defecto vista global de jugadores solo
            'rankingsSolo' => $this->getGlobalRanking($id, 'solo', 'players'),
            'recentMatches' => DB::table('tournament_matches')
                ->where('tournament_id', $id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
        ]);
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

    public function processReplay(Request $request, $id)
    {
        $this->ensureDatabaseIsReady();
        
        $request->validate([
            'replay' => 'required|file',
            'mode' => 'required|integer' // 1=Solo, 2=Duo, 3=Trio, 4=Squad
        ]);

        try {
            $file = $request->file('replay');
            $mode = (int)$request->input('mode');

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

            $matchUid = $data['fileName'] ?? uniqid('match_');

            // Insertar Partida
            $matchId = DB::table('tournament_matches')->insertGetId([
                'tournament_id' => $id,
                'match_id' => $matchUid . '_' . time(),
                'game_mode' => $this->getModeName($mode),
                'map_name' => 'Island',
                'raw_data' => json_encode($data),
                'updated_at' => now(),
                'created_at' => now(),
            ]);

            // --- PROCESAR JUGADORES ---
            $players = $data['playerLeaderboard'] ?? [];
            foreach ($players as $p) {
                // FILTRO DE BOTS: Si es bot o se llama "Unknown", lo saltamos
                if (($p['isBot'] ?? false) || ($p['playerName'] ?? '') === 'Unknown') {
                    continue;
                }

                DB::table('player_match_stats')->insert([
                    'tournament_match_id' => $matchId,
                    'player_name' => $p['playerName'] ?? 'Unknown',
                    'placement' => $p['leaderboardRank'] ?? 0,
                    'kills' => $p['kills'] ?? 0,
                    'damage_done' => 0,
                    'damage_taken' => 0,
                    'extra_stats' => json_encode([
                        'teamId' => $p['teamId'] ?? -1,
                        'totalPoints' => $p['totalPoints'] ?? 0,
                    ]),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }

            // --- PROCESAR EQUIPOS ---
            $teams = $data['teamLeaderboard'] ?? [];
            foreach ($teams as $t) {
                $members = $t['memberNames'] ?? [];
                
                // FILTRO DE BOTS EN EQUIPOS: Si el equipo está compuesto solo por Unknowns, saltar
                // O si contiene "Unknown", puedes decidir saltarlo. Aquí saltamos si TODOS son Unknown.
                $isValidTeam = false;
                foreach($members as $m) {
                    if ($m !== 'Unknown') $isValidTeam = true;
                }
                if (!$isValidTeam) continue;

                sort($members); 
                $signature = md5(json_encode($members));

                DB::table('team_match_stats')->insert([
                    'tournament_match_id' => $matchId,
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
            return back()->with('success', "Partida ({$this->getModeName($mode)}) procesada correctamente.");

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

    // API Endpoint mejorado para aceptar filtros
    public function getLeaderboard(Request $request, $tournamentId)
    {
        $type = $request->query('type', 'players'); // 'players' | 'teams'
        $mode = $request->query('mode', 'all'); // 'all', 'solo', 'duo', 'trio', 'squad'
        $matchId = $request->query('match_id'); // null para global, ID para partida específica

        return $this->getGlobalRanking($tournamentId, $mode, $type, $matchId);
    }

    protected function getGlobalRanking($tournamentId, $mode = 'all', $viewType = 'players', $matchId = null)
    {
        // 1. Ranking de EQUIPOS
        if ($viewType === 'teams') {
            $query = DB::table('team_match_stats')
                ->join('tournament_matches', 'team_match_stats.tournament_match_id', '=', 'tournament_matches.id')
                ->where('tournament_matches.tournament_id', $tournamentId)
                ->select(
                    'team_signature',
                    DB::raw('MIN(member_names) as members_json'), 
                    DB::raw('COUNT(*) as games_played'),
                    DB::raw('SUM(total_kills) as total_kills'),
                    DB::raw('MIN(rank) as best_placement'),
                    DB::raw('SUM(total_points) as total_points')
                );

            // Filtrar por partida específica si se solicita
            if ($matchId) {
                $query->where('tournament_matches.id', $matchId);
            } 
            // Si es global y hay filtro de modo
            elseif ($mode !== 'all') {
                $query->where('tournament_matches.game_mode', $mode);
            }

            return $query
                ->groupBy('team_signature')
                ->orderByDesc('total_points')
                ->orderByDesc('total_kills')
                ->get()
                ->map(function($team) {
                    $team->member_names = json_decode($team->members_json);
                    return $team;
                });
        }

        // 2. Ranking de JUGADORES (Individual)
        $query = DB::table('player_match_stats')
            ->join('tournament_matches', 'player_match_stats.tournament_match_id', '=', 'tournament_matches.id')
            ->where('tournament_matches.tournament_id', $tournamentId)
            // Filtro de seguridad para eliminar Bots "Unknown" en la consulta
            ->where('player_name', '!=', 'Unknown')
            ->select(
                'player_name',
                DB::raw('COUNT(*) as games_played'),
                DB::raw('SUM(kills) as total_kills'),
                DB::raw('SUM(damage_done) as total_damage'),
                DB::raw('MIN(placement) as best_placement'),
                DB::raw('SUM(CAST(JSON_EXTRACT(extra_stats, "$.totalPoints") AS DECIMAL(10,2))) as total_points')
            );

        // Filtrar por partida específica
        if ($matchId) {
            $query->where('tournament_matches.id', $matchId);
        }
        // Filtrar por modo si no es "todos"
        elseif ($mode !== 'all') {
            $query->where('tournament_matches.game_mode', $mode);
        }

        return $query
            ->groupBy('player_name')
            ->orderByDesc('total_points')
            ->orderByDesc('total_kills')
            ->get();
    }
}