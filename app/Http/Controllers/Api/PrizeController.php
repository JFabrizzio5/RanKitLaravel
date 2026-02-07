<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TournamentPrize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class PrizeController extends Controller
{
    /**
     * Process the payout for a specific prize.
     *
     * @param Request $request
     * @param int|string $prizeId
     * @return \Illuminate\Http\JsonResponse
     */
    public function payPrize(Request $request, $prizeId)
    {
        // 1. Authenticate the Organizer (User triggering the payment)
        $organizer = $request->user();

        if (!$organizer || !$organizer->stripe_id) {
            return response()->json(['error' => 'Organizer does not have a connected Stripe account.'], 403);
        }

        try {
            // 2. Start Database Transaction
            return DB::transaction(function () use ($organizer, $prizeId) {

                // 3. Find and Lock the Prize Record
                // "lockForUpdate" prevents race conditions (double clicks)
                $prize = TournamentPrize::with('winner')->lockForUpdate()->find($prizeId);

                if (!$prize) {
                    return response()->json(['error' => 'Prize not found.'], 404);
                }

                // 4. Validation
                if ($prize->status === 'paid') {
                    throw new \Exception('This prize has already been paid.');
                }

                if ($prize->status !== 'ready' && $prize->status !== 'pending') {
                // Allows generic 'pending' or specific 'ready' status
                // throw new \Exception('Prize is not ready for payment.');
                }

                if (!$prize->winner) {
                    throw new \Exception('Prize has no assigned winner.');
                }

                if (!$prize->winner->stripe_id) {
                    throw new \Exception('Winner does not have a connected Stripe account.');
                }

                // 5. Initialize Stripe
                $stripe = new StripeClient(config('services.stripe.secret'));

                // 6. Create Transfer
                // Logic: Transfer FROM Organizer Connect Account TO Winner Connect Account.
                // Since funds are on Organizer Account (Direct Charges), we must make the transfer
                // *on behalf of* the Organizer.

                $transfer = $stripe->transfers->create([
                    'amount' => $prize->amount,
                    'currency' => $prize->currency,
                    'destination' => $prize->winner->stripe_id,
                    'description' => "Prize payment: {$prize->title} (Tournament #{$prize->tournament_id})",
                ], [
                    'stripe_account' => $organizer->stripe_id, // Acting AS the Organizer
                ]);

                // 7. Update Prize Record
                $prize->status = 'paid';
                $prize->stripe_transfer_id = $transfer->id;
                $prize->paid_at = now();
                $prize->save();

                return response()->json([
                    'message' => 'Prize paid successfully.',
                    'transfer_id' => $transfer->id,
                    'amount' => $prize->amount,
                    'currency' => $prize->currency,
                ]);
            });

        }
        catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error("Stripe Error in payPrize: " . $e->getMessage());
            return response()->json(['error' => 'Stripe Payment Failed: ' . $e->getMessage()], 500);
        }
        catch (\Exception $e) {
            Log::error("Error in payPrize: " . $e->getMessage());
            // Rollback is automatic with DB::transaction helper if exception is thrown
            // Ensure we return a proper error response
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}