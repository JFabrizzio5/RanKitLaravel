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
            'username' => 'Bellz',
            'country' => 'México',
            'member_since' => 2023,
            'badge' => 'Pro Player',
            'win_rate' => 68.5,
            'tournaments' => 14,
            'avatar' => 'https://static-cdn.jtvnw.net/jtv_user_pictures/008b9b4e-651c-4d3b-b14b-5b2122898c8d-profile_image-70x70.png',
        ];

        // Mock active tournaments
        $activeTournaments = [
            [
                'name' => 'BellzCup',
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
