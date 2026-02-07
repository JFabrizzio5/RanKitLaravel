<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class StripeConnectController extends Controller
{
    public function connect(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'organizer' && $user->role !== 'admin') {
            return back()->with('error', 'Solo los organizadores pueden conectar una cuenta de Stripe.');
        }

        $stripe = new StripeClient(config('services.stripe.secret'));

        // 1. Create Account Link (Standard Connect)
        // If user already has an account id stored, we might want to create a login link instead?
        // For simplicity, we assume we are onboarding or re-onboarding.

        try {
            // Generate a random state token for security
            $state = csrf_token();

            // For Standard accounts, we use the OAuth flow via Stripe "Express" or "Standard" OAuth URL
            // However, Laravel Cashier or custom implementation usually prefers Account Links for Express.
            // But the prompt specified "Standard". Standard uses the OAuth authorize URL.

            $clientId = config('services.stripe.client_id'); // Need this in .env
            $redirectUri = route('stripe.connect.callback');

            $url = "https://connect.stripe.com/oauth/authorize?response_type=code&client_id={$clientId}&scope=read_write&redirect_uri={$redirectUri}&state={$state}";

            return redirect($url);

        }
        catch (\Exception $e) {
            Log::error("Stripe Connect Error: " . $e->getMessage());
            return back()->with('error', 'Error al iniciar conexión con Stripe.');
        }
    }

    public function callback(Request $request)
    {
        $user = $request->user();

        if ($request->has('error')) {
            return redirect()->route('profile.edit')->with('error', 'Conexión denegada por el usuario.');
        }

        $code = $request->query('code');
        // Validate state if needed

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));

            // Exchange code for access token and account ID
            $response = $stripe->oauth->token([
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]);

            $connectedAccountId = $response->stripe_user_id;

            // Update User
            DB::table('users')->where('id', $user->id)->update([
                'stripe_connect_id' => $connectedAccountId,
                'updated_at' => now()
            ]);

            return redirect()->route('profile.edit')->with('success', '¡Cuenta de Stripe conectada exitosamente!');

        }
        catch (\Exception $e) {
            Log::error("Stripe Callback Error: " . $e->getMessage());
            return redirect()->route('profile.edit')->with('error', 'Error al finalizar la conexión con Stripe.');
        }
    }
}