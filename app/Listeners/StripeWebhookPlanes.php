<?php

namespace App\Listeners;

use App\Services\Planes\PasarelaStripe;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Engancha los planes al webhook que Cashier ya expone en /stripe/webhook
 * (con su verificación de firma). Laravel lo descubre solo por el type-hint.
 */
class StripeWebhookPlanes
{
    public function __construct(private PasarelaStripe $stripe)
    {
    }

    public function handle(WebhookReceived $event): void
    {
        $this->stripe->procesarEvento($event->payload);
    }
}
