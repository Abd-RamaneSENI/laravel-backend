<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class ConfiguredGateway implements PaymentGateway
{
    public function __construct(protected string $provider) {}

    protected function settings(): array
    {
        $provider = config("payments.providers.{$this->provider}", []);
        $environment = $provider[config('payments.environment')] ?? [];

        return array_merge($provider, $environment);
    }

    protected function url(string $pathOrUrl): string
    {
        if (str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://')) {
            return $pathOrUrl;
        }

        $baseUrl = rtrim((string) ($this->settings()['base_url'] ?? ''), '/');
        $path = ltrim($pathOrUrl, '/');

        return "{$baseUrl}/{$path}";
    }

    protected function http(): PendingRequest
    {
        return Http::timeout((int) config('payments.timeout', 30))
            ->acceptJson()
            ->asJson();
    }

    protected function assertConfigured(): void
    {
        $settings = $this->settings();
        if (empty($settings['base_url']) || empty($settings['callback_url'])) {
            throw new RuntimeException("Le provider {$this->provider} n'est pas configuré pour l'environnement actif.");
        }
        if (config('payments.environment') === 'production' && empty(config("payments.providers.{$this->provider}.webhook_secret"))) {
            throw new RuntimeException("La configuration de production {$this->provider} est incomplète.");
        }
    }

    protected function requirePayerPhone(Payment $payment): string
    {
        if (! $payment->payer_phone) {
            throw new RuntimeException('Le numéro Mobile Money du payeur est obligatoire.');
        }

        return $payment->payer_phone;
    }

    public function verifyWebhook(string $rawBody, array $payload, ?string $signature): bool
    {
        $secret = config("payments.providers.{$this->provider}.webhook_secret");

        return is_string($secret) && $secret !== '' && is_string($signature) && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    public function normalizeWebhook(array $payload): array
    {
        return [
            'event_id' => (string) ($payload['event_id'] ?? $payload['id'] ?? $payload['transactionId'] ?? ''),
            'reference' => (string) ($payload['reference'] ?? $payload['external_reference'] ?? $payload['externalId'] ?? $payload['transactionId'] ?? ''),
            'amount' => (int) round((float) ($payload['amount'] ?? -1)),
            'currency' => (string) ($payload['currency'] ?? ''),
            'status' => $this->normalizeStatus((string) ($payload['status'] ?? '')),
        ];
    }

    protected function normalizeStatus(string $status): string
    {
        return match (strtolower($status)) {
            'succeeded', 'successful', 'success', 'paid', 'completed', 'complete' => 'succeeded',
            'failed', 'failure', 'cancelled', 'canceled', 'declined', 'rejected', 'timeout', 'expired' => 'failed',
            default => 'pending',
        };
    }
}
