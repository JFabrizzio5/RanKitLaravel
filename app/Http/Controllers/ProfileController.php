<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\EsquemaUsuarios;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        EsquemaUsuarios::asegurar();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Nombre y teléfono. El correo no se cambia y la cuenta no se borra desde
     * aquí (no hay ruta DELETE /profile).
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        EsquemaUsuarios::asegurar();

        $request->user()->fill($request->validated())->save();

        return Redirect::route('profile.edit');
    }
}
