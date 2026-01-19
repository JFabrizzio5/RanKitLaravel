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
     * Crea tablas y agrega columnas si faltan.
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
                $table->string('match_id')->unique(); // ID único de la partida (del juego o generado)
                $table->string('game_mode')->default('solo');
                $table->string('map_name')->nullable();
                $table->json('raw_data')->nullable(); // Guardamos todo el JSON por si acaso
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
                $table->json('extra_stats')->nullable(); // Para guardar teamId, knocks, etc.
                $table->timestamps();
            });
        }

        // 4. Verificación de columnas específicas (Parches en caliente)
        Schema::table('tournament_matches', function (Blueprint $table) {
            if (!Schema::hasColumn('tournament_matches', 'game_mode')) {
                $table->string('game_mode')->default('solo')->after('match_id');
            }
        });

        Schema::table('player_match_stats', function (Blueprint $table) {
            if (!Schema::hasColumn('player_match_stats', 'damage_done')) {
                $table->integer('damage_done')->default(0);
            }
        });
    }

    public function index()
    {
        $this->ensureDatabaseIsReady();
        
        // Obtenemos los torneos
        $tournaments = DB::table('tournaments')->orderBy('created_at', 'desc')->get();

        // Adjuntamos las partidas a cada torneo manualmente para mostrarlas en el dashboard
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

    /**
     * Muestra detalles de un torneo específico (Vista pública o admin simple)
     */
    public function show($id)
    {
        $this->ensureDatabaseIsReady();
        $tournament = DB::table('tournaments')->where('id', $id)->first();

        if (!$tournament) {
            return redirect()->route('jangel.indexdos')->with('error', 'Torneo no encontrado.');
        }

        return Inertia::render('Admin/TournamentDetail', [
            'tournament' => $tournament,
            'rankingsSolo' => $this->getGlobalRanking($id, 'solo'),
            'rankingsDuo' => $this->getGlobalRanking($id, 'duo'),
            'rankingsSquad' => $this->getGlobalRanking($id, 'squad'),
            'recentMatches' => DB::table('tournament_matches')
                ->where('tournament_id', $id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
        ]);
    }

    /**
     * Elimina una partida y sus estadísticas asociadas
     */
    public function deleteMatch($matchId)
    {
        try {
            DB::beginTransaction();
            // 1. Eliminar stats de jugadores
            DB::table('player_match_stats')->where('tournament_match_id', $matchId)->delete();
            // 2. Eliminar la partida
            DB::table('tournament_matches')->where('id', $matchId)->delete();
            DB::commit();
            
            return back()->with('success', 'Partida eliminada y puntos recalculados.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al eliminar partida: ' . $e->getMessage());
        }
    }

    public function uploadReplay(Request $request, $id)
    {
        return $this->processReplay($request, $id);
    }

    public function processReplay(Request $request, $id)
    {
        $this->ensureDatabaseIsReady();
        
        // Validación: Requerimos el archivo y el modo de juego
        $request->validate([
            'replay' => 'required|file',
            'mode' => 'required|integer' // 1=Solo, 2=Duo, 3=Trio, 4=Squad
        ]);

        try {
            $file = $request->file('replay');
            $mode = (int)$request->input('mode');

            // 1. Llamada a la API Externa Real
            // URL: http://62.72.3.139:5138/api/FortniteParser/analyze
            $response = Http::timeout(120) // Damos tiempo suficiente
                ->attach(
                    'file', 
                    file_get_contents($file->getRealPath()), 
                    $file->getClientOriginalName()
                )
                ->post('http://62.72.3.139:5138/api/FortniteParser/analyze', [
                    'mode' => $mode,
                    'rulesJson' => '' // Enviamos vacío según tu curl
                ]);

            if (!$response->successful()) {
                throw new \Exception("Error en API externa: " . $response->body());
            }

            $data = $response->json();

            // 2. Guardado en Base de Datos
            DB::beginTransaction();

            $matchUid = $data['fileName'] ?? uniqid('match_'); // Usamos fileName o generamos uno

            // Insertar la partida
            $matchId = DB::table('tournament_matches')->insertGetId([
                'tournament_id' => $id,
                'match_id' => $matchUid . '_' . time(), // Aseguramos unicidad
                'game_mode' => $this->getModeName($mode),
                'map_name' => 'Island', // Dato no presente en tu JSON actual, placeholder
                'raw_data' => json_encode($data),
                'updated_at' => now(),
                'created_at' => now(),
            ]);

            // 3. Procesar Jugadores
            // NOTA: Tu JSON devuelve "playerLeaderboard", no "players"
            $players = $data['playerLeaderboard'] ?? [];

            foreach ($players as $p) {
                DB::table('player_match_stats')->insert([
                    'tournament_match_id' => $matchId,
                    'player_name' => $p['playerName'] ?? 'Unknown',
                    'placement' => $p['leaderboardRank'] ?? 0,
                    'kills' => $p['kills'] ?? 0,
                    'damage_done' => 0, // No viene en este endpoint específico de tu JSON, ponemos 0
                    'damage_taken' => 0,
                    // Guardamos todo lo demás en extra_stats para uso futuro
                    'extra_stats' => json_encode([
                        'teamId' => $p['teamId'] ?? -1,
                        'knocks' => $p['knocks'] ?? 0,
                        'isWinner' => $p['isWinner'] ?? false,
                        'totalPoints' => $p['totalPoints'] ?? 0,
                        'placementPoints' => $p['placementPoints'] ?? 0,
                        'killPoints' => $p['killPoints'] ?? 0,
                    ]),
                    'updated_at' => now(),
                    'created_at' => now(),
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

    // Método API para devolver el Leaderboard calculado al Frontend
    public function getLeaderboard($tournamentId)
    {
        // Si viene el nombre de la tabla antigua, intentamos buscar por ID primero, 
        // si no, asumimos que el parámetro es el ID directamente.
        
        return $this->getGlobalRanking($tournamentId);
    }

    protected function getGlobalRanking($tournamentId, $mode = null)
    {
        $query = DB::table('player_match_stats')
            ->join('tournament_matches', 'player_match_stats.tournament_match_id', '=', 'tournament_matches.id')
            ->where('tournament_matches.tournament_id', $tournamentId)
            ->select(
                'player_name',
                DB::raw('COUNT(*) as games_played'),
                DB::raw('SUM(kills) as total_kills'),
                DB::raw('SUM(damage_done) as total_damage'),
                DB::raw('MIN(placement) as best_placement'),
                // Sumamos los puntos guardados en extra_stats->totalPoints si existen, 
                // si no, usamos un cálculo por defecto.
                // Como JSON_EXTRACT puede ser lento o variar por driver, hacemos un cálculo SQL simple fallback
                // Pero tu JSON YA trae los puntos calculados por la API externa. Intentemos usarlos.
                // Para SQLite/MySQL modernos:
                DB::raw('SUM(CAST(JSON_EXTRACT(extra_stats, "$.totalPoints") AS DECIMAL(10,2))) as total_points')
            );

        if ($mode) {
            $query->where('tournament_matches.game_mode', $mode);
        }

        return $query
            ->groupBy('player_name')
            ->orderByDesc('total_points')
            ->orderByDesc('total_kills') // Desempate por kills
            ->get();
    }
}