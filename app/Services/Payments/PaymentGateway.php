<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentGateway
{
    public function initiate(Payment $payment): array;

    public function verify(Payment $payment): array;

    public function verifyWebhook(string $rawBody, array $payload, ?string $signature): bool;

    public function normalizeWebhook(array $payload): array;
}
