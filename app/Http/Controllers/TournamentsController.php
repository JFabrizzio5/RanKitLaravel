<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class TournamentsController extends Controller
{
    /**
     * Muestra el detalle de un torneo (/tournaments/{id})
     */
    public function show(int $id): Response
    {
        /**
         * 🔹 MOCK DATA
         * Luego aquí reemplazas por:
         * Tournament::with(...)->findOrFail($id)
         */

        $tournament = [
            'id' => $id,
            'title' => 'BellzCup',
            'game' => 'Fortnite',
            'status' => 'En Vivo',
            'hero_image' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80',
            'prize_pool' => 25000,
            'twitch_channel' => 'Jelty',
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
        $targetDate = now()->addDays(2)->timestamp * 1000;

        return Inertia::render('Tournament/Show', [
            'tournament'        => $tournament,
            'bracketRounds'     => $bracketRounds,
            'sponsors'          => $sponsors,
            'prizeDistribution' => $prizeDistribution,
            'targetDate'        => $targetDate,
        ]);
    }
}
