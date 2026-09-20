<?php

namespace App\Services\Payments;

use App\Models\Payment;
use RuntimeException;

class MoovMoneyGateway extends ConfiguredGateway
{
    public function __construct()
    {
        parent::__construct('moov');
    }

    public function initiate(Payment $payment): array
    {
        $this->assertConfigured();
        $payerPhone = $this->requirePayerPhone($payment);
        $settings = $this->settings();

        if (empty($settings['initiate_path']) || empty($settings['merchant_number'])) {
            throw new RuntimeException('La configuration Moov Money est incomplète.');
        }

        $response = $this->request($settings)->post($this->url($settings['initiate_path']), [
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'reference' => $payment->internal_reference,
            'payer' => ['phone' => $payerPhone],
            'merchant' => ['number' => $settings['merchant_number']],
            'callback_url' => $settings['callback_url'],
            'description' => 'Paiement SENI-CNF EDU '.$payment->order?->reference,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Moov Money a refusé l’initiation du paiement.');
        }

        $payload = $response->json();

        return [
            'external_reference' => (string) ($payload['reference'] ?? $payload['transaction_id'] ?? $payload['transactionId'] ?? $payment->internal_reference),
            'checkout_url' => $payload['checkout_url'] ?? $payload['payment_url'] ?? null,
            'status' => $this->normalizeStatus((string) ($payload['status'] ?? 'pending')),
            'message' => 'Demande Moov Money envoyée. En attente de confirmation serveur.',
        ];
    }

    public function verify(Payment $payment): array
    {
        $this->assertConfigured();
        $settings = $this->settings();

        if (empty($settings['verify_path']) || ! $payment->external_reference) {
            throw new RuntimeException('La vérification Moov Money n’est pas configurée.');
        }

        $path = str_replace('{reference}', rawurlencode($payment->external_reference), $settings['verify_path']);
        $response = $this->request($settings)->get($this->url($path));

        if (! $response->successful()) {
            throw new RuntimeException('Moov Money ne peut pas vérifier cette transaction pour le moment.');
        }

        $payload = $response->json();

        return [
            'reference' => (string) ($payload['reference'] ?? $payload['transaction_id'] ?? $payload['transactionId'] ?? $payment->external_reference),
            'amount' => (int) round((float) ($payload['amount'] ?? -1)),
            'currency' => (string) ($payload['currency'] ?? ''),
            'status' => $this->normalizeStatus((string) ($payload['status'] ?? '')),
            'raw' => $payload,
        ];
    }

    private function request(array $settings)
    {
        $request = $this->http();

        if (! empty($settings['api_key'])) {
            $request = $request->withToken($settings['api_key']);
        }

        if (! empty($settings['client_id'])) {
            $request = $request->withHeader('X-Client-Id', $settings['client_id']);
        }

        if (! empty($settings['client_secret'])) {
            $request = $request->withHeader('X-Client-Secret', $settings['client_secret']);
        }

        return $request;
    }
}
