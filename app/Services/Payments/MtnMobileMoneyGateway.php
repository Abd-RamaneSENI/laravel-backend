<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Str;
use RuntimeException;

class MtnMobileMoneyGateway extends ConfiguredGateway
{
    public function __construct()
    {
        parent::__construct('mtn');
    }

    public function initiate(Payment $payment): array
    {
        $this->assertConfigured();
        $payerPhone = $this->requirePayerPhone($payment);
        $settings = $this->settings();
        $reference = (string) Str::uuid();
        $token = $this->accessToken($settings);

        $response = $this->http()
            ->withToken($token)
            ->withHeaders($this->collectionHeaders($settings, ['X-Reference-Id' => $reference]))
            ->post($this->url('/collection/'.($settings['collection_version'] ?? 'v1_0').'/requesttopay'), [
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'externalId' => $payment->internal_reference,
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $payerPhone,
                ],
                'payerMessage' => 'Paiement SENI-CNF EDU',
                'payeeNote' => 'Commande '.$payment->order?->reference,
            ]);

        if ($response->status() !== 202) {
            throw new RuntimeException('MTN Mobile Money a refusé l’initiation du paiement.');
        }

        return [
            'external_reference' => $reference,
            'status' => 'pending',
            'message' => 'Demande MTN Mobile Money envoyée. En attente de confirmation serveur.',
        ];
    }

    public function verify(Payment $payment): array
    {
        $this->assertConfigured();
        $settings = $this->settings();

        if (! $payment->external_reference) {
            throw new RuntimeException('Référence MTN absente pour la vérification.');
        }

        $response = $this->http()
            ->withToken($this->accessToken($settings))
            ->withHeaders($this->collectionHeaders($settings))
            ->get($this->url('/collection/'.($settings['collection_version'] ?? 'v1_0').'/requesttopay/'.$payment->external_reference));

        if (! $response->successful()) {
            throw new RuntimeException('MTN Mobile Money ne peut pas vérifier cette transaction pour le moment.');
        }

        $payload = $response->json();

        return [
            'reference' => $payment->external_reference,
            'amount' => (int) round((float) ($payload['amount'] ?? -1)),
            'currency' => (string) ($payload['currency'] ?? ''),
            'status' => $this->normalizeStatus((string) ($payload['status'] ?? '')),
            'raw' => $payload,
        ];
    }

    private function accessToken(array $settings): string
    {
        $apiUser = $settings['api_user'] ?? null;
        $apiKey = $settings['api_key'] ?? ($settings['api_secret'] ?? null);
        $subscriptionKey = $settings['subscription_key'] ?? null;

        if (! $apiUser || ! $apiKey || ! $subscriptionKey || empty($settings['target_environment'])) {
            throw new RuntimeException('La configuration MTN Mobile Money est incomplète.');
        }

        $response = $this->http()
            ->asForm()
            ->withBasicAuth($apiUser, $apiKey)
            ->withHeaders(['Ocp-Apim-Subscription-Key' => $subscriptionKey])
            ->post($this->url('/collection/token/'));

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Impossible d’obtenir un jeton MTN Mobile Money.');
        }

        return (string) $response->json('access_token');
    }

    private function collectionHeaders(array $settings, array $extra = []): array
    {
        return array_filter([
            'Ocp-Apim-Subscription-Key' => $settings['subscription_key'] ?? null,
            'X-Target-Environment' => $settings['target_environment'] ?? null,
        ]) + $extra;
    }
}
