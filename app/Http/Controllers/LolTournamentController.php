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
                $table->integer('swiss_rounds_total')->default(0);         // 0 = ilimitado (por umbral)
                $table->integer('elimination_teams')->default(4);
                $table->integer('swiss_wins_to_advance')->default(3);      // victorias para clasificar
                $table->integer('swiss_losses_to_eliminate')->default(3);  // derrotas para eliminar
                $table->boolean('swiss_first_round_manual')->default(false);
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
                    $table->integer('swiss_rounds_total')->default(0)->after('phase');
                if (!Schema::hasColumn('lol_test_tournaments', 'elimination_teams'))
                    $table->integer('elimination_teams')->default(4)->after('swiss_rounds_total');
                if (!Schema::hasColumn('lol_test_tournaments', 'swiss_wins_to_advance'))
                    $table->integer('swiss_wins_to_advance')->default(3)->after('elimination_teams');
                if (!Schema::hasColumn('lol_test_tournaments', 'swiss_losses_to_eliminate'))
                    $table->integer('swiss_losses_to_eliminate')->default(3)->after('swiss_wins_to_advance');
                if (!Schema::hasColumn('lol_test_tournaments', 'swiss_first_round_manual'))
                    $table->boolean('swiss_first_round_manual')->default(false)->after('swiss_losses_to_eliminate');
            });
        }

        // 2. Equipos
        if (!Schema::hasTable('lol_test_teams')) {
            Schema::create('lol_test_teams', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('lol_tournament_id');
                $table->string('name');
                $table->string('logo')->nullable();
                $table->integer('seed')->nullable();
                $table->integer('wins')->default(0);
                $table->integer('losses')->default(0);
                $table->string('swiss_status')->default('active'); // active | advanced | eliminated
                $table->string('de_bracket')->default('wb');       // wb | lb | out  (double elimination)
                $table->integer('points')->default(0);             // liga
                $table->timestamps();
            });
        } else {
            Schema::table('lol_test_teams', function (Blueprint $table) {
                if (!Schema::hasColumn('lol_test_teams', 'logo'))
                    $table->string('logo')->nullable()->after('name');
                if (!Schema::hasColumn('lol_test_teams', 'swiss_status'))
                    $table->string('swiss_status')->default('active')->after('losses');
                if (!Schema::hasColumn('lol_test_teams', 'de_bracket'))
                    $table->string('de_bracket')->default('wb')->after('swiss_status');
                if (!Schema::hasColumn('lol_test_teams', 'points'))
                    $table->integer('points')->default(0)->after('de_bracket');
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
     * Construye una fila base con TODAS las columnas presentes
     */
    private function matchRow(array $overrides): array
    {
        return array_merge([
            'lol_tournament_id' => 0,
            'phase'             => 'swiss',
            'round'             => 1,
            'team1_id'          => 0,
            'team2_id'          => null,
            'winner_id'         => null,
            'score1'            => 0,
            'score2'            => 0,
            'status'            => 'pending',
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $overrides);
    }

    /**
     * Construye historial de enfrentamientos: par ordenado (min, max) → cantidad de veces
     */
    private function buildMatchHistory(int $tournamentId): array
    {
        $history = [];
        $played = DB::table('lol_test_matches')
            ->where('lol_tournament_id', $tournamentId)
            ->where('phase', 'swiss')
            ->whereNotNull('team2_id')
            ->get(['team1_id', 'team2_id']);

        foreach ($played as $m) {
            $key = min($m->team1_id, $m->team2_id) . '-' . max($m->team1_id, $m->team2_id);
            $history[$key] = ($history[$key] ?? 0) + 1;
        }
        return $history;
    }

    /**
     * Swiss Estricto: empareja equipos activos evitando rematches.
     * Si no hay oponente fresco, usa el que se ha enfrentado menos veces (fallback).
     * Retorna array de pares [[t1, t2], ...] y posibles BYEs.
     */
    private function strictSwissPairing(array $activeTeams, array $history): array
    {
        $teams     = array_values($activeTeams);
        $pairs     = [];
        $paired    = [];

        for ($i = 0; $i < count($teams); $i++) {
            if (in_array($teams[$i]->id, $paired)) continue;

            $t1 = $teams[$i];
            $bestOpponent  = null;
            $bestFaceCount = PHP_INT_MAX;

            for ($j = $i + 1; $j < count($teams); $j++) {
                if (in_array($teams[$j]->id, $paired)) continue;
                $t2  = $teams[$j];
                $key = min($t1->id, $t2->id) . '-' . max($t1->id, $t2->id);
                $faceCount = $history[$key] ?? 0;

                // Preferir 0 veces; si no hay frescos, preferir el menor número de veces
                if ($faceCount < $bestFaceCount) {
                    $bestFaceCount = $faceCount;
                    $bestOpponent  = $t2;
                    if ($faceCount === 0) break; // ideal, ya encontramos rival fresco
                }
            }

            if ($bestOpponent !== null) {
                $pairs[]  = [$t1, $bestOpponent];
                $paired[] = $t1->id;
                $paired[] = $bestOpponent->id;
            }
            // Si no hay oponente (número impar), quedará como BYE al final
        }

        // BYE: el equipo no emparejado
        $byeTeam = null;
        foreach ($teams as $t) {
            if (!in_array($t->id, $paired)) {
                $byeTeam = $t;
                break;
            }
        }

        return ['pairs' => $pairs, 'bye' => $byeTeam];
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
            'name'                       => 'required|string|max:100',
            'game'                       => 'required|in:lol,valorant',
            'format'                     => 'required|in:elimination,swiss_elimination,double_elimination,league',
            'swiss_rounds_total'         => 'nullable|integer|min:0|max:20',
            'elimination_teams'          => 'nullable|integer|min:2|max:64',
            'swiss_wins_to_advance'      => 'nullable|integer|min:1|max:20',
            'swiss_losses_to_eliminate'  => 'nullable|integer|min:1|max:20',
            'swiss_first_round_manual'   => 'nullable|boolean',
        ]);

        $format = $request->format;
        $isSwiss = $format === 'swiss_elimination';

        DB::table('lol_test_tournaments')->insert([
            'user_id'                    => auth()->id(),
            'name'                       => $request->name,
            'game'                       => $request->game,
            'format'                     => $format,
            'phase'                      => $format === 'elimination' ? 'elimination' : 'pending',
            'swiss_rounds_total'         => $isSwiss ? ($request->swiss_rounds_total ?? 0) : 0,
            'elimination_teams'          => $request->elimination_teams ?? 4,
            'swiss_wins_to_advance'      => $isSwiss ? ($request->swiss_wins_to_advance ?? 3) : 0,
            'swiss_losses_to_eliminate'  => $isSwiss ? ($request->swiss_losses_to_eliminate ?? 3) : 0,
            'swiss_first_round_manual'   => $isSwiss ? ($request->swiss_first_round_manual ?? false) : false,
            'created_at'                 => now(),
            'updated_at'                 => now(),
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
            'swiss_status'      => 'active',
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

        // Double Elimination — initial generation
        if ($tournament->format === 'double_elimination' && $tournament->phase === 'pending') {
            return $this->generateDoubleElimStart($tournament, $teams);
        }

        // League — generate all round-robin matches
        if ($tournament->format === 'league' && $tournament->phase === 'pending') {
            return $this->generateLeagueMatches($tournament, $teams);
        }

        if ($tournament->format === 'swiss_elimination' && in_array($tournament->phase, ['pending', 'swiss'])) {
            return $this->generateSwissRound($tournament, $teams);
        }

        if ($tournament->phase === 'elimination') {
            return $this->generateEliminationBracket($tournament, $teams);
        }

        return back()->with('error', 'No hay una fase que generar en este momento.');
    }

    /**
     * Swiss Estricto con umbral de victorias/derrotas.
     * Solo empareja equipos con swiss_status = 'active'.
     */
    private function generateSwissRound($tournament, array $allTeams): \Illuminate\Http\RedirectResponse
    {
        $id = $tournament->id;

        // Verificar rondas pendientes
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

        // Verificar límite de rondas (si swiss_rounds_total > 0)
        $nextRound = $currentRound + 1;
        if ($tournament->swiss_rounds_total > 0 && $nextRound > $tournament->swiss_rounds_total) {
            return back()->with('error', 'Ya se completaron todas las rondas Swiss. Avanza a Eliminación.');
        }

        // Si round 1 es manual y aún no fue configurado, bloquear
        if ($nextRound === 1 && $tournament->swiss_first_round_manual) {
            return back()->with('error', 'El Round 1 está configurado como manual. Usa "Configurar Round 1" para definir los emparejamientos.');
        }

        // Solo equipos activos
        $activeTeams = array_values(array_filter($allTeams, fn($t) => ($t->swiss_status ?? 'active') === 'active'));

        if (count($activeTeams) < 2) {
            // Puede que todos hayan clasificado/eliminado — intentar auto-avanzar
            return $this->tryAutoAdvance($tournament);
        }

        // Construir historial y hacer emparejamiento estricto
        $history = $this->buildMatchHistory($id);
        $result  = $this->strictSwissPairing($activeTeams, $history);

        $insertRows = [];
        foreach ($result['pairs'] as [$t1, $t2]) {
            $insertRows[] = $this->matchRow([
                'lol_tournament_id' => $id,
                'phase'             => 'swiss',
                'round'             => $nextRound,
                'team1_id'          => $t1->id,
                'team2_id'          => $t2->id,
                'status'            => 'pending',
            ]);
        }

        // BYE si número impar
        if ($result['bye'] !== null) {
            $byeTeam      = $result['bye'];
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

            // Verificar umbral tras BYE
            $this->checkAndUpdateSwissStatus($id, $byeTeam->id, $tournament);
        }

        DB::table('lol_test_matches')->insert($insertRows);
        DB::table('lol_test_tournaments')->where('id', $id)->update([
            'phase' => 'swiss', 'updated_at' => now(),
        ]);

        return back()->with('success', "Ronda $nextRound Swiss generada (emparejamiento estricto).");
    }

    /**
     * Guarda el Round 1 con emparejamientos manuales.
     * POST body: { pairs: [[team1_id, team2_id], ...] }
     */
    public function setManualRound1(Request $request, int $id)
    {
        $this->ensureLolTablesReady();
        $tournament = $this->getTournament($id, auth()->id());
        abort_if(!$tournament, 404);

        if (!$tournament->swiss_first_round_manual) {
            return back()->with('error', 'Este torneo no tiene Round 1 manual habilitado.');
        }

        $existing = DB::table('lol_test_matches')
            ->where('lol_tournament_id', $id)->where('phase', 'swiss')->where('round', 1)->count();
        if ($existing > 0) {
            return back()->with('error', 'El Round 1 ya fue generado.');
        }

        $request->validate([
            'pairs'              => 'required|array|min:1',
            'pairs.*.team1_id'   => 'required|integer',
            'pairs.*.team2_id'   => 'nullable|integer',
        ]);

        // Validar que los equipos pertenecen al torneo y no se repiten
        $teamIds = DB::table('lol_test_teams')
            ->where('lol_tournament_id', $id)->pluck('id')->toArray();

        $usedIds = [];
        $insertRows = [];

        foreach ($request->pairs as $pair) {
            $t1id = (int) $pair['team1_id'];
            $t2id = isset($pair['team2_id']) ? (int) $pair['team2_id'] : null;

            if (!in_array($t1id, $teamIds)) {
                return back()->with('error', "Equipo $t1id no pertenece a este torneo.");
            }
            if ($t2id && !in_array($t2id, $teamIds)) {
                return back()->with('error', "Equipo $t2id no pertenece a este torneo.");
            }
            if (in_array($t1id, $usedIds) || ($t2id && in_array($t2id, $usedIds))) {
                return back()->with('error', 'Un equipo aparece en más de un par.');
            }

            $usedIds[] = $t1id;
            if ($t2id) $usedIds[] = $t2id;

            if ($t2id === null) {
                // BYE manual
                $insertRows[] = $this->matchRow([
                    'lol_tournament_id' => $id,
                    'phase'             => 'swiss',
                    'round'             => 1,
                    'team1_id'          => $t1id,
                    'team2_id'          => null,
                    'winner_id'         => $t1id,
                    'status'            => 'done',
                ]);
                DB::table('lol_test_teams')->where('id', $t1id)->increment('wins');
                $this->checkAndUpdateSwissStatus($id, $t1id, $tournament);
            } else {
                $insertRows[] = $this->matchRow([
                    'lol_tournament_id' => $id,
                    'phase'             => 'swiss',
                    'round'             => 1,
                    'team1_id'          => $t1id,
                    'team2_id'          => $t2id,
                    'status'            => 'pending',
                ]);
            }
        }

        DB::table('lol_test_matches')->insert($insertRows);
        DB::table('lol_test_tournaments')->where('id', $id)->update([
            'phase' => 'swiss', 'updated_at' => now(),
        ]);

        return back()->with('success', 'Round 1 configurado manualmente.');
    }

    /**
     * Verifica si un equipo alcanzó el umbral y actualiza su swiss_status.
     */
    private function checkAndUpdateSwissStatus(int $tournamentId, int $teamId, $tournament): void
    {
        $team = DB::table('lol_test_teams')->where('id', $teamId)->first();
        if (!$team || ($team->swiss_status ?? 'active') !== 'active') return;

        $winsThreshold   = $tournament->swiss_wins_to_advance ?? 3;
        $lossesThreshold = $tournament->swiss_losses_to_eliminate ?? 3;

        if ($team->wins >= $winsThreshold) {
            DB::table('lol_test_teams')->where('id', $teamId)->update([
                'swiss_status' => 'advanced',
                'updated_at'   => now(),
            ]);
        } elseif ($team->losses >= $lossesThreshold) {
            DB::table('lol_test_teams')->where('id', $teamId)->update([
                'swiss_status' => 'eliminated',
                'updated_at'   => now(),
            ]);
        }
    }

    /**
     * Intenta auto-avanzar a eliminación si todos los equipos tienen un swiss_status definitivo
     * (no quedan equipos en 'active') o si no hay más rondas Swiss posibles.
     */
    private function tryAutoAdvance($tournament): \Illuminate\Http\RedirectResponse
    {
        $id = $tournament->id;

        $activeCount = DB::table('lol_test_teams')
            ->where('lol_tournament_id', $id)
            ->where('swiss_status', 'active')
            ->count();

        if ($activeCount > 0) {
            return back()->with('error', 'No hay suficientes equipos activos para generar una ronda.');
        }

        // Todos definidos — auto-avanzar con los que clasificaron
        $advancedTeams = DB::table('lol_test_teams')
            ->where('lol_tournament_id', $id)
            ->where('swiss_status', 'advanced')
            ->orderBy('wins', 'desc')
            ->orderBy('seed')
            ->get()->toArray();

        if (count($advancedTeams) < 2) {
            return back()->with('error', 'No hay suficientes equipos clasificados para el bracket de eliminación.');
        }

        return $this->generateEliminationBracket($tournament, $advancedTeams);
    }

    private function generateEliminationBracket($tournament, array $teams): \Illuminate\Http\RedirectResponse
    {
        $id = $tournament->id;

        // Si viene de Swiss, usa solo los 'advanced'; si es eliminación directa, todos
        if ($tournament->format === 'swiss_elimination') {
            $topTeams = array_slice(array_values($teams), 0, $tournament->elimination_teams > 0 ? $tournament->elimination_teams : count($teams));
        } else {
            $topTeams = array_values($teams);
        }

        $count = count($topTeams);
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
            $t1    = $bracket[$i];
            $t2    = $bracket[$i + 1];
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

        $winnerId = (int) $request->winner_id;

        // VALIDACIÓN ESTRICTA: El ganador DEBE ser uno de los dos equipos de este match específico
        if ($winnerId !== (int)$match->team1_id && $winnerId !== (int)$match->team2_id) {
            return back()->with('error', 'El ganador seleccionado no pertenece a esta partida.');
        }

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

        // Verificar umbrales en fase Swiss
        if ($match->phase === 'swiss') {
            $this->checkAndUpdateSwissStatus($id, $winnerId, $tournament);
            if ($loserId) $this->checkAndUpdateSwissStatus($id, $loserId, $tournament);

            // Verificar si todos los activos de la ronda actual tienen resultado
            // y si ya no quedan equipos activos → auto-avanzar
            $pendingCurrentRound = DB::table('lol_test_matches')
                ->where('lol_tournament_id', $id)
                ->where('phase', 'swiss')
                ->where('round', $match->round)
                ->where('status', 'pending')
                ->count();

            if ($pendingCurrentRound === 0) {
                $activeCount = DB::table('lol_test_teams')
                    ->where('lol_tournament_id', $id)
                    ->where('swiss_status', 'active')
                    ->count();

                // Si no quedan activos, auto-avanzar a eliminación
                if ($activeCount === 0) {
                    $advancedTeams = DB::table('lol_test_teams')
                        ->where('lol_tournament_id', $id)
                        ->where('swiss_status', 'advanced')
                        ->orderBy('wins', 'desc')
                        ->orderBy('seed')
                        ->get()->toArray();

                    if (count($advancedTeams) >= 2) {
                        $this->generateEliminationBracket($tournament, $advancedTeams);
                    }
                }
            }
        }

        if ($match->phase === 'elimination') {
            $this->maybeGenerateNextElimRound($tournament, $match->round);
        }

        // Double Elimination — handle WB/LB/GF advancement
        if (in_array($match->phase, ['winner', 'loser', 'grand_final'])) {
            $updatedMatch = (object)[
                'id'        => $match->id,
                'phase'     => $match->phase,
                'round'     => $match->round,
                'team1_id'  => $match->team1_id,
                'team2_id'  => $match->team2_id,
                'winner_id' => $winnerId,
                'status'    => 'done',
            ];
            $this->handleDeResult($tournament, $updatedMatch);
        }

        // League — award points
        if ($match->phase === 'league') {
            $this->handleLeagueResult($tournament, $winnerId);
        }

        return back()->with('success', 'Resultado registrado.');
    }

    /**
     * Avance manual a eliminación (fallback cuando el admin quiere forzarlo)
     */
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

        $advancedTeams = DB::table('lol_test_teams')
            ->where('lol_tournament_id', $id)
            ->where('swiss_status', 'advanced')
            ->orderBy('wins', 'desc')->orderBy('seed')
            ->get()->toArray();

        // Si no hay ninguno con 'advanced' aún (torneo sin umbral definido), tomar los mejores
        if (count($advancedTeams) < 2) {
            $advancedTeams = DB::table('lol_test_teams')
                ->where('lol_tournament_id', $id)
                ->orderBy('wins', 'desc')->orderBy('seed')
                ->limit($tournament->elimination_teams)
                ->get()->toArray();
        }

        return $this->generateEliminationBracket($tournament, $advancedTeams);
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

        return response()->view('lol-widget', [
            'tournament' => $tournament,
            'teams'      => $teams,
            'matches'    => $matches,
            'phase'      => $phase,
        ]);
    }

    /**
     * GET /lol/{id}/bracket?phase=swiss|elimination|all
     */
    public function bracket(int $id, Request $request)
    {
        $this->ensureLolTablesReady();
        $tournament = $this->getTournament($id);
        abort_if(!$tournament, 404);

        $phase   = $request->query('phase', 'all');
        $teams   = $this->getTeams($id);
        $matches = $this->getMatches($id);

        return response()->view('lol-bracket', [
            'tournament' => $tournament,
            'teams'      => $teams,
            'matches'    => $matches,
            'phase'      => $phase,
        ]);
    }

    /**
     * GET /lol/{id}/widget-data — JSON para auto-refresh
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

    // -----------------------------------------------------------------------
    // DOUBLE ELIMINATION — WINNER BRACKET + LOSER BRACKET
    // -----------------------------------------------------------------------

    /**
     * Initial generation: WB Round 1 with all seeded teams.
     */
    private function generateDoubleElimStart($tournament, array $teams): \Illuminate\Http\RedirectResponse
    {
        $id    = $tournament->id;
        $count = count($teams);

        if ($count < 2) {
            return back()->with('error', 'Necesitas al menos 2 equipos para Double Elimination.');
        }

        // Reset de_bracket for all teams
        DB::table('lol_test_teams')
            ->where('lol_tournament_id', $id)
            ->update(['de_bracket' => 'wb', 'updated_at' => now()]);

        // Build seeded bracket (pad to next power of 2)
        $bracketSize = 1;
        while ($bracketSize < $count) $bracketSize *= 2;

        $bracket = $teams;
        while (count($bracket) < $bracketSize) $bracket[] = null;

        $insertRows = [];
        for ($i = 0; $i < $bracketSize; $i += 2) {
            $t1    = $bracket[$i];
            $t2    = $bracket[$i + 1];
            $isBye = ($t1 && !$t2);

            if (!$t1) continue;

            $insertRows[] = $this->matchRow([
                'lol_tournament_id' => $id,
                'phase'             => 'winner',
                'round'             => 1,
                'team1_id'          => $t1->id,
                'team2_id'          => $t2 ? $t2->id : null,
                'winner_id'         => $isBye ? $t1->id : null,
                'status'            => $isBye ? 'done' : 'pending',
            ]);
        }

        DB::table('lol_test_matches')->insert($insertRows);
        DB::table('lol_test_tournaments')->where('id', $id)->update([
            'phase' => 'elimination', 'updated_at' => now(),
        ]);

        return back()->with('success', 'Double Elimination iniciado — WB Ronda 1 generada.');
    }

    /**
     * Called after a DE match result is recorded.
     * Routes to WB/LB/GF handlers.
     */
    private function handleDeResult($tournament, object $match): void
    {
        $id       = $tournament->id;
        $winnerId = $match->winner_id;
        $loserId  = ($match->team1_id == $winnerId) ? $match->team2_id : $match->team1_id;

        if ($match->phase === 'grand_final') {
            DB::table('lol_test_tournaments')->where('id', $id)->update([
                'phase' => 'done', 'updated_at' => now(),
            ]);
            return;
        }

        // Loser drops to LB (WB) or gets eliminated (LB)
        if ($loserId) {
            if ($match->phase === 'winner') {
                DB::table('lol_test_teams')->where('id', $loserId)
                    ->update(['de_bracket' => 'lb', 'updated_at' => now()]);
            } elseif ($match->phase === 'loser') {
                DB::table('lol_test_teams')->where('id', $loserId)
                    ->update(['de_bracket' => 'out', 'updated_at' => now()]);
            }
        }

        // Check if this entire round is done
        $pending = DB::table('lol_test_matches')
            ->where('lol_tournament_id', $id)
            ->where('phase', $match->phase)
            ->where('round', $match->round)
            ->where('status', 'pending')
            ->count();

        if ($pending > 0) return; // round not done yet

        if ($match->phase === 'winner') {
            $this->generateDeAfterWBRound($tournament, $match->round);
        } elseif ($match->phase === 'loser') {
            $this->tryGenerateLBRound($tournament);
            $this->checkDeGrandFinal($tournament);
        }
    }

    /**
     * After a WB round is fully done: generate next WB round + try next LB round.
     */
    private function generateDeAfterWBRound($tournament, int $wbRound): void
    {
        $id = $tournament->id;

        $wbWinners = DB::table('lol_test_matches')
            ->join('lol_test_teams', 'lol_test_matches.winner_id', '=', 'lol_test_teams.id')
            ->where('lol_test_matches.lol_tournament_id', $id)
            ->where('lol_test_matches.phase', 'winner')
            ->where('lol_test_matches.round', $wbRound)
            ->whereNotNull('lol_test_matches.winner_id')
            ->select('lol_test_teams.*')
            ->orderBy('lol_test_teams.seed')
            ->get()->toArray();

        // Generate next WB round if more than 1 winner
        if (count($wbWinners) > 1) {
            $nextWBRound = $wbRound + 1;
            $insertRows  = [];

            for ($i = 0; $i < count($wbWinners) - 1; $i += 2) {
                $insertRows[] = $this->matchRow([
                    'lol_tournament_id' => $id,
                    'phase'             => 'winner',
                    'round'             => $nextWBRound,
                    'team1_id'          => $wbWinners[$i]->id,
                    'team2_id'          => $wbWinners[$i + 1]->id,
                    'status'            => 'pending',
                ]);
            }

            // Bye if odd
            if (count($wbWinners) % 2 !== 0) {
                $bye = $wbWinners[count($wbWinners) - 1];
                $insertRows[] = $this->matchRow([
                    'lol_tournament_id' => $id,
                    'phase'             => 'winner',
                    'round'             => $nextWBRound,
                    'team1_id'          => $bye->id,
                    'team2_id'          => null,
                    'winner_id'         => $bye->id,
                    'status'            => 'done',
                ]);
            }

            DB::table('lol_test_matches')->insert($insertRows);
        }
        // If count == 1: WB champion identified; Grand Final check will handle it

        // Try generating next LB round (using new WB losers)
        $this->tryGenerateLBRound($tournament);

        // Check if Grand Final can be generated
        $this->checkDeGrandFinal($tournament);
    }

    /**
     * Attempt to generate the next LB round.
     * Combines LB survivors from the last completed LB round + unmatched WB losers.
     */
    private function tryGenerateLBRound($tournament): void
    {
        $id = $tournament->id;

        // Do not generate if there are still pending LB matches
        $pendingLB = DB::table('lol_test_matches')
            ->where('lol_tournament_id', $id)
            ->where('phase', 'loser')
            ->where('status', 'pending')
            ->count();

        if ($pendingLB > 0) return;

        $lbSurvivors     = $this->getLBSurvivors($id);
        $unmatchedLBTeams = $this->getUnmatchedLBTeams($id);

        $lbTeams = array_merge($lbSurvivors, $unmatchedLBTeams);

        if (count($lbTeams) < 2) return;

        $nextLBRound = (DB::table('lol_test_matches')
            ->where('lol_tournament_id', $id)
            ->where('phase', 'loser')
            ->max('round') ?? 0) + 1;

        $insertRows = [];

        for ($i = 0; $i < count($lbTeams) - 1; $i += 2) {
            $insertRows[] = $this->matchRow([
                'lol_tournament_id' => $id,
                'phase'             => 'loser',
                'round'             => $nextLBRound,
                'team1_id'          => $lbTeams[$i]->id,
                'team2_id'          => $lbTeams[$i + 1]->id,
                'status'            => 'pending',
            ]);
        }

        // Bye for odd count
        if (count($lbTeams) % 2 !== 0) {
            $bye = $lbTeams[count($lbTeams) - 1];
            $insertRows[] = $this->matchRow([
                'lol_tournament_id' => $id,
                'phase'             => 'loser',
                'round'             => $nextLBRound,
                'team1_id'          => $bye->id,
                'team2_id'          => null,
                'winner_id'         => $bye->id,
                'status'            => 'done',
            ]);
        }

        DB::table('lol_test_matches')->insert($insertRows);
    }

    /**
     * Returns winners of the last fully-completed LB round.
     */
    private function getLBSurvivors(int $tournamentId): array
    {
        $maxLBRound = DB::table('lol_test_matches')
            ->where('lol_tournament_id', $tournamentId)
            ->where('phase', 'loser')
            ->max('round') ?? 0;

        if ($maxLBRound === 0) return [];

        $pending = DB::table('lol_test_matches')
            ->where('lol_tournament_id', $tournamentId)
            ->where('phase', 'loser')
            ->where('round', $maxLBRound)
            ->where('status', 'pending')
            ->count();

        if ($pending > 0) return [];

        return DB::table('lol_test_matches')
            ->join('lol_test_teams', 'lol_test_matches.winner_id', '=', 'lol_test_teams.id')
            ->where('lol_test_matches.lol_tournament_id', $tournamentId)
            ->where('lol_test_matches.phase', 'loser')
            ->where('lol_test_matches.round', $maxLBRound)
            ->whereNotNull('lol_test_matches.winner_id')
            ->select('lol_test_teams.*')
            ->get()->toArray();
    }

    /**
     * Returns LB teams (de_bracket = 'lb') that have NOT yet been assigned to any LB match.
     */
    private function getUnmatchedLBTeams(int $tournamentId): array
    {
        $matchedIds = DB::table('lol_test_matches')
            ->where('lol_tournament_id', $tournamentId)
            ->where('phase', 'loser')
            ->get(['team1_id', 'team2_id'])
            ->flatMap(fn($m) => array_filter([$m->team1_id, $m->team2_id]))
            ->unique()
            ->values()
            ->toArray();

        return DB::table('lol_test_teams')
            ->where('lol_tournament_id', $tournamentId)
            ->where('de_bracket', 'lb')
            ->whereNotIn('id', $matchedIds)
            ->get()->toArray();
    }

    /**
     * Generate Grand Final if WB champion and LB champion are both determined.
     */
    private function checkDeGrandFinal($tournament): void
    {
        $id = $tournament->id;

        if (DB::table('lol_test_matches')
            ->where('lol_tournament_id', $id)
            ->where('phase', 'grand_final')
            ->exists()) return;

        // Find WB champion: last WB round with exactly 1 (non-bye) completed match
        $maxWBRound = DB::table('lol_test_matches')
            ->where('lol_tournament_id', $id)
            ->where('phase', 'winner')
            ->max('round') ?? 0;

        if ($maxWBRound === 0) return;

        $wbLastMatches = DB::table('lol_test_matches')
            ->where('lol_tournament_id', $id)
            ->where('phase', 'winner')
            ->where('round', $maxWBRound)
            ->get();

        $wbPending    = $wbLastMatches->where('status', 'pending')->count();
        $realWBMatches = $wbLastMatches->whereNotNull('team2_id')->count();

        if ($wbPending > 0 || $realWBMatches !== 1) return;

        $wbChampionId = $wbLastMatches->first()->winner_id;
        if (!$wbChampionId) return;

        // No unmatched LB teams should remain
        if (count($this->getUnmatchedLBTeams($id)) > 0) return;

        // Find LB champion: last LB round fully done, 1 match
        $maxLBRound = DB::table('lol_test_matches')
            ->where('lol_tournament_id', $id)
            ->where('phase', 'loser')
            ->max('round') ?? 0;

        if ($maxLBRound === 0) return;

        $lbLastMatches = DB::table('lol_test_matches')
            ->where('lol_tournament_id', $id)
            ->where('phase', 'loser')
            ->where('round', $maxLBRound)
            ->get();

        $lbPending     = $lbLastMatches->where('status', 'pending')->count();
        $realLBMatches = $lbLastMatches->whereNotNull('team2_id')->count();

        if ($lbPending > 0 || $realLBMatches !== 1) return;

        $lbChampionId = $lbLastMatches->first()->winner_id;
        if (!$lbChampionId) return;

        // Generate Grand Final
        DB::table('lol_test_matches')->insert($this->matchRow([
            'lol_tournament_id' => $id,
            'phase'             => 'grand_final',
            'round'             => 1,
            'team1_id'          => $wbChampionId,
            'team2_id'          => $lbChampionId,
            'status'            => 'pending',
        ]));
    }

    // -----------------------------------------------------------------------
    // LIGA (ROUND-ROBIN)
    // -----------------------------------------------------------------------

    /**
     * Generate all round-robin matches using the polygon rotation algorithm.
     * Win = 3 points, Loss = 0 points.
     */
    private function generateLeagueMatches($tournament, array $teams): \Illuminate\Http\RedirectResponse
    {
        $id    = $tournament->id;
        $count = count($teams);

        if ($count < 2) {
            return back()->with('error', 'Necesitas al menos 2 equipos para la Liga.');
        }

        if (DB::table('lol_test_matches')->where('lol_tournament_id', $id)->where('phase', 'league')->exists()) {
            return back()->with('error', 'Los partidos de liga ya fueron generados.');
        }

        // Reset points
        DB::table('lol_test_teams')
            ->where('lol_tournament_id', $id)
            ->update(['points' => 0, 'wins' => 0, 'losses' => 0, 'updated_at' => now()]);

        // Pad to even number for round-robin
        $teamList = array_values($teams);
        $n        = $count;

        if ($n % 2 !== 0) {
            $teamList[] = null; // bye slot
            $n++;
        }

        $insertRows = [];
        $rounds     = $n - 1;

        for ($round = 1; $round <= $rounds; $round++) {
            for ($match = 0; $match < $n / 2; $match++) {
                $t1 = $teamList[$match];
                $t2 = $teamList[$n - 1 - $match];

                if ($t1 === null || $t2 === null) continue; // skip BYE

                $insertRows[] = $this->matchRow([
                    'lol_tournament_id' => $id,
                    'phase'             => 'league',
                    'round'             => $round,
                    'team1_id'          => $t1->id,
                    'team2_id'          => $t2->id,
                    'status'            => 'pending',
                ]);
            }

            // Rotate: fix first team, rotate the rest
            $last = array_pop($teamList);
            array_splice($teamList, 1, 0, [$last]);
        }

        DB::table('lol_test_matches')->insert($insertRows);
        DB::table('lol_test_tournaments')->where('id', $id)->update([
            'phase' => 'league', 'updated_at' => now(),
        ]);

        return back()->with('success', "Liga generada: {$count} equipos, " . count($insertRows) . " partidos.");
    }

    /**
     * Award 3 points to winner; finish tournament when all league matches are done.
     */
    private function handleLeagueResult($tournament, int $winnerId): void
    {
        $id = $tournament->id;

        DB::table('lol_test_teams')->where('id', $winnerId)->increment('points', 3);

        $pending = DB::table('lol_test_matches')
            ->where('lol_tournament_id', $id)
            ->where('phase', 'league')
            ->where('status', 'pending')
            ->count();

        if ($pending === 0) {
            DB::table('lol_test_tournaments')->where('id', $id)->update([
                'phase' => 'done', 'updated_at' => now(),
            ]);
        }
    }
}
