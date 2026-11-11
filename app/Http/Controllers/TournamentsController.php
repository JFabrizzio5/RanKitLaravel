<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response as HttpResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Support\Seo;
class TournamentsController extends Controller
{
    /**
     * /tournaments (enlace viejo, también en la barra de Semanales): ahora
     * hay listado propio en /torneos.
     */
    public function index(): \Illuminate\Http\RedirectResponse
    {
        return redirect('/torneos', 301);
    }

    /**
     * /torneos: todos los torneos públicos para que un jugador encuentre dónde
     * entrar. Sin privados (van por código) y sin las fechas hijas de un torneo
     * seriado (se ven dentro del principal). Nunca manda access_code.
     */
    public function lista(): Response
    {
        $torneos = [];

        if (\Illuminate\Support\Facades\Schema::hasTable('tournaments')) {
            $inscritos = \Illuminate\Support\Facades\Schema::hasTable('tournament_registrations')
                ? DB::table('tournament_registrations')->select('tournament_id', DB::raw('count(*) as total'))->groupBy('tournament_id')->pluck('total', 'tournament_id')
                : collect();
            $partidas = \Illuminate\Support\Facades\Schema::hasTable('tournament_matches')
                ? DB::table('tournament_matches')->select('tournament_id', DB::raw('count(*) as total'))->groupBy('tournament_id')->pluck('total', 'tournament_id')
                : collect();
            $ahora = Carbon::now();

            $torneos = DB::table('tournaments')
                ->where(fn ($q) => $q->whereNull('is_private')->orWhere('is_private', false))
                ->whereNull('parent_tournament_id')
                ->orderByDesc('created_at')
                ->limit(300)
                ->get()
                ->map(function ($t) use ($inscritos, $partidas, $ahora) {
                    $inicio = !empty($t->start_date ?? null) ? Carbon::parse($t->start_date) : null;
                    $estado = ($t->registration_status ?? '') === 'open'
                        ? 'abierto'
                        : ($inicio && $inicio->isAfter($ahora) ? 'proximo' : 'jugado');

                    return [
                        'id' => (int) $t->id,
                        'nombre' => (string) $t->name,
                        'url' => '/t/' . ($t->slug ?: $t->id),
                        'imagen' => !empty($t->banner_image) ? asset('public/' . $t->banner_image) : null,
                        'logo' => !empty($t->logo_image ?? null) ? asset('public/' . $t->logo_image) : null,
                        'estado' => $estado,
                        'fecha' => $inicio?->toIso8601String(),
                        'fecha_texto' => $t->date_label ?? null,
                        'gratis' => (bool) ($t->is_free ?? false),
                        'precio' => $t->ticket_price ?? null,
                        'inscritos' => (int) ($inscritos[$t->id] ?? 0),
                        'partidas' => (int) ($partidas[$t->id] ?? 0),
                        'creado' => $t->created_at,
                    ];
                })
                // Primero lo que se puede jugar: abiertos, luego próximos, luego el resto.
                ->sortBy(fn ($t) => ['abierto' => 0, 'proximo' => 1, 'jugado' => 2][$t['estado']])
                ->values()
                ->all();
        }

        $catalogo = app(\App\Services\Planes\CatalogoPlanes::class);

        return Inertia::render('Torneos/Index', [
            'torneos' => $torneos,
            'precioBase' => $catalogo->precio('base'),
            'precioPro' => $catalogo->precio('pro'),
            'seo' => Seo::pagina('torneos'),
        ]);
    }

    /**
     * Muestra el detalle de un torneo (/tournaments/{id})
     */
    public function show(int $id): Response
    {
        $dbTournament = \Illuminate\Support\Facades\DB::table('tournaments')->where('id', $id)->first();
        
        if (!$dbTournament) {
            abort(404, 'Torneo no encontrado');
        }

        $tournament = [
            'id' => $dbTournament->id,
            'title' => $dbTournament->name,
            'game' => $dbTournament->game_id ?? 'Juego Desconocido',
            'status' => 'En Vivo',
            'hero_image' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80', // Placeholder
            'prize_pool' => 0,
            'twitch_channel' => $dbTournament->twitch_channel ?? '',
        ];

        $bracketRounds = [
            [
                'name' => 'Cuartos',
                'matches' => [
                    ['id' => 1, 'p1' => 'Team Liquid', 'p2' => 'Cloud9', 's1' => 2, 's2' => 0, 'status' => 'finished', 'winner' => 'p1', 'prediction' => 85],
                    ['id' => 2, 'p1' => 'Sentinels', 'p2' => '100 Thieves', 's1' => 1, 's2' => 2, 'status' => 'finished', 'winner' => 'p2', 'prediction' => 45],
                    ['id' => 3, 'p1' => 'Fnatic', 'p2' => 'G2 Esports', 's1' => 1, 's2' => 0, 'status' => 'live', 'isBo3' => true, 'prediction' => 52],
                    ['id' => 4, 'p1' => 'KRÜ', 'p2' => 'Leviatán', 's1' => 0, 's2' => 0, 'status' => 'scheduled', 'prediction' => 60],
                ],
            ],
            [
                'name' => 'Semis',
                'matches' => [
                    ['id' => 5, 'p1' => 'Team Liquid', 'p2' => '100 Thieves', 's1' => 0, 's2' => 0, 'status' => 'scheduled', 'prediction' => 70],
                    ['id' => 6, 'p1' => 'TBD', 'p2' => 'TBD', 's1' => 0, 's2' => 0, 'status' => 'scheduled'],
                ],
            ],
            [
                'name' => 'Final',
                'matches' => [
                    ['id' => 7, 'p1' => 'TBD', 'p2' => 'TBD', 's1' => 0, 's2' => 0, 'status' => 'scheduled', 'isFinal' => true],
                ],
            ],
        ];

        $sponsors = [
            ['name' => 'Red Bull', 'logo' => 'https://upload.wikimedia.org/wikipedia/en/thumb/f/f5/Red_Bull_GmbH_logo.svg/1200px-Red_Bull_GmbH_logo.svg.png'],
            ['name' => 'Intel', 'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c9/Intel-logo.svg/1200px-Intel-logo.svg.png'],
            ['name' => 'Logitech G', 'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a2/Logitech_logo.svg/2560px-Logitech_logo.svg.png'],
            ['name' => 'Secretlab', 'logo' => 'https://upload.wikimedia.org/wikipedia/commons/7/7e/Secretlab_Logo_2020.png'],
            ['name' => 'Prime Gaming', 'logo' => 'https://upload.wikimedia.org/wikipedia/commons/f/f1/Prime_Gaming_logo.svg'],
            ['name' => 'Monster Energy', 'logo' => 'https://upload.wikimedia.org/wikipedia/commons/1/13/Monster_Energy_logo.svg'],
        ];

        $prizeDistribution = [
            ['place' => '1st', 'amount' => '$15,000', 'percent' => 60, 'color' => 'bg-yellow-500'],
            ['place' => '2nd', 'amount' => '$7,000', 'percent' => 28, 'color' => 'bg-gray-400'],
            ['place' => '3rd', 'amount' => '$3,000', 'percent' => 12, 'color' => 'bg-orange-700'],
        ];

        // 🔹 Fecha objetivo para el countdown (en ms para JS)
        $targetDate = \Carbon\Carbon::create(2026, 1, 18, 22, 15, 0, 'America/Mexico_City')->timestamp * 1000;

        $user = auth()->user();

$totalPoints = 0;

if ($user) {
  $row = \DB::table('bellzcup_rifapoints')->where('userid', $user->id)->first();
  $totalPoints = $row?->totalpoints ?? 0;
}
        return Inertia::render('Tournament/Show', [
            'tournament'        => $tournament,
            'bracketRounds'     => $bracketRounds,
            'sponsors'          => $sponsors,
            'prizeDistribution' => $prizeDistribution,
            'targetDate'        => $targetDate,
            'totalPoints' => $totalPoints,
            'seo'               => Seo::torneo($dbTournament),
        ]);
    }
}
