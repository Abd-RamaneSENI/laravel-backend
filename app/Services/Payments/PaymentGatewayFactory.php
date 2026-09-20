<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class PaymentGatewayFactory
{
    public function make(string $provider): PaymentGateway
    {
        if (! in_array($provider, config('payments.enabled'), true)) {
            throw new InvalidArgumentException('Provider de paiement non activé.');
        }

return match ($provider) {
            'fake' => new FakePaymentGateway, 'mtn' => new MtnMobileMoneyGateway, 'moov' => new MoovMoneyGateway, default => throw new InvalidArgumentException('Provider de paiement inconnu.'),
        };
    }
}
