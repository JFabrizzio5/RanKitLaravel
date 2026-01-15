<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect()
    {
        // stateless() ayuda cuando hay problemas de sesión/cookies en algunos hostings
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        // 1) Si ya existe por google_id
        $user = User::where('google_id', $googleUser->getId())->first();

        // 2) Si no, intenta empatar por email
        if (!$user) {
            $user = User::where('email', $googleUser->getEmail())->first();
        }

        // 3) Si no existe, crea usuario
        if (!$user) {
            $user = User::create([
                'name' => $googleUser->getName() ?? $googleUser->getNickname() ?? 'Usuario',
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                // Password dummy (no la usará). OJO: no dejes null si tu DB no permite.
                'password' => bcrypt(Str::random(32)),
            ]);
        } else {
            // Si existe, asegúrate de ligar google_id si aún no lo tiene
            if (!$user->google_id) {
                $user->google_id = $googleUser->getId();
                $user->avatar = $googleUser->getAvatar();
                $user->save();
            }
        }

        Auth::login($user, true);

        return redirect()->intended(route('dashboard'));
    }
}
