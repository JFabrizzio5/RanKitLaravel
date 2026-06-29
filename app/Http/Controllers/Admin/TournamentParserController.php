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
     * Asegura que la infraestructura de DB esté lista y actualizada.
     */
    protected function ensureDatabaseIsReady()
    {
        // 0. Tabla de Usuarios (Asegurar que existe el rol)
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'role')) {
                    $table->string('role')->default('organizer'); // 'admin' o 'organizer'
                }
            });
        }

        // 1. Tabla de Torneos
        if (!Schema::hasTable('tournaments')) {
            Schema::create('tournaments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade'); // El dueño
                $table->string('name');
                $table->string('slug')->nullable();
                $table->string('twitch_channel')->nullable();
                $table->boolean('is_private')->default(false);
                $table->boolean('is_serialized')->default(false);
                $table->unsignedBigInteger('parent_tournament_id')->nullable();
                $table->string('access_code')->nullable();
                $table->longText('rules')->nullable(); // Reglas
                $table->longText('prizes')->nullable(); // Premios
                $table->json('scoring_format')->nullable(); // Configuración de puntos JSON
                $table->string('table_name')->nullable();
                $table->string('banner_image')->nullable();
                $table->timestamps();
            });
        } else {
             Schema::table('tournaments', function (Blueprint $table) {
                if (!Schema::hasColumn('tournaments', 'user_id')) {
                    // Si ya existía, añadimos el campo permitiendo nulos temporalmente
                    $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
                }
                if (!Schema::hasColumn('tournaments', 'twitch_channel')) $table->string('twitch_channel')->nullable();
                if (!Schema::hasColumn('tournaments', 'is_private')) $table->boolean('is_private')->default(false);
                if (!Schema::hasColumn('tournaments', 'is_serialized')) $table->boolean('is_serialized')->default(false);
                if (!Schema::hasColumn('tournaments', 'parent_tournament_id')) $table->unsignedBigInteger('parent_tournament_id')->nullable();
                if (!Schema::hasColumn('tournaments', 'access_code')) $table->string('access_code')->nullable();
                if (!Schema::hasColumn('tournaments', 'rules')) $table->longText('rules')->nullable();
                if (!Schema::hasColumn('tournaments', 'prizes')) $table->longText('prizes')->nullable();
                if (!Schema::hasColumn('tournaments', 'scoring_format')) $table->json('scoring_format')->nullable();
                if (!Schema::hasColumn('tournaments', 'table_name')) $table->string('table_name')->nullable();
                if (!Schema::hasColumn('tournaments', 'banner_image')) $table->string('banner_image')->nullable();
             });

            // Asignar torneos huérfanos (sin user_id) al usuario jangel
            if (Schema::hasColumn('tournaments', 'user_id')) {
                $jangel = DB::table('users')->where('email', '18jangel18@gmail.com')->first();
                if ($jangel) {
                    DB::table('tournaments')
                        ->whereNull('user_id')
                        ->update(['user_id' => $jangel->id]);
                }
            }
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

        // 6. Inscripciones y Pagos
        if (!Schema::hasTable('tournament_registrations')) {
            Schema::create('tournament_registrations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tournament_id');
                $table->string('player_name');
                $table->string('email');
                $table->string('whatsapp')->nullable();
                $table->string('discord')->nullable();
                $table->string('payment_status')->default('pending'); // pending, paid, rejected
                $table->text('payment_notes')->nullable();
                $table->unsignedBigInteger('confirmed_by_admin_id')->nullable();
                $table->timestamps();
            });
        }

        // 7. Seriación de Torneos (Clasificados)
        if (!Schema::hasTable('tournament_qualifiers')) {
            Schema::create('tournament_qualifiers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tournament_from_id');
                $table->unsignedBigInteger('tournament_to_id')->nullable();
                $table->string('player_name');
                $table->string('status')->default('qualified');
                $table->timestamps();
            });
        }

        // 8. Tabla de Canchas (Pitches)
        if (!Schema::hasTable('pitches')) {
            Schema::create('pitches', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type')->default('Futbol 7'); // Ej: Futbol 7, Futbol 5, etc.
                $table->decimal('price_per_hour', 8, 2)->default(0);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // 9. Tabla de Reservas de Canchas (Pitch Reservations)
        if (!Schema::hasTable('pitch_reservations')) {
            Schema::create('pitch_reservations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pitch_id');
                $table->string('customer_name');
                $table->string('customer_phone')->nullable();
                $table->dateTime('start_time');
                $table->dateTime('end_time');
                $table->string('status')->default('pending'); // pending, paid, cancelled
                $table->timestamps();

                $table->foreign('pitch_id')->references('id')->on('pitches')->onDelete('cascade');
            });
        }
    }

    /**
     * Helpers de Seguridad
     */
    private function isSuperAdmin($user)
    {
        return $user->isSuperAdmin();
    }

    private function getTournamentIfOwner($id) 
    {
        $user = auth()->user();
        $query = DB::table('tournaments')->where('id', $id);

        if (!$this->isSuperAdmin($user)) {
            $query->where('user_id', $user->id);
        }

        return $query->first();
    }

    /**
     * Vista principal (Filtra por usuario)
     */
    public function index()
    {
        $this->ensureDatabaseIsReady();
        $user = auth()->user();

        $query = DB::table('tournaments')
            ->leftJoin('users', 'tournaments.user_id', '=', 'users.id')
            ->select('tournaments.*', 'users.name as creator_name');

        if (!$this->isSuperAdmin($user)) {
            $query->where('tournaments.user_id', $user->id);
        }

        $tournaments = $query->orderBy('tournaments.created_at', 'desc')->get();

        foreach($tournaments as $tn) {
            $tn->scoring_format = json_decode($tn->scoring_format ?? '{}');

            if (!empty($tn->banner_image)) {
                $tn->banner_image = asset('public/' . $tn->banner_image);
            }
            
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
            'tournaments' => $tournaments,
            'isSuperAdmin' => $this->isSuperAdmin($user)
        ]);
    }
    
    public function store(Request $request) {
        $this->ensureDatabaseIsReady();
        $user = auth()->user();
        
        $data = [
            'user_id' => $user->id,
            'name' => $request->name,
            'twitch_channel' => $request->twitch_channel,
            'rules' => $request->rules,
            'prizes' => $request->prizes,
            'scoring_format' => $request->scoring_format ? json_encode($request->scoring_format) : null,
            'table_name' => Str::slug($request->name) . '_' . time(),
            'created_at' => now(), 
            'updated_at' => now()
        ];

        // Solo Admin puede crear torneos privados
        if ($this->isSuperAdmin($user) && $request->boolean('is_private')) {
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
        return redirect()->route('jangel.indexdos')->with('success', 'Torneo creado con éxito');
    }

    public function getSerializedStandings()
    {
        $this->ensureDatabaseIsReady();
        
        $tournaments = DB::table('tournaments')
            ->where('is_serialized', true)
            ->get();
            
        $standings = [];
        
        foreach ($tournaments as $t) {
            $stats = DB::table('player_match_stats')
                ->join('tournament_matches', 'player_match_stats.tournament_match_id', '=', 'tournament_matches.id')
                ->where('tournament_matches.tournament_id', $t->id)
                ->select(
                    'player_match_stats.player_name',
                    DB::raw('SUM(kills) as total_kills'),
                    DB::raw('SUM(damage_done) as total_damage'),
                    DB::raw('COUNT(tournament_match_id) as matches_played'),
                    DB::raw('MAX(placement) as best_placement')
                )
                ->groupBy('player_name')
                ->orderByDesc('total_kills')
                ->get();
                
            $standings[] = [
                'tournament' => $t,
                'standings' => $stats
            ];
        }
        
        return response()->json($standings);
    }
    
    public function update(Request $request, $id) {
        $tournament = $this->getTournamentIfOwner($id);
        if (!$tournament) return back()->with('error', 'No tienes permiso para editar este torneo.');

        $data = [
            'name' => $request->name,
            'twitch_channel' => $request->twitch_channel,
            'rules' => $request->rules,
            'prizes' => $request->prizes,
            'scoring_format' => $request->scoring_format ? json_encode($request->scoring_format) : null,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('tournaments', 'is_private')) {
            if ($this->isSuperAdmin(auth()->user())) {
                $isPrivate = $request->boolean('is_private');
                $data['is_private'] = $isPrivate;
                $data['access_code'] = $isPrivate ? $request->access_code : null;
            }
        }

        DB::table('tournaments')->where('id', $id)->update($data);
        return back()->with('success', 'Torneo actualizado.');
    }

    public function destroy($id) {
        $tournament = $this->getTournamentIfOwner($id);
        if (!$tournament) return back()->with('error', 'No tienes permiso para eliminar este torneo.');

        $matchCount = DB::table('tournament_matches')->where('tournament_id', $id)->count();
        if ($matchCount > 0) return back()->with('error', 'Elimina las partidas primero.');
        
        DB::table('tournaments')->where('id', $id)->delete();
        return back()->with('success', 'Torneo eliminado.');
    }

    public function uploadBanner(Request $request, $id)
    {
        $this->ensureDatabaseIsReady();
        $tournament = $this->getTournamentIfOwner($id);
        if (!$tournament) return back()->with('error', 'Permiso denegado.');

        $request->validate(['banner' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:5120']);

        $file = $request->file('banner');
        $filename = 'banner_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();

        $destination = public_path('torneos');
        if (!file_exists($destination)) {
            mkdir($destination, 0755, true);
        }

        // Eliminar imagen anterior si existe
        if (!empty($tournament->banner_image)) {
            $oldPath = public_path($tournament->banner_image);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $file->move($destination, $filename);

        DB::table('tournaments')->where('id', $id)->update([
            'banner_image' => 'torneos/' . $filename,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Imagen del torneo actualizada.');
    }

    public function storeScheduledMatch(Request $request, $tournamentId) {
        $this->ensureDatabaseIsReady();
        
        if (!$this->getTournamentIfOwner($tournamentId)) {
            return back()->with('error', 'Permiso denegado.');
        }

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
        $match = DB::table('tournament_matches')->where('id', $id)->first();
        if (!$match || !$this->getTournamentIfOwner($match->tournament_id)) {
            return back()->with('error', 'Permiso denegado.');
        }

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
        $match = DB::table('tournament_matches')->where('id', $matchId)->first();
        if (!$match || !$this->getTournamentIfOwner($match->tournament_id)) {
            return back()->with('error', 'Permiso denegado.');
        }

        DB::table('player_match_stats')->where('tournament_match_id', $matchId)->delete();
        DB::table('team_match_stats')->where('tournament_match_id', $matchId)->delete();
        DB::table('tournament_score_logs')->where('match_id', $matchId)->delete();
        DB::table('tournament_matches')->where('id', $matchId)->delete();
        return back()->with('success', 'Partida eliminada.');
    }

    // --- LÓGICA DE CÁLCULO DE PUNTOS INTERNA ---
    private function calculateScore($rank, $kills, $format, $gameMode = 'solo')
    {
        $multiplier = match(strtolower($gameMode)) {
            'duo' => 2,
            'trio' => 3,
            'squad' => 4,
            default => 1,
        };

        $points = 0;
        
        if (!empty($format) && isset($format->placement) && is_array($format->placement)) {
            $killPts = isset($format->kill_points) ? (float)$format->kill_points : 1;
            $points += ($kills * $killPts * $multiplier);

            foreach ($format->placement as $range) {
                $from = (int)($range->from ?? 0);
                $to = (int)($range->to ?? 0);
                $pts = (float)($range->points ?? 0);

                $start = max($from, $to); 
                $end = min($from, $to);

                if ($rank <= $start) {
                    $effectiveRank = max($rank, $end);
                    $steps = ($start - $effectiveRank) + 1;
                    if ($steps > 0) {
                        $points += ($steps * $pts * $multiplier);
                    }
                }
            }
        } 
        else {
            $points += ($kills * $multiplier); 

            if ($rank == 1) $points += (25 * $multiplier);
            elseif ($rank <= 5) $points += (15 * $multiplier);
            elseif ($rank <= 15) $points += (10 * $multiplier);
            elseif ($rank <= 25) $points += (5 * $multiplier);
        }

        return $points;
    }

    // --- PROCESAMIENTO PRINCIPAL (ANALYZE) ---
    public function processReplay(Request $request, $id)
    {
        $this->ensureDatabaseIsReady();
        
        $tournament = $this->getTournamentIfOwner($id);
        if (!$tournament) return back()->with('error', 'Permiso denegado.');
        
        $request->validate([
            'replay' => 'required|file',
            'mode' => 'required|integer', 
            'target_match_id' => 'nullable|integer' 
        ]);

        try {
            $scoringFormat = $tournament->scoring_format ? json_decode($tournament->scoring_format) : null;

            $file = $request->file('replay');
            $mode = (int)$request->input('mode');
            $targetMatchId = $request->input('target_match_id');
            $fileContent = file_get_contents($file->getRealPath());
            $fileName = uniqid() . '_' . $file->getClientOriginalName();

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
                    'rulesJson' => '' 
                ]);

            if (!$analyzeResponse->successful()) throw new \Exception("Error en Analyze: " . $analyzeResponse->body());

            $data = $analyzeResponse->json();

            DB::beginTransaction();

            $contentSignature = md5(json_encode($data['teamLeaderboard'] ?? []));
            $matchUid = 'sig_' . $contentSignature;
            
            if ($sessionID) {
                $existingCollision = DB::table('tournament_matches')
                    ->where('tournament_id', $id)
                    ->where('game_session_id', $sessionID)
                    ->first();
            } else {
                $existingCollision = DB::table('tournament_matches')
                    ->where('tournament_id', $id)
                    ->where('match_id', $matchUid)
                    ->first();
            }

            $currentMatchId = null;

            if ($targetMatchId) {
                // Ensure the target slot belongs to this tournament
                $targetSlot = DB::table('tournament_matches')
                    ->where('id', $targetMatchId)
                    ->where('tournament_id', $id)
                    ->first();

                if (!$targetSlot) {
                    DB::rollBack();
                    return back()->with('error', 'El slot seleccionado no pertenece a este torneo.');
                }

                // Use a slot-scoped match_id to avoid unique constraint conflicts when
                // the same game session is (re)assigned to a different slot.
                $currentMatchId = $targetMatchId;
                $slotMatchUid = $matchUid . '_' . $currentMatchId;

                DB::table('tournament_matches')->where('id', $currentMatchId)->update([
                    'match_id' => $slotMatchUid,
                    'game_session_id' => $sessionID, 
                    'raw_data' => json_encode($data),
                    'updated_at' => now(),
                ]);
            } else {
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

            DB::table('player_match_stats')->where('tournament_match_id', $currentMatchId)->delete();
            DB::table('team_match_stats')->where('tournament_match_id', $currentMatchId)->delete();

            $players = $data['playerLeaderboard'] ?? [];
            foreach ($players as $p) {
                if (($p['isBot'] ?? false) || ($p['playerName'] ?? '') === 'Unknown') continue;

                $rank = $p['leaderboardRank'] ?? 0;
                $kills = $p['kills'] ?? 0;
                
                $calculatedPoints = $this->calculateScore($rank, $kills, $scoringFormat, $this->getModeName($mode));

                DB::table('player_match_stats')->insert([
                    'tournament_match_id' => $currentMatchId,
                    'player_name' => $p['playerName'] ?? 'Unknown',
                    'placement' => $rank,
                    'kills' => $kills,
                    'extra_stats' => json_encode([
                        'teamId' => $p['teamId'] ?? -1,
                        'totalPoints' => $calculatedPoints, 
                        'manual_points' => 0 
                    ]),
                    'updated_at' => now(), 'created_at' => now(),
                ]);
            }

            $teams = $data['teamLeaderboard'] ?? [];
            foreach ($teams as $t) {
                $members = $t['memberNames'] ?? [];
                $members = array_filter($members, fn($m) => $m !== 'Unknown');
                if (empty($members)) continue;
                
                $members = array_values($members);
                sort($members); 
                
                $teamRank = $t['rank'] ?? ($t['leaderboardRank'] ?? 999);
                $teamTotalKills = $t['totalKills'] ?? 0;
                
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
        
        $tournament = $this->getTournamentIfOwner($tournamentId);
        if (!$tournament) return back()->with('error', 'Permiso denegado.');

        $request->validate(['replay' => 'required|file']);

        try {
            $scoringFormat = $tournament->scoring_format ? json_decode($tournament->scoring_format) : null;

            $file = $request->file('replay');
            $fileName = uniqid() . '_' . $file->getClientOriginalName();
            
            Log::info("--- INICIO APELACIÓN AUTOMÁTICA --- Tournament: $tournamentId");

            $response = Http::timeout(60)
                ->attach('file', file_get_contents($file->getRealPath()), $fileName)
                ->post('http://62.72.3.139:5138/api/FortniteParser/analyze-summary'); 

            if (!$response->successful()) throw new \Exception("Error API Summary: " . $response->body());

            $appealData = $response->json();
            $externalMatchId = strtoupper($appealData['matchId'] ?? '');
            $playerName = $appealData['replayOwnerName'] ?? null;
            $cleanSessionId = explode('|', $externalMatchId)[0];

            if (!$externalMatchId || !$playerName) throw new \Exception("Replay inválido o corrupto.");

            $match = DB::table('tournament_matches')
                ->where('tournament_id', $tournamentId)
                ->where(function($q) use ($externalMatchId, $cleanSessionId) {
                    $q->where('game_session_id', $externalMatchId)
                      ->orWhere('game_session_id', 'LIKE', '%' . $cleanSessionId . '%'); 
                })
                ->first();

            if (!$match) return back()->with('error', "APELACIÓN RECHAZADA: La partida base no ha sido subida por el administrador.");

            $kills = $appealData['kills'] ?? 0;
            $rank = $appealData['rank'] ?? 99;

            $calculatedPoints = $this->calculateScore($rank, $kills, $scoringFormat, $match->game_mode);

            Log::info("Apelación $playerName: Rank $rank, Kills $kills -> Puntos Calc: $calculatedPoints");

            DB::beginTransaction();

            $existingPlayer = DB::table('player_match_stats')
                ->where('tournament_match_id', $match->id)
                ->where('player_name', $playerName)
                ->first();
            
            $extraStats = $existingPlayer ? json_decode($existingPlayer->extra_stats, true) : [];
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

            $teamStats = DB::table('team_match_stats')
                ->where('tournament_match_id', $match->id)
                ->where('member_names', 'LIKE', '%"'.$playerName.'"%')
                ->first();

            if ($teamStats) {
                $members = json_decode($teamStats->member_names);
                $teamManualPoints = 0;
                $teamKills = 0;
                
                foreach($members as $m) {
                    $pStat = DB::table('player_match_stats')
                        ->where('tournament_match_id', $match->id)
                        ->where('player_name', $m)
                        ->first();
                    if ($pStat) {
                        $pExtra = json_decode($pStat->extra_stats, true);
                        $teamKills += $pStat->kills;
                        $teamManualPoints += ($pExtra['manual_points'] ?? 0);
                    }
                }
                
                $newRank = $rank;
                $teamPoints = $this->calculateScore($newRank, $teamKills, $scoringFormat, $match->game_mode) + $teamManualPoints;
                
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
        
        $tournament = $this->getTournamentIfOwner($tournamentId);
        if (!$tournament) return back()->with('error', 'Permiso denegado.');

        $request->validate([
            'match_id' => 'required|exists:tournament_matches,id',
            'player_name' => 'required|string',
            'points_change' => 'required|integer', 
            'reason' => 'required|string|max:255'
        ]);

        try {
            DB::beginTransaction();

            $matchId = $request->match_id;
            $player = $request->player_name;
            $change = (int)$request->points_change;
            $reason = $request->reason;

            $pStat = DB::table('player_match_stats')
                ->where('tournament_match_id', $matchId)
                ->where('player_name', $player)
                ->first();

            if (!$pStat) throw new \Exception("Jugador no encontrado en esta partida.");

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

            DB::table('tournament_score_logs')->insert([
                'tournament_id' => $tournamentId,
                'match_id' => $matchId,
                'player_name' => $player,
                'points_change' => $change,
                'reason' => $reason,
                'admin_id' => auth()->id(),
                'created_at' => now(), 'updated_at' => now()
            ]);

             $teamStats = DB::table('team_match_stats')
                ->where('tournament_match_id', $matchId)
                ->where('member_names', 'LIKE', '%"'.$player.'"%')
                ->first();
            
            if ($teamStats) {
                $members = json_decode($teamStats->member_names);
                $teamTotalKills = 0;
                $teamManualPoints = 0;
                
                foreach($members as $m) {
                    $ps = DB::table('player_match_stats')->where('tournament_match_id', $matchId)->where('player_name', $m)->first();
                    if ($ps) {
                        $ex = json_decode($ps->extra_stats, true);
                        $teamTotalKills += $ps->kills;
                        $teamManualPoints += ($ex['manual_points'] ?? 0);
                    }
                }
                
                $matchData = DB::table('tournament_matches')->where('id', $matchId)->first();
                $scoringFormat = $tournament->scoring_format ? json_decode($tournament->scoring_format) : null;
                
                $teamTotalPoints = $this->calculateScore($teamStats->rank, $teamTotalKills, $scoringFormat, $matchData->game_mode) + $teamManualPoints;
                
                DB::table('team_match_stats')->where('id', $teamStats->id)->update([
                    'total_points' => $teamTotalPoints,
                    'total_kills' => $teamTotalKills
                ]);
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

    /**
     * Edita directamente las estadísticas (kills, placement, puntos) de un jugador en una partida.
     * Accesible para todos los admins (no requiere superadmin).
     */
    public function updatePlayerResult(Request $request, $matchId)
    {
        $this->ensureDatabaseIsReady();

        $match = DB::table('tournament_matches')->where('id', $matchId)->first();
        if (!$match) return back()->with('error', 'Partida no encontrada.');

        $tournament = $this->getTournamentIfOwner($match->tournament_id);
        if (!$tournament) return back()->with('error', 'Permiso denegado.');

        $request->validate([
            'player_name'     => 'required|string',
            'kills'           => 'required|integer|min:0',
            'placement'       => 'required|integer|min:1',
            'points_override' => 'nullable|numeric',
        ]);

        try {
            DB::beginTransaction();

            $player = $request->player_name;
            $newKills = (int) $request->kills;
            $newPlacement = (int) $request->placement;

            $pStat = DB::table('player_match_stats')
                ->where('tournament_match_id', $matchId)
                ->where('player_name', $player)
                ->first();

            if (!$pStat) {
                DB::rollBack();
                return back()->with('error', 'Jugador no encontrado en esta partida.');
            }

            $scoringFormat = $tournament->scoring_format ? json_decode($tournament->scoring_format) : null;

            // Calcular puntos: si se proporciona un override directo, usarlo; si no, recalcular
            if ($request->filled('points_override')) {
                $newTotalPoints = (float) $request->points_override;
                $newKillPoints = $newKills * (isset($scoringFormat->kill_points) ? (float)$scoringFormat->kill_points : 1);
                $newPlacementPoints = $newTotalPoints - $newKillPoints;
            } else {
                $newTotalPoints = $this->calculateScore($newPlacement, $newKills, $scoringFormat, $match->game_mode);
                $newKillPoints = $newKills * (isset($scoringFormat->kill_points) ? (float)$scoringFormat->kill_points : 1);
                $newPlacementPoints = $newTotalPoints - $newKillPoints;
            }

            $extra = json_decode($pStat->extra_stats, true) ?? [];
            $oldTotalPoints = (float)($extra['totalPoints'] ?? 0);
            $extra['totalPoints'] = $newTotalPoints;
            $extra['killPoints'] = $newKillPoints;
            $extra['placementPoints'] = $newPlacementPoints;
            $extra['manual_points'] = $extra['manual_points'] ?? 0;
            $extra['edited_by_admin'] = true;

            DB::table('player_match_stats')
                ->where('id', $pStat->id)
                ->update([
                    'kills'       => $newKills,
                    'placement'   => $newPlacement,
                    'extra_stats' => json_encode($extra),
                ]);

            // Actualizar estadísticas del equipo si aplica
            $escapedPlayer = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $player);
            $teamStats = DB::table('team_match_stats')
                ->where('tournament_match_id', $matchId)
                ->where('member_names', 'LIKE', '%"' . $escapedPlayer . '"%')
                ->first();

            if ($teamStats) {
                $members = json_decode($teamStats->member_names);
                $teamTotalKills = 0;
                $teamManualPoints = 0;

                foreach ($members as $m) {
                    $ps = DB::table('player_match_stats')
                        ->where('tournament_match_id', $matchId)
                        ->where('player_name', $m)
                        ->first();
                    if ($ps) {
                        $ex = json_decode($ps->extra_stats, true);
                        $teamTotalKills += $ps->kills;
                        $teamManualPoints += ($ex['manual_points'] ?? 0);
                    }
                }

                $teamTotalPoints = $this->calculateScore($teamStats->rank, $teamTotalKills, $scoringFormat, $match->game_mode) + $teamManualPoints;

                DB::table('team_match_stats')->where('id', $teamStats->id)->update([
                    'total_points' => $teamTotalPoints,
                    'total_kills'  => $teamTotalKills,
                ]);
            }

            DB::table('tournament_score_logs')->insert([
                'tournament_id' => $tournament->id,
                'match_id'      => $matchId,
                'player_name'   => $player,
                'points_change' => $newTotalPoints - $oldTotalPoints,
                'reason'        => 'Edición directa de resultado por admin (kills: ' . $newKills . ', pos: ' . $newPlacement . ', pts: ' . $newTotalPoints . ')',
                'admin_id'      => auth()->id(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            DB::commit();
            return back()->with('success', "Resultado de {$player} actualizado correctamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

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
    
    public function getPublicData(Request $request, $id) {
         return response()->json([]);
    }

    // --- INSCRIPCIONES Y PAGOS ---
    public function registerForTournament(Request $request, $id)
    {
        $this->ensureDatabaseIsReady();
        $request->validate([
            'player_name' => 'required|string|max:255',
            'email' => 'required|email',
            'whatsapp' => 'nullable|string|max:20',
        ]);

        $exists = DB::table('tournament_registrations')
            ->where('tournament_id', $id)
            ->where('email', $request->email)
            ->first();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Ya existe un registro con este correo para este torneo.'], 400);
        }

        DB::table('tournament_registrations')->insert([
            'tournament_id' => $id,
            'player_name' => $request->player_name,
            'email' => $request->email,
            'whatsapp' => $request->whatsapp,
            'discord' => $request->discord,
            'payment_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Registro recibido correctamente.']);
    }

    public function getRegistrations($id)
    {
        $this->ensureDatabaseIsReady();
        $tournament = $this->getTournamentIfOwner($id);
        if (!$tournament) return response()->json(['error' => 'No autorizado'], 403);

        $registrations = DB::table('tournament_registrations')
            ->where('tournament_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($registrations);
    }

    public function updatePaymentStatus(Request $request, $registrationId)
    {
        $this->ensureDatabaseIsReady();
        $request->validate(['status' => 'required|in:pending,paid,rejected']);

        $reg = DB::table('tournament_registrations')->where('id', $registrationId)->first();
        if (!$reg) return response()->json(['error' => 'Registro no encontrado'], 404);

        $tournament = $this->getTournamentIfOwner($reg->tournament_id);
        if (!$tournament) return response()->json(['error' => 'No autorizado'], 403);

        DB::table('tournament_registrations')->where('id', $registrationId)->update([
            'payment_status' => $request->status,
            'confirmed_by_admin_id' => auth()->id(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    // --- SERIACIÓN DE TORNEOS (CLASIFICAR JUGADORES) ---
    public function classifyToNextRound(Request $request, $id)
    {
        $this->ensureDatabaseIsReady();
        $tournament = $this->getTournamentIfOwner($id);
        if (!$tournament) return back()->with('error', 'No autorizado');

        $request->validate([
            'players' => 'required|array',
            'players.*' => 'string',
            'target_tournament_id' => 'nullable|integer',
        ]);

        $targetId = $request->target_tournament_id;

        // Si no hay target_tournament, clonamos el torneo actual
        if (!$targetId) {
            $targetId = DB::table('tournaments')->insertGetId([
                'user_id' => $tournament->user_id,
                'name' => $tournament->name . ' - Fase 2',
                'slug' => $tournament->slug ? $tournament->slug . '-fase2' : null,
                'is_private' => $tournament->is_private,
                'scoring_format' => $tournament->scoring_format,
                'table_name' => 'fase2_' . time(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $target = DB::table('tournaments')->where('id', $targetId)->first();
            if (!$target || (!$this->isSuperAdmin(auth()->user()) && $target->user_id !== auth()->id())) {
                return back()->with('error', 'El torneo destino no es válido.');
            }
        }

        $inserts = [];
        foreach ($request->players as $p) {
            // Check if already qualified
            $exists = DB::table('tournament_qualifiers')
                ->where('tournament_from_id', $id)
                ->where('tournament_to_id', $targetId)
                ->where('player_name', $p)
                ->exists();
                
            if (!$exists) {
                $inserts[] = [
                    'tournament_from_id' => $id,
                    'tournament_to_id' => $targetId,
                    'player_name' => $p,
                    'status' => 'qualified',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (count($inserts) > 0) {
            DB::table('tournament_qualifiers')->insert($inserts);
        }

        return back()->with('success', count($inserts) . ' jugadores clasificados correctamente.');
    }

    public function getQualifiers($id)
    {
        $this->ensureDatabaseIsReady();
        $qualifiers = DB::table('tournament_qualifiers')
            ->where('tournament_from_id', $id)
            ->get();
            
        return response()->json($qualifiers);
    }

    // --- MÓDULO DE CANCHAS (PITCHES) ---

    public function getPitches()
    {
        $this->ensureDatabaseIsReady();
        $pitches = DB::table('pitches')->get();
        return response()->json($pitches);
    }

    public function storePitch(Request $request)
    {
        $this->ensureDatabaseIsReady();
        
        $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'price_per_hour' => 'numeric',
            'description' => 'nullable|string'
        ]);

        DB::table('pitches')->insert([
            'name' => $request->name,
            'type' => $request->type,
            'price_per_hour' => $request->price_per_hour ?? 0,
            'description' => $request->description,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return back()->with('success', 'Cancha creada correctamente.');
    }

    public function deletePitch($id)
    {
        DB::table('pitches')->where('id', $id)->delete();
        return back()->with('success', 'Cancha eliminada.');
    }

    public function getPitchReservations($id)
    {
        $reservations = DB::table('pitch_reservations')
            ->where('pitch_id', $id)
            ->orderBy('start_time', 'asc')
            ->get();
        return response()->json($reservations);
    }

    public function storePitchReservation(Request $request, $id)
    {
        $request->validate([
            'customer_name' => 'required|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        DB::table('pitch_reservations')->insert([
            'pitch_id' => $id,
            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return back()->with('success', 'Reserva creada.');
    }

    public function updatePitchReservationStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|string']);
        
        DB::table('pitch_reservations')->where('id', $id)->update([
            'status' => $request->status,
            'updated_at' => now()
        ]);

        return back()->with('success', 'Estado de reserva actualizado.');
    }
}