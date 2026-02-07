<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class TournamentPaymentController extends Controller
{
    public function checkout(Request $request, $tournamentId)
    {
        $user = $request->user();
        if (!$user)
            abort(401);

        $tournament = DB::table('tournaments')->where('id', $tournamentId)->first();

        if (!$tournament) {
            abort(404);
        }

        // 1. Validation
        if (($tournament->entry_fee ?? 0) <= 0) {
            return back()->with('error', 'Este torneo es gratuito.');
        }

        // Check if already paid
        $registration = DB::table('tournament_registrations')
            ->where('user_id', $user->id)
            ->where('tournament_id', $tournamentId)
            ->where('has_paid', true)
            ->first();

        if ($registration) {
            return redirect()->route('public.tournament.show', $tournament->slug ?? $tournament->id)
                ->with('success', 'Ya estás registrado.');
        }

        // Check if Organizer has Stripe
        $organizer = DB::table('users')->where('id', $tournament->user_id)->first();
        if (!$organizer || empty($organizer->stripe_connect_id)) {
            return back()->with('error', 'El organizador no ha configurado los pagos aún.');
        }

        // 2. Create Stripe Checkout Session
        $stripe = new StripeClient(config('services.stripe.secret'));

        try {
            // Direct Charge: Payment goes to Organizer, Platform takes fee
            $feeAmount = $tournament->entry_fee ?? 0;
            $applicationFee = (int)($feeAmount * 0.10); // 10% Platform Fee

            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                        'price_data' => [
                            'currency' => $tournament->currency ?? 'usd',
                            'product_data' => [
                                'name' => "Entrada: {$tournament->name}",
                            ],
                            'unit_amount' => $feeAmount,
                        ],
                        'quantity' => 1,
                    ]],
                'mode' => 'payment',
                'success_url' => route('payment.success', ['tournament_id' => $tournamentId]) . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('public.tournament.show', $tournament->slug ?? $tournament->id),
                'payment_intent_data' => [
                    'application_fee_amount' => $applicationFee,
                    'transfer_data' => [
                        'destination' => $organizer->stripe_connect_id,
                    ],
                ],
                'client_reference_id' => $user->id,
                'metadata' => [
                    'tournament_id' => $tournamentId,
                    'user_id' => $user->id
                ]
            ]);

            return redirect($session->url);

        }
        catch (\Exception $e) {
            Log::error("Stripe Checkout Error: " . $e->getMessage());
            return back()->with('error', 'Error al iniciar pago: ' . $e->getMessage());
        }
    }

    public function success(Request $request)
    {
        $tournamentId = $request->query('tournament_id');
        $sessionId = $request->query('session_id');

        if (!$tournamentId || !$sessionId)
            abort(404);

        $tournament = DB::table('tournaments')->where('id', $tournamentId)->first();

        if (!$tournament)
            abort(404);

        // Registrar en DB
        // En producción, esto debería hacerse idealmente via WEBHOOK para mayor seguridad,
        // pero lo hacemos aquí para feedback inmediato al usuario por ahora.

        DB::table('tournament_registrations')->updateOrInsert(
        ['user_id' => $request->user()->id, 'tournament_id' => $tournamentId],
        [
            'has_paid' => true,
            'amount_paid' => $tournament->entry_fee ?? 0,
            'currency' => $tournament->currency ?? 'usd',
            'updated_at' => now(),
            'created_at' => now() // Solo si inserta
        ]
        );

        return redirect()->route('public.tournament.show', $tournament->slug ?? $tournament->id)
            ->with('success', '¡Pago confirmado! Aquí tienes tu código de acceso.');
    }
}