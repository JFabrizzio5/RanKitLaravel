<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfilePageController extends Controller
{
    public function show(Request $request): Response
    {
        // Mock user/profile
        $profile = [
            'username' => 'xSlayer99',
            'country' => 'México',
            'member_since' => 2023,
            'badge' => 'Pro Player',
            'win_rate' => 68.5,
            'tournaments' => 14,
            'avatar' => 'https://i.pravatar.cc/150?img=12',
        ];

        // Mock active tournaments
        $activeTournaments = [
            [
                'name' => 'Neon City Cup',
                'status' => 'En Curso',
                'match_code' => 'M-9921',
                'lobby_url' => '/lobby/M-9921', // inventado (ajústalo)
            ],
        ];

        // ✅ URL para copiar (inventada por ahora)
        // ideal: después que sea route('tournament.widget', ...) con token/torneo real
        $scoreboardWidgetUrl = url('/tournament/widget?overlay=scoreboard&token=demo-12345');

        return Inertia::render('Profile/Show', [
            'profile' => $profile,
            'activeTournaments' => $activeTournaments,
            'scoreboardWidgetUrl' => $scoreboardWidgetUrl,

            // Si quieres precargar códigos (mock):
            'organizer' => [
                'enabled' => true,
                'matchCodes' => [
                    'match1' => '',
                    'match2' => '',
                    'match3' => '',
                ],
            ],
        ]);
    }
}
