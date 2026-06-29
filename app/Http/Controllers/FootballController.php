<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Inertia\Inertia;

class FootballController extends Controller
{
    // -----------------------------------------------------------------------
    // INFRAESTRUCTURA
    // -----------------------------------------------------------------------
    protected function ensureFootballTablesReady(): void
    {
        // 1. Torneos
        if (!Schema::hasTable('football_tournaments')) {
            Schema::create('football_tournaments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('name');
                $table->string('game')->default('football');
                $table->string('format')->default('elimination'); // elimination | league
                $table->string('phase')->default('pending');
                $table->integer('elimination_teams')->default(4);
                $table->integer('league_points_win')->default(3);
                $table->integer('league_points_draw')->default(1);
                $table->integer('league_points_loss')->default(0);
                $table->timestamps();
            });
        }

        // 2. Equipos
        if (!Schema::hasTable('football_teams')) {
            Schema::create('football_teams', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('football_tournament_id');
                $table->string('name');
                $table->string('logo')->nullable();
                $table->integer('seed')->nullable();
                $table->integer('wins')->default(0);
                $table->integer('draws')->default(0);
                $table->integer('losses')->default(0);
                $table->integer('goals_for')->default(0);
                $table->integer('goals_against')->default(0);
                $table->integer('points')->default(0);
                $table->timestamps();
            });
        }

        // 3. Jugadores
        if (!Schema::hasTable('football_players')) {
            Schema::create('football_players', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('football_team_id');
                $table->string('name');
                $table->string('number')->nullable();
                $table->string('position')->default('MID'); // GK | DEF | MID | FWD
                $table->integer('goals')->default(0);
                $table->integer('assists')->default(0);
                $table->integer('yellow_cards')->default(0);
                $table->integer('red_cards')->default(0);
                $table->timestamps();
            });
        }

        // 4. Partidos
        if (!Schema::hasTable('football_matches')) {
            Schema::create('football_matches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('football_tournament_id');
                $table->string('phase')->default('elimination');
                $table->integer('round')->default(1);
                $table->unsignedBigInteger('team1_id');
                $table->unsignedBigInteger('team2_id')->nullable();
                $table->unsignedBigInteger('winner_id')->nullable(); // null puede ser empate si status=done
                $table->integer('score1')->default(0);
                $table->integer('score2')->default(0);
                $table->string('status')->default('pending');
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamps();
            });
        }
    }

    private function getTournament(int $id, ?int $userId = null)
    {
        $q = DB::table('football_tournaments')->where('id', $id);
        if ($userId) {
            $user = auth()->user();
            if (!$user || !$user->isSuperAdmin()) {
                $q->where('user_id', $userId);
            }
        }
        return $q->first();
    }

    private function getTeams(int $tournamentId): array
    {
        return DB::table('football_teams')
            ->where('football_tournament_id', $tournamentId)
            ->orderBy('points', 'desc')
            ->orderByRaw('(goals_for - goals_against) desc')
            ->orderBy('id')
            ->get()
            ->toArray();
    }

    private function getPlayers(int $tournamentId): array
    {
        $teamIds = DB::table('football_teams')->where('football_tournament_id', $tournamentId)->pluck('id');
        return DB::table('football_players')->whereIn('football_team_id', $teamIds)->get()->toArray();
    }

    private function getMatches(int $tournamentId): array
    {
        $matches = DB::table('football_matches')
            ->where('football_tournament_id', $tournamentId)
            ->orderBy('phase')
            ->orderBy('round')
            ->orderBy('id')
            ->get();

        $teamMap = [];
        foreach (DB::table('football_teams')->where('football_tournament_id', $tournamentId)->get() as $t) {
            $teamMap[$t->id] = $t;
        }

        return $matches->map(function ($m) use ($teamMap) {
            $m->team1  = $teamMap[$m->team1_id] ?? null;
            $m->team2  = $m->team2_id ? ($teamMap[$m->team2_id] ?? null) : null;
            $m->winner = $m->winner_id ? ($teamMap[$m->winner_id] ?? null) : null;
            return $m;
        })->toArray();
    }

    private function matchRow(array $overrides): array
    {
        return array_merge([
            'football_tournament_id' => 0,
            'phase'             => 'elimination',
            'round'             => 1,
            'team1_id'          => 0,
            'team2_id'          => null,
            'winner_id'         => null,
            'score1'            => 0,
            'score2'            => 0,
            'status'            => 'pending',
            'scheduled_at'      => null,
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $overrides);
    }

    // -----------------------------------------------------------------------
    // CRUD BÁSICO
    // -----------------------------------------------------------------------
    public function index(Request $request)
    {
        $this->ensureFootballTablesReady();
        $tournaments = DB::table('football_tournaments')
            ->where('user_id', auth()->id())
            ->orderBy('id', 'desc')
            ->get();
        return Inertia::render('Football/Index', ['tournaments' => $tournaments]);
    }

    public function store(Request $request)
    {
        $this->ensureFootballTablesReady();
        $request->validate([
            'name' => 'required|string|max:255',
            'format' => 'required|in:elimination,league',
        ]);
        
        $id = DB::table('football_tournaments')->insertGetId([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'game' => 'football',
            'format' => $request->format,
            'league_points_win' => $request->league_points_win ?? 3,
            'league_points_draw' => $request->league_points_draw ?? 1,
            'league_points_loss' => $request->league_points_loss ?? 0,
            'elimination_teams' => $request->elimination_teams ?? 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return redirect()->route('football.show', $id);
    }

    public function show(int $id)
    {
        $this->ensureFootballTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);

        return Inertia::render('Football/Show', [
            'tournament' => $tournament,
            'teams'      => $this->getTeams($id),
            'players'    => $this->getPlayers($id),
            'matches'    => $this->getMatches($id),
        ]);
    }

    public function destroy(int $id)
    {
        $this->ensureFootballTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);

        $teamIds = DB::table('football_teams')->where('football_tournament_id', $id)->pluck('id');
        DB::table('football_players')->whereIn('football_team_id', $teamIds)->delete();
        DB::table('football_matches')->where('football_tournament_id', $id)->delete();
        DB::table('football_teams')->where('football_tournament_id', $id)->delete();
        DB::table('football_tournaments')->where('id', $id)->delete();

        return redirect()->route('football.index')->with('success', 'Torneo eliminado.');
    }

    // -----------------------------------------------------------------------
    // GESTIÓN EQUIPOS Y JUGADORES
    // -----------------------------------------------------------------------
    public function addTeam(Request $request, int $id)
    {
        $this->ensureFootballTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);
        if ($tournament->phase !== 'pending') return back()->with('error', 'Torneo en curso.');

        $request->validate(['name' => 'required|string', 'logo' => 'nullable|url']);
        DB::table('football_teams')->insert([
            'football_tournament_id' => $id,
            'name' => $request->name,
            'logo' => $request->logo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return back();
    }

    public function updateTeam(Request $request, int $id, int $teamId)
    {
        $this->ensureFootballTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);
        $request->validate(['name' => 'required|string', 'logo' => 'nullable|url']);
        DB::table('football_teams')->where('id', $teamId)->where('football_tournament_id', $id)->update([
            'name' => $request->name, 'logo' => $request->logo, 'updated_at' => now(),
        ]);
        return back();
    }

    public function removeTeam(int $id, int $teamId)
    {
        $this->ensureFootballTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);
        if ($tournament->phase !== 'pending') return back()->with('error', 'Torneo en curso.');

        DB::table('football_players')->where('football_team_id', $teamId)->delete();
        DB::table('football_teams')->where('id', $teamId)->where('football_tournament_id', $id)->delete();
        return back();
    }

    public function addPlayer(Request $request, int $id, int $teamId)
    {
        $this->ensureFootballTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);
        $request->validate([
            'name' => 'required|string',
            'number' => 'nullable|string',
            'position' => 'required|in:GK,DEF,MID,FWD'
        ]);
        DB::table('football_players')->insert([
            'football_team_id' => $teamId,
            'name' => $request->name,
            'number' => $request->number,
            'position' => $request->position,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return back();
    }

    public function removePlayer(int $id, int $playerId)
    {
        $this->ensureFootballTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);
        DB::table('football_players')->where('id', $playerId)->delete();
        return back();
    }

    // -----------------------------------------------------------------------
    // GENERACIÓN
    // -----------------------------------------------------------------------
    public function generate(int $id)
    {
        $this->ensureFootballTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);
        if ($tournament->phase !== 'pending') return back()->with('error', 'Ya generado.');

        $teams = DB::table('football_teams')->where('football_tournament_id', $id)->orderBy('id')->get()->toArray();
        shuffle($teams); // Randomize seeds for initial draw

        if ($tournament->format === 'elimination') {
            return $this->generateEliminationBracket($tournament, $teams);
        } elseif ($tournament->format === 'league') {
            return $this->generateLeagueMatches($tournament, $teams);
        }
        return back();
    }

    private function generateEliminationBracket($tournament, array $teams)
    {
        $id = $tournament->id;
        $count = count($teams);
        if ($count < 2) return back()->with('error', 'Se necesitan al menos 2 equipos.');

        // Build bracket (power of 2)
        $bracketSize = 1;
        while ($bracketSize < $count) $bracketSize *= 2;
        $bracket = $teams;
        while (count($bracket) < $bracketSize) $bracket[] = null;

        $insertRows = [];
        for ($i = 0; $i < $bracketSize; $i += 2) {
            $t1 = $bracket[$i];
            $t2 = $bracket[$i + 1];
            $isBye = ($t1 && !$t2);
            if (!$t1) continue;
            $insertRows[] = $this->matchRow([
                'football_tournament_id' => $id,
                'phase' => 'elimination',
                'round' => 1,
                'team1_id' => $t1->id,
                'team2_id' => $t2 ? $t2->id : null,
                'winner_id' => $isBye ? $t1->id : null,
                'status' => $isBye ? 'done' : 'pending',
            ]);
        }
        DB::table('football_matches')->insert($insertRows);
        DB::table('football_tournaments')->where('id', $id)->update(['phase' => 'elimination', 'updated_at' => now()]);
        return back()->with('success', 'Bracket de eliminación generado.');
    }

    private function generateLeagueMatches($tournament, array $teams)
    {
        $id = $tournament->id;
        $count = count($teams);
        if ($count < 2) return back()->with('error', 'Se necesitan al menos 2 equipos.');

        $teamList = array_values($teams);
        $n = $count;
        if ($n % 2 !== 0) {
            $teamList[] = null;
            $n++;
        }

        $insertRows = [];
        $rounds = $n - 1;

        for ($round = 1; $round <= $rounds; $round++) {
            for ($match = 0; $match < $n / 2; $match++) {
                $t1 = $teamList[$match];
                $t2 = $teamList[$n - 1 - $match];
                if ($t1 === null || $t2 === null) continue;
                $insertRows[] = $this->matchRow([
                    'football_tournament_id' => $id,
                    'phase' => 'league',
                    'round' => $round,
                    'team1_id' => $t1->id,
                    'team2_id' => $t2->id,
                    'status' => 'pending',
                ]);
            }
            $last = array_pop($teamList);
            array_splice($teamList, 1, 0, [$last]);
        }
        DB::table('football_matches')->insert($insertRows);
        DB::table('football_tournaments')->where('id', $id)->update(['phase' => 'league', 'updated_at' => now()]);
        return back()->with('success', "Liga generada: {$count} equipos.");
    }

    // -----------------------------------------------------------------------
    // REGISTRO DE RESULTADOS
    // -----------------------------------------------------------------------
    public function recordResult(Request $request, int $id)
    {
        $this->ensureFootballTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);

        $request->validate([
            'match_id' => 'required|integer',
            'score1' => 'required|integer|min:0',
            'score2' => 'required|integer|min:0',
            'stats' => 'nullable|array', // stats[player_id] = { goals, assists, yellow_cards, red_cards }
        ]);

        $match = DB::table('football_matches')->where('id', $request->match_id)->where('football_tournament_id', $id)->first();
        abort_if(!$match || $match->status === 'done', 400);

        $score1 = (int) $request->score1;
        $score2 = (int) $request->score2;
        $winnerId = null;

        if ($score1 > $score2) $winnerId = $match->team1_id;
        elseif ($score2 > $score1) $winnerId = $match->team2_id;
        else {
            if ($match->phase === 'elimination' && $request->has('penalties_winner_id')) {
                $winnerId = $request->penalties_winner_id;
            }
        }

        if ($match->phase === 'elimination' && $score1 === $score2 && !$winnerId) {
            return back()->with('error', 'En eliminación debe haber un ganador (desempate).');
        }

        DB::transaction(function () use ($match, $score1, $score2, $winnerId, $tournament, $request) {
            DB::table('football_matches')->where('id', $match->id)->update([
                'score1' => $score1,
                'score2' => $score2,
                'winner_id' => $winnerId,
                'status' => 'done',
                'updated_at' => now(),
            ]);

            // Actualizar stats de equipos
            $t1Stats = ['goals_for' => DB::raw("goals_for + $score1"), 'goals_against' => DB::raw("goals_against + $score2")];
            $t2Stats = ['goals_for' => DB::raw("goals_for + $score2"), 'goals_against' => DB::raw("goals_against + $score1")];

            if ($winnerId === $match->team1_id) {
                $t1Stats['wins'] = DB::raw("wins + 1");
                $t1Stats['points'] = DB::raw("points + {$tournament->league_points_win}");
                $t2Stats['losses'] = DB::raw("losses + 1");
                $t2Stats['points'] = DB::raw("points + {$tournament->league_points_loss}");
            } elseif ($winnerId === $match->team2_id) {
                $t2Stats['wins'] = DB::raw("wins + 1");
                $t2Stats['points'] = DB::raw("points + {$tournament->league_points_win}");
                $t1Stats['losses'] = DB::raw("losses + 1");
                $t1Stats['points'] = DB::raw("points + {$tournament->league_points_loss}");
            } else { // Empate
                $t1Stats['draws'] = DB::raw("draws + 1");
                $t1Stats['points'] = DB::raw("points + {$tournament->league_points_draw}");
                $t2Stats['draws'] = DB::raw("draws + 1");
                $t2Stats['points'] = DB::raw("points + {$tournament->league_points_draw}");
            }

            DB::table('football_teams')->where('id', $match->team1_id)->update($t1Stats);
            DB::table('football_teams')->where('id', $match->team2_id)->update($t2Stats);

            // Actualizar stats de jugadores
            if ($request->stats) {
                foreach ($request->stats as $playerId => $st) {
                    $upd = [];
                    if (!empty($st['goals'])) $upd['goals'] = DB::raw("goals + " . (int)$st['goals']);
                    if (!empty($st['assists'])) $upd['assists'] = DB::raw("assists + " . (int)$st['assists']);
                    if (!empty($st['yellow_cards'])) $upd['yellow_cards'] = DB::raw("yellow_cards + " . (int)$st['yellow_cards']);
                    if (!empty($st['red_cards'])) $upd['red_cards'] = DB::raw("red_cards + " . (int)$st['red_cards']);
                    if (count($upd) > 0) {
                        DB::table('football_players')->where('id', $playerId)->update($upd);
                    }
                }
            }

            // Si es eliminación, intentar generar siguiente ronda
            if ($match->phase === 'elimination') {
                $this->maybeGenerateNextElimRound($tournament, $match->round);
            }

            // Si es liga, ver si terminó
            if ($match->phase === 'league') {
                $pending = DB::table('football_matches')->where('football_tournament_id', $tournament->id)->where('status', 'pending')->count();
                if ($pending === 0) {
                    DB::table('football_tournaments')->where('id', $tournament->id)->update(['phase' => 'done', 'updated_at' => now()]);
                }
            }
        });

        return back()->with('success', 'Resultado registrado.');
    }

    private function maybeGenerateNextElimRound($tournament, int $round)
    {
        $id = $tournament->id;
        $pending = DB::table('football_matches')->where('football_tournament_id', $id)->where('phase', 'elimination')->where('round', $round)->where('status', 'pending')->count();
        if ($pending > 0) return;

        $winners = DB::table('football_matches')
            ->join('football_teams', 'football_matches.winner_id', '=', 'football_teams.id')
            ->where('football_matches.football_tournament_id', $id)
            ->where('football_matches.phase', 'elimination')
            ->where('football_matches.round', $round)
            ->orderBy('football_teams.id') // orden original o seed
            ->select('football_teams.*')
            ->get()->toArray();

        if (count($winners) <= 1) {
            DB::table('football_tournaments')->where('id', $id)->update(['phase' => 'done', 'updated_at' => now()]);
            return;
        }

        $nextRound = $round + 1;
        $insertRows = [];

        for ($i = 0; $i < count($winners) - 1; $i += 2) {
            $insertRows[] = $this->matchRow([
                'football_tournament_id' => $id,
                'phase' => 'elimination',
                'round' => $nextRound,
                'team1_id' => $winners[$i]->id,
                'team2_id' => $winners[$i + 1]->id,
                'status' => 'pending',
            ]);
        }

        if (count($winners) % 2 !== 0) {
            $byeTeam = $winners[count($winners) - 1];
            $insertRows[] = $this->matchRow([
                'football_tournament_id' => $id,
                'phase' => 'elimination',
                'round' => $nextRound,
                'team1_id' => $byeTeam->id,
                'team2_id' => null,
                'winner_id' => $byeTeam->id,
                'status' => 'done',
            ]);
            // Bye logic for stats? not required in soccer usually
        }

        DB::table('football_matches')->insert($insertRows);
    }
}
