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
        $tournaments = DB::table('tournaments')->orderBy('created_at', 'desc')->get();
        return Inertia::render('Admin/JangelDashboard', [
            'tournaments' => $tournaments
        ]);
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
            'rankingsSolo' => $this->getGlobalRanking($id, 'solo'),
            'rankingsDuo' => $this->getGlobalRanking($id, 'duo'),
            'recentMatches' => DB::table('tournament_matches')
                ->where('tournament_id', $id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
        ]);
    }

    /**
     * Este es el método que soluciona tu error "Call to undefined method uploadReplay"
     */
    public function uploadReplay(Request $request, $id)
    {
        return $this->processReplay($request, $id);
    }

    public function processReplay(Request $request, $id)
    {
        $this->ensureDatabaseIsReady();
        $request->validate(['replay' => 'required|file']);

        try {
            // Llamada a tu API de .NET (Asegúrate que el puerto/URL sea correcto)
            $response = Http::attach(
                'file', 
                file_get_contents($request->file('replay')->getRealPath()), 
                $request->file('replay')->getClientOriginalName()
            )->post('http://localhost:5000/api/FortniteParser/parse');

            if (!$response->successful()) {
                throw new \Exception("La API .NET falló: " . $response->body());
            }

            $data = $response->json();

            // DETECCIÓN DEL MODO DE JUEGO (Dúo, Solo, etc)
            $teamSize = $data['teamSize'] ?? 1;
            $gameMode = match($teamSize) {
                1 => 'solo',
                2 => 'duo',
                3 => 'trio',
                4 => 'squad',
                default => 'solo'
            };

            DB::beginTransaction();

            $matchUid = $data['matchId'] ?? uniqid('match_');

            // 1. Guardar la partida
            DB::table('tournament_matches')->updateOrInsert(
                ['match_id' => $matchUid],
                [
                    'tournament_id' => $id,
                    'game_mode' => $gameMode,
                    'map_name' => $data['mapName'] ?? 'Desconocido',
                    'raw_data' => json_encode($data),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $matchDb = DB::table('tournament_matches')->where('match_id', $matchUid)->first();

            // 2. Guardar estadísticas de cada jugador en la partida
            $players = $data['players'] ?? [];
            foreach ($players as $p) {
                DB::table('player_match_stats')->updateOrInsert(
                    [
                        'tournament_match_id' => $matchDb->id,
                        'player_name' => $p['username']
                    ],
                    [
                        'placement' => $p['placement'] ?? 0,
                        'kills' => $p['kills'] ?? 0,
                        'damage_done' => $p['damageToPlayers'] ?? 0,
                        'damage_taken' => $p['damageTaken'] ?? 0,
                        'extra_stats' => json_encode($p),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            DB::commit();
            return back()->with('success', "Partida ({$gameMode}) procesada correctamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error procesando replay: " . $e->getMessage());
            return back()->with('error', 'Error crítico: ' . $e->getMessage());
        }
    }

    protected function getGlobalRanking($tournamentId, $mode)
    {
        return DB::table('player_match_stats')
            ->join('tournament_matches', 'player_match_stats.tournament_match_id', '=', 'tournament_matches.id')
            ->where('tournament_matches.tournament_id', $tournamentId)
            ->where('tournament_matches.game_mode', $mode)
            ->select(
                'player_name',
                DB::raw('COUNT(*) as total_matches'),
                DB::raw('SUM(kills) as total_kills'),
                DB::raw('SUM(damage_done) as total_damage'),
                DB::raw('MIN(placement) as best_placement'),
                // Fórmula de puntos personalizable
                DB::raw('SUM(kills * 2) + SUM(CASE WHEN placement = 1 THEN 10 ELSE 0 END) as total_points')
            )
            ->groupBy('player_name')
            ->orderByDesc('total_points')
            ->get();
    }
}