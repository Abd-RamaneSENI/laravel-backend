<?php

namespace App\Services\Payments;

use App\Models\Payment;
use RuntimeException;

class FakePaymentGateway implements PaymentGateway
{
    public function initiate(Payment $payment): array
    {
        if (config('payments.environment') !== 'test') {
            throw new RuntimeException('Le provider fake est réservé aux tests locaux et ne doit pas être utilisé pour des paiements réels.');
        }

        return ['external_reference' => 'FAKE-'.$payment->internal_reference, 'status' => 'succeeded', 'message' => 'Paiement de test confirmé.'];
    }

    public function verify(Payment $payment): array
    {
        return [
            'reference' => $payment->external_reference ?: 'FAKE-'.$payment->internal_reference,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => 'succeeded',
        ];
    }

    public function verifyWebhook(string $rawBody, array $payload, ?string $signature): bool
    {
        return config('payments.environment') === 'test' && ($payload['test_token'] ?? null) === 'seni-cnf-test';
    }

    public function normalizeWebhook(array $payload): array
    {
        return ['event_id' => (string) ($payload['event_id'] ?? ''), 'reference' => (string) ($payload['reference'] ?? ''), 'amount' => (int) ($payload['amount'] ?? -1), 'currency' => (string) ($payload['currency'] ?? ''), 'status' => (string) ($payload['status'] ?? '')];
    }
}
