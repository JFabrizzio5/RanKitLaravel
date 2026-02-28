<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Inertia\Inertia;

class LolTournamentController extends Controller
{
    // -----------------------------------------------------------------------
    // INFRAESTRUCTURA (No migración — crea tablas defensivamente)
    // -----------------------------------------------------------------------
    protected function ensureLolTablesReady(): void
    {
        // 1. Torneos
        if (!Schema::hasTable('lol_test_tournaments')) {
            Schema::create('lol_test_tournaments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('name');
                $table->string('game')->default('lol');
                $table->string('format')->default('swiss_elimination');
                $table->string('phase')->default('pending');
                $table->integer('swiss_rounds_total')->default(3);
                $table->integer('elimination_teams')->default(4);
                $table->timestamps();
            });
        } else {
            Schema::table('lol_test_tournaments', function (Blueprint $table) {
                if (!Schema::hasColumn('lol_test_tournaments', 'game'))
                    $table->string('game')->default('lol')->after('name');
                if (!Schema::hasColumn('lol_test_tournaments', 'format'))
                    $table->string('format')->default('swiss_elimination')->after('game');
                if (!Schema::hasColumn('lol_test_tournaments', 'phase'))
                    $table->string('phase')->default('pending')->after('format');
                if (!Schema::hasColumn('lol_test_tournaments', 'swiss_rounds_total'))
                    $table->integer('swiss_rounds_total')->default(3)->after('phase');
                if (!Schema::hasColumn('lol_test_tournaments', 'elimination_teams'))
                    $table->integer('elimination_teams')->default(4)->after('swiss_rounds_total');
            });
        }

        // 2. Equipos (con logo)
        if (!Schema::hasTable('lol_test_teams')) {
            Schema::create('lol_test_teams', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('lol_tournament_id');
                $table->string('name');
                $table->string('logo')->nullable();   // URL o ruta de imagen
                $table->integer('seed')->nullable();
                $table->integer('wins')->default(0);
                $table->integer('losses')->default(0);
                $table->timestamps();
            });
        } else {
            Schema::table('lol_test_teams', function (Blueprint $table) {
                if (!Schema::hasColumn('lol_test_teams', 'logo'))
                    $table->string('logo')->nullable()->after('name');
            });
        }

        // 3. Partidas
        if (!Schema::hasTable('lol_test_matches')) {
            Schema::create('lol_test_matches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('lol_tournament_id');
                $table->string('phase')->default('swiss');
                $table->integer('round')->default(1);
                $table->unsignedBigInteger('team1_id');
                $table->unsignedBigInteger('team2_id')->nullable();
                $table->unsignedBigInteger('winner_id')->nullable();
                $table->integer('score1')->default(0);
                $table->integer('score2')->default(0);
                $table->string('status')->default('pending');
                $table->timestamps();
            });
        }
    }

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------
    private function getTournament(int $id, ?int $userId = null)
    {
        $q = DB::table('lol_test_tournaments')->where('id', $id);
        if ($userId) $q->where('user_id', $userId);
        return $q->first();
    }

    private function getTeams(int $tournamentId): array
    {
        return DB::table('lol_test_teams')
            ->where('lol_tournament_id', $tournamentId)
            ->orderBy('seed')
            ->orderBy('id')
            ->get()
            ->toArray();
    }

    private function getMatches(int $tournamentId): array
    {
        $matches = DB::table('lol_test_matches')
            ->where('lol_tournament_id', $tournamentId)
            ->orderBy('phase')
            ->orderBy('round')
            ->orderBy('id')
            ->get();

        $teamMap = [];
        foreach (DB::table('lol_test_teams')->where('lol_tournament_id', $tournamentId)->get() as $t) {
            $teamMap[$t->id] = $t;
        }

        return $matches->map(function ($m) use ($teamMap) {
            $m->team1  = $teamMap[$m->team1_id] ?? null;
            $m->team2  = $m->team2_id ? ($teamMap[$m->team2_id] ?? null) : null;
            $m->winner = $m->winner_id ? ($teamMap[$m->winner_id] ?? null) : null;
            return $m;
        })->toArray();
    }

    /**
     * Construye una fila base con TODAS las columnas presentes (evita column-count mismatch en batch insert)
     */
    private function matchRow(array $overrides): array
    {
        return array_merge([
            'lol_tournament_id' => 0,
            'phase'             => 'swiss',
            'round'             => 1,
            'team1_id'          => 0,
            'team2_id'          => null,
            'winner_id'         => null,   // ← clave: siempre presente
            'score1'            => 0,
            'score2'            => 0,
            'status'            => 'pending',
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $overrides);
    }

    // -----------------------------------------------------------------------
    // LISTA / CREAR / VER / BORRAR
    // -----------------------------------------------------------------------

    public function index(Request $request)
    {
        $this->ensureLolTablesReady();
        $game   = $request->query('game', 'lol');
        $userId = auth()->id();

        $tournaments = DB::table('lol_test_tournaments')
            ->where('user_id', $userId)
            ->where('game', $game)
            ->orderBy('created_at', 'desc')
            ->get()->toArray();

        return Inertia::render('Lol/Index', [
            'tournaments' => $tournaments,
            'game'        => $game,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureLolTablesReady();
        $request->validate([
            'name'               => 'required|string|max:100',
            'game'               => 'required|in:lol,valorant',
            'format'             => 'required|in:elimination,swiss_elimination',
            'swiss_rounds_total' => 'nullable|integer|min:1|max:10',
            'elimination_teams'  => 'nullable|integer|min:2|max:64',
        ]);

        $format = $request->format;

        DB::table('lol_test_tournaments')->insert([
            'user_id'            => auth()->id(),
            'name'               => $request->name,
            'game'               => $request->game,
            'format'             => $format,
            'phase'              => $format === 'swiss_elimination' ? 'pending' : 'elimination',
            'swiss_rounds_total' => $format === 'swiss_elimination' ? ($request->swiss_rounds_total ?? 3) : 0,
            'elimination_teams'  => $request->elimination_teams ?? 4,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        return back()->with('success', 'Torneo creado.');
    }

    public function show(int $id)
    {
        $this->ensureLolTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404, 'Torneo no encontrado.');

        return Inertia::render('Lol/Show', [
            'tournament' => $tournament,
            'teams'      => $this->getTeams($id),
            'matches'    => $this->getMatches($id),
        ]);
    }

    public function destroy(int $id)
    {
        $this->ensureLolTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);

        DB::table('lol_test_matches')->where('lol_tournament_id', $id)->delete();
        DB::table('lol_test_teams')->where('lol_tournament_id', $id)->delete();
        DB::table('lol_test_tournaments')->where('id', $id)->delete();

        return redirect()->route('lol.index', ['game' => $tournament->game])
            ->with('success', 'Torneo eliminado.');
    }

    // -----------------------------------------------------------------------
    // EQUIPOS
    // -----------------------------------------------------------------------

    public function addTeam(Request $request, int $id)
    {
        $this->ensureLolTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);

        $request->validate(['name' => 'required|string|max:80']);

        $maxSeed = DB::table('lol_test_teams')
            ->where('lol_tournament_id', $id)->max('seed') ?? 0;

        DB::table('lol_test_teams')->insert([
            'lol_tournament_id' => $id,
            'name'              => $request->name,
            'logo'              => $request->logo ?? null,
            'seed'              => $maxSeed + 1,
            'wins'              => 0,
            'losses'            => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return back()->with('success', 'Equipo agregado.');
    }

    public function removeTeam(int $id, int $teamId)
    {
        $this->ensureLolTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);

        DB::table('lol_test_teams')
            ->where('id', $teamId)
            ->where('lol_tournament_id', $id)
            ->delete();

        return back()->with('success', 'Equipo eliminado.');
    }

    /**
     * Editar nombre/logo de un equipo
     */
    public function updateTeam(Request $request, int $id, int $teamId)
    {
        $this->ensureLolTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);

        $request->validate([
            'name' => 'required|string|max:80',
            'logo' => 'nullable|string|max:500',
        ]);

        DB::table('lol_test_teams')
            ->where('id', $teamId)
            ->where('lol_tournament_id', $id)
            ->update([
                'name'       => $request->name,
                'logo'       => $request->logo,
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Equipo actualizado.');
    }

    public function shuffleTeams(int $id)
    {
        $this->ensureLolTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);

        $teams = DB::table('lol_test_teams')
            ->where('lol_tournament_id', $id)->pluck('id')->toArray();

        $seeds = range(1, count($teams));
        shuffle($seeds);

        foreach ($teams as $i => $teamId) {
            DB::table('lol_test_teams')->where('id', $teamId)->update([
                'seed'       => $seeds[$i],
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Seeds mezclados aleatoriamente.');
    }

    public function sortTeamsByName(int $id)
    {
        $this->ensureLolTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);

        $teams = DB::table('lol_test_teams')
            ->where('lol_tournament_id', $id)
            ->orderBy('name')->pluck('id')->toArray();

        foreach ($teams as $i => $teamId) {
            DB::table('lol_test_teams')->where('id', $teamId)->update([
                'seed'       => $i + 1,
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Equipos ordenados por nombre.');
    }

    // -----------------------------------------------------------------------
    // BRACKET GENERATION
    // -----------------------------------------------------------------------

    public function generateBracket(int $id)
    {
        $this->ensureLolTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);

        $teams = DB::table('lol_test_teams')
            ->where('lol_tournament_id', $id)
            ->orderBy('wins', 'desc')->orderBy('seed')->get()->toArray();

        if (count($teams) < 2) {
            return back()->with('error', 'Necesitas al menos 2 equipos.');
        }

        if ($tournament->format === 'swiss_elimination' && in_array($tournament->phase, ['pending', 'swiss'])) {
            return $this->generateSwissRound($tournament, $teams);
        }

        if ($tournament->phase === 'elimination') {
            return $this->generateEliminationBracket($tournament, $teams);
        }

        return back()->with('error', 'No hay una fase que generar en este momento.');
    }

    private function generateSwissRound($tournament, array $teams): \Illuminate\Http\RedirectResponse
    {
        $id = $tournament->id;

        $currentRound = DB::table('lol_test_matches')
            ->where('lol_tournament_id', $id)->where('phase', 'swiss')->max('round') ?? 0;

        if ($currentRound > 0) {
            $pending = DB::table('lol_test_matches')
                ->where('lol_tournament_id', $id)->where('phase', 'swiss')
                ->where('round', $currentRound)->where('status', 'pending')->count();

            if ($pending > 0) {
                return back()->with('error', "Registra todos los resultados de la Ronda $currentRound antes de continuar.");
            }
        }

        $nextRound = $currentRound + 1;

        if ($nextRound > $tournament->swiss_rounds_total) {
            return back()->with('error', 'Ya se completaron todas las rondas Swiss. Avanza a Eliminación.');
        }

        $insertRows = [];
        $teamsArr   = array_values($teams);

        for ($i = 0; $i < count($teamsArr) - 1; $i += 2) {
            $insertRows[] = $this->matchRow([
                'lol_tournament_id' => $id,
                'phase'             => 'swiss',
                'round'             => $nextRound,
                'team1_id'          => $teamsArr[$i]->id,
                'team2_id'          => $teamsArr[$i + 1]->id,
                'status'            => 'pending',
            ]);
        }

        // BYE si número impar
        if (count($teamsArr) % 2 !== 0) {
            $byeTeam      = $teamsArr[count($teamsArr) - 1];
            $insertRows[] = $this->matchRow([
                'lol_tournament_id' => $id,
                'phase'             => 'swiss',
                'round'             => $nextRound,
                'team1_id'          => $byeTeam->id,
                'team2_id'          => null,
                'winner_id'         => $byeTeam->id,
                'status'            => 'done',
            ]);
            DB::table('lol_test_teams')->where('id', $byeTeam->id)->increment('wins');
        }

        DB::table('lol_test_matches')->insert($insertRows);
        DB::table('lol_test_tournaments')->where('id', $id)->update([
            'phase' => 'swiss', 'updated_at' => now(),
        ]);

        return back()->with('success', "Ronda $nextRound Swiss generada.");
    }

    private function generateEliminationBracket($tournament, array $teams): \Illuminate\Http\RedirectResponse
    {
        $id = $tournament->id;

        $topTeams = array_slice(array_values($teams), 0, $tournament->elimination_teams);
        $count    = count($topTeams);

        if ($count < 2) {
            return back()->with('error', 'No hay suficientes equipos para el bracket.');
        }

        $currentElimRound = DB::table('lol_test_matches')
            ->where('lol_tournament_id', $id)->where('phase', 'elimination')->max('round') ?? 0;

        $nextRound   = $currentElimRound + 1;
        $bracketSize = 1;
        while ($bracketSize < $count) $bracketSize *= 2;

        $bracket = $topTeams;
        while (count($bracket) < $bracketSize) $bracket[] = null;

        $insertRows = [];
        for ($i = 0; $i < $bracketSize; $i += 2) {
            $t1 = $bracket[$i];
            $t2 = $bracket[$i + 1];

            $isBye = (!$t2 && $t1);

            $insertRows[] = $this->matchRow([
                'lol_tournament_id' => $id,
                'phase'             => 'elimination',
                'round'             => $nextRound,
                'team1_id'          => $t1 ? $t1->id : 0,
                'team2_id'          => $t2 ? $t2->id : null,
                'winner_id'         => $isBye ? $t1->id : null,
                'status'            => $isBye ? 'done' : 'pending',
            ]);
        }

        DB::table('lol_test_matches')->insert($insertRows);
        DB::table('lol_test_tournaments')->where('id', $id)->update([
            'phase' => 'elimination', 'updated_at' => now(),
        ]);

        return back()->with('success', 'Bracket de Eliminación generado.');
    }

    // -----------------------------------------------------------------------
    // RESULTADOS
    // -----------------------------------------------------------------------

    public function recordResult(Request $request, int $id)
    {
        $this->ensureLolTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);

        $request->validate([
            'match_id'  => 'required|integer',
            'winner_id' => 'required|integer',
            'score1'    => 'nullable|integer|min:0',
            'score2'    => 'nullable|integer|min:0',
        ]);

        $match = DB::table('lol_test_matches')
            ->where('id', $request->match_id)
            ->where('lol_tournament_id', $id)
            ->first();

        abort_if(!$match || $match->status === 'done', 400, 'Partida no válida o ya registrada.');

        $winnerId = (int)$request->winner_id;
        $loserId  = ($match->team1_id == $winnerId) ? $match->team2_id : $match->team1_id;

        DB::table('lol_test_matches')->where('id', $match->id)->update([
            'winner_id'  => $winnerId,
            'score1'     => $request->score1 ?? 0,
            'score2'     => $request->score2 ?? 0,
            'status'     => 'done',
            'updated_at' => now(),
        ]);

        DB::table('lol_test_teams')->where('id', $winnerId)->increment('wins');
        if ($loserId) DB::table('lol_test_teams')->where('id', $loserId)->increment('losses');

        if ($match->phase === 'elimination') {
            $this->maybeGenerateNextElimRound($tournament, $match->round);
        }

        return back()->with('success', 'Resultado registrado.');
    }

    public function advancePhase(int $id)
    {
        $this->ensureLolTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);

        if ($tournament->phase !== 'swiss') {
            return back()->with('error', 'Solo puedes avanzar desde la fase Swiss.');
        }

        $pending = DB::table('lol_test_matches')
            ->where('lol_tournament_id', $id)->where('phase', 'swiss')
            ->where('status', 'pending')->count();

        if ($pending > 0) {
            return back()->with('error', 'Hay partidas Swiss sin resultado. Regístralas antes de avanzar.');
        }

        $teams = DB::table('lol_test_teams')
            ->where('lol_tournament_id', $id)
            ->orderBy('wins', 'desc')->orderBy('seed')->get()->toArray();

        return $this->generateEliminationBracket($tournament, $teams);
    }

    private function maybeGenerateNextElimRound($tournament, int $round): void
    {
        $id = $tournament->id;

        $pending = DB::table('lol_test_matches')
            ->where('lol_tournament_id', $id)->where('phase', 'elimination')
            ->where('round', $round)->where('status', 'pending')->count();

        if ($pending > 0) return;

        $winners = DB::table('lol_test_matches')
            ->join('lol_test_teams', 'lol_test_matches.winner_id', '=', 'lol_test_teams.id')
            ->where('lol_test_matches.lol_tournament_id', $id)
            ->where('lol_test_matches.phase', 'elimination')
            ->where('lol_test_matches.round', $round)
            ->orderBy('lol_test_teams.seed')
            ->select('lol_test_teams.*')
            ->get()->toArray();

        if (count($winners) <= 1) {
            DB::table('lol_test_tournaments')->where('id', $id)->update([
                'phase' => 'done', 'updated_at' => now(),
            ]);
            return;
        }

        $nextRound  = $round + 1;
        $insertRows = [];

        for ($i = 0; $i < count($winners) - 1; $i += 2) {
            $insertRows[] = $this->matchRow([
                'lol_tournament_id' => $id,
                'phase'             => 'elimination',
                'round'             => $nextRound,
                'team1_id'          => $winners[$i]->id,
                'team2_id'          => $winners[$i + 1]->id,
                'status'            => 'pending',
            ]);
        }

        if (count($winners) % 2 !== 0) {
            $byeTeam      = $winners[count($winners) - 1];
            $insertRows[] = $this->matchRow([
                'lol_tournament_id' => $id,
                'phase'             => 'elimination',
                'round'             => $nextRound,
                'team1_id'          => $byeTeam->id,
                'team2_id'          => null,
                'winner_id'         => $byeTeam->id,
                'status'            => 'done',
            ]);
            DB::table('lol_test_teams')->where('id', $byeTeam->id)->increment('wins');
        }

        DB::table('lol_test_matches')->insert($insertRows);
    }

    // -----------------------------------------------------------------------
    // WIDGET OBS (ruta pública — sin auth)
    // -----------------------------------------------------------------------

    /**
     * Renderiza el widget HTML para OBS
     * GET /lol/{id}/widget?phase=swiss|elimination|standings|all
     */
    public function widget(int $id, Request $request)
    {
        $this->ensureLolTablesReady();
        $tournament = $this->getTournament($id);
        abort_if(!$tournament, 404);

        $phase   = $request->query('phase', 'all');
        $teams   = $this->getTeams($id);
        $matches = $this->getMatches($id);

        // Devolvemos una vista blade standalone (sin layout) para que OBS la cargue directo
        return response()->view('lol-widget', [
            'tournament' => $tournament,
            'teams'      => $teams,
            'matches'    => $matches,
            'phase'      => $phase,
        ]);
    }

    /**
     * JSON de datos del torneo (para auto-refresh del widget)
     * GET /lol/{id}/widget-data
     */
    public function widgetData(int $id, Request $request)
    {
        $this->ensureLolTablesReady();
        $tournament = $this->getTournament($id);
        abort_if(!$tournament, 404);

        return response()->json([
            'tournament' => $tournament,
            'teams'      => $this->getTeams($id),
            'matches'    => $this->getMatches($id),
        ]);
    }
}

