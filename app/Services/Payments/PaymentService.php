<?php

namespace App\Services\Payments;

use App\Models\Entitlement;
use App\Models\BookPurchase;
use App\Models\Loan;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PaymentService
{
    public function __construct(private PaymentGatewayFactory $gateways) {}

    public function initiate(Order $order, string $provider, array $payer = []): Payment
    {
        if (! in_array($order->status, ['pending', 'awaiting_payment'], true)) {
            throw new RuntimeException('Cette commande ne peut plus être payée.');
        }
        $gateway = $this->gateways->make($provider);
        $payment = DB::transaction(function () use ($order, $provider, $payer) {
            return Payment::create([
                'order_id' => $order->id,
                'provider' => $provider,
                'environment' => config('payments.environment'),
                'payer_phone' => $payer['payer_phone'] ?? null,
                'internal_reference' => 'PAY-'.strtoupper(Str::random(18)),
                'amount' => $order->total,
                'currency' => $order->currency,
                'status' => 'initiated',
            ]);
        });
        try {
            $result = $gateway->initiate($payment);
        } catch (Throwable $e) {
            $payment->delete();
            throw $e;
        }
        $payment->update([
            'external_reference' => $result['external_reference'] ?? null,
            'checkout_url' => $result['checkout_url'] ?? null,
            'status' => $result['status'] ?? 'pending',
            'request_hash' => hash('sha256', json_encode(['provider' => $provider, 'payment' => $payment->internal_reference])),
        ]);

        if (($result['status'] ?? null) === 'succeeded') {
            return $this->fulfillSucceededPayment($payment->fresh(), json_encode($result, JSON_THROW_ON_ERROR));
        }

        $order->update(['status' => 'awaiting_payment']);

        return $payment->fresh();
    }

    public function refreshOrder(Order $order): Order
    {
        $order->loadMissing('payments');

        foreach ($order->payments as $payment) {
            if (in_array($payment->status, ['initiated', 'pending'], true)) {
                $this->verifyPendingPayment($payment);
            }
        }

        return $order->fresh();
    }

    public function verifyPendingPayment(Payment $payment): Payment
    {
        if (! in_array($payment->status, ['initiated', 'pending'], true)) {
            return $payment->fresh();
        }

        $result = $this->gateways->make($payment->provider)->verify($payment);

        if (($result['reference'] ?? null) && $payment->external_reference !== $result['reference']) {
            $payment->update(['external_reference' => $result['reference']]);
        }

        $status = $result['status'] ?? 'pending';

        if ($status === 'succeeded') {
            $this->assertAmountAndCurrencyMatch($payment, $result);

            return $this->fulfillSucceededPayment($payment, json_encode($result, JSON_THROW_ON_ERROR));
        }

        if ($status === 'failed') {
            $payment->update([
                'status' => 'failed',
                'failure_code' => 'provider_failed',
                'failure_message' => 'Transaction refusée ou expirée par le provider.',
                'status_checked_at' => now(),
            ]);

            return $payment->fresh();
        }

        $payment->update(['status' => 'pending', 'status_checked_at' => now()]);

        return $payment->fresh();
    }

    public function handleWebhook(string $provider, string $rawBody, array $payload, ?string $signature): WebhookEvent
    {
        $gateway = $this->gateways->make($provider);
        $normalized = $gateway->normalizeWebhook($payload);
        $eventId = $normalized['event_id'];
        if ($eventId === '') {
            throw new RuntimeException('Webhook sans identifiant d’événement.');
        }
        $validSignature = $gateway->verifyWebhook($rawBody, $payload, $signature);

        return DB::transaction(function () use ($gateway, $provider, $rawBody, $normalized, $eventId, $validSignature) {
            $event = WebhookEvent::firstOrCreate(['provider' => $provider, 'event_id' => $eventId], ['environment' => config('payments.environment'), 'signature_valid' => $validSignature, 'payload_hash' => hash('sha256', $rawBody), 'received_at' => now(), 'status' => 'received']);
            if (! $event->wasRecentlyCreated) {
                return $event;
            }
            if (! $validSignature) {
                $event->update(['status' => 'rejected', 'error_code' => 'invalid_signature', 'processed_at' => now()]);

                return $event;
            }
            $payment = Payment::where('provider', $provider)
                ->where(function ($query) use ($normalized) {
                    $query->where('external_reference', $normalized['reference'])
                        ->orWhere('internal_reference', $normalized['reference']);
                })
                ->lockForUpdate()
                ->first();
            if (! $payment) {
                $event->update(['status' => 'rejected', 'error_code' => 'unknown_reference', 'processed_at' => now()]);

                return $event;
            }
            if (($normalized['amount'] ?? -1) < 0 || ($normalized['currency'] ?? '') === '') {
                try {
                    $verified = $gateway->verify($payment);
                    $normalized['amount'] = $verified['amount'] ?? $normalized['amount'];
                    $normalized['currency'] = $verified['currency'] ?? $normalized['currency'];
                    $normalized['status'] = $verified['status'] ?? $normalized['status'];
                } catch (Throwable $e) {
                    report($e);
                    $event->update(['status' => 'rejected', 'error_code' => 'provider_verification_failed', 'processed_at' => now()]);

                    return $event;
                }
            }
            if ($payment->amount !== $normalized['amount'] || $payment->currency !== $normalized['currency']) {
                $event->update(['status' => 'rejected', 'error_code' => 'amount_or_currency_mismatch', 'processed_at' => now()]);

                return $event;
            }
            if ($normalized['status'] === 'failed') {
                $payment->update(['status' => 'failed', 'failure_code' => 'provider_'.$normalized['status']]);
                $event->update(['status' => 'processed', 'processed_at' => now()]);

                return $event;
            }
            if ($normalized['status'] !== 'succeeded') {
                $payment->update(['status' => 'pending', 'status_checked_at' => now()]);
                $event->update(['status' => 'processed', 'processed_at' => now()]);

                return $event;
            }
            if ($payment->status !== 'succeeded') {
                $this->fulfillSucceededPayment($payment, $rawBody);
            }
            $event->update(['status' => 'processed', 'processed_at' => now()]);

            return $event;
        });
    }

    private function assertAmountAndCurrencyMatch(Payment $payment, array $result): void
    {
        if (($result['amount'] ?? $payment->amount) !== $payment->amount || ($result['currency'] ?? $payment->currency) !== $payment->currency) {
            $payment->update([
                'status' => 'failed',
                'failure_code' => 'amount_or_currency_mismatch',
                'failure_message' => 'Montant ou devise confirmé différent de la commande.',
                'status_checked_at' => now(),
            ]);

            throw new RuntimeException('Le montant confirmé par le provider ne correspond pas à la commande.');
        }
    }

    private function fulfillSucceededPayment(Payment $payment, string $rawPayload = ''): Payment
    {
        return DB::transaction(function () use ($payment, $rawPayload) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'succeeded' || ! $payment->paid_at) {
                $payment->update([
                    'status' => 'succeeded',
                    'paid_at' => now(),
                    'payload_hash' => $rawPayload !== '' ? hash('sha256', $rawPayload) : $payment->payload_hash,
                ]);
            }

            $order = $payment->order()->with(['items.resource', 'loan.book', 'book'])->lockForUpdate()->firstOrFail();
            if (! in_array($order->status, ['paid', 'fulfilled'], true)) {
                $order->update([
                    'status' => 'fulfilled',
                    'paid_at' => now(),
                    'fulfilled_at' => now(),
                ]);
            }
            $this->sendInvoiceEmail($order->fresh(['user']));

            if ($order->purpose === 'loan_borrowing_fee') {
                $loan = Loan::query()->lockForUpdate()->findOrFail($order->loan_id);
                if ($loan->fee_status !== 'paid') {
                    $loan->update([
                        'fee_amount' => $order->total,
                        'fee_currency' => $order->currency,
                        'fee_status' => 'paid',
                        'fee_paid_at' => now(),
                        'access_expires_at' => now()->addDays(Loan::READING_ACCESS_DAYS),
                        'fee_charged_amount' => 0,
                        'fee_refund_amount' => 0,
                        'fee_refund_status' => 'none',
                        'fee_provider' => $payment->provider,
                        'fee_payment_reference' => $payment->external_reference ?: $payment->internal_reference,
                    ]);
                }

                return $payment->fresh();
            }

            if ($order->purpose === 'book_purchase') {
                abort_unless($order->book_id && $order->book, 422, 'Le livre associé à cette commande est introuvable.');

                BookPurchase::updateOrCreate(
                    ['user_id' => $order->user_id, 'book_id' => $order->book_id],
                    ['order_id' => $order->id, 'granted_at' => now()]
                );

                return $payment->fresh();
            }

            foreach ($order->items as $item) {
                $entitlement = Entitlement::firstOrCreate(
                    ['user_id' => $order->user_id, 'resource_id' => $item->resource_id, 'order_id' => $order->id],
                    ['granted_at' => now()]
                );

                if ($entitlement->wasRecentlyCreated) {
                    $item->resource?->increment('sales_count');
                }
            }

            return $payment->fresh();
        });
    }

    public function sendInvoiceEmail(Order $order, bool $force = false): bool
    {
        if (! $order->billing_email) {
            return false;
        }

        if ($order->invoice_sent_at && ! $force) {
            return true;
        }

        $order->update([
            'invoice_email_attempts' => ((int) $order->invoice_email_attempts) + 1,
            'invoice_email_last_attempt_at' => now(),
            'invoice_email_error' => null,
        ]);

        try {
            $invoiceUrl = route('orders.invoice', $order);
            Mail::html(
                '<p>Bonjour '.e($order->billing_name ?: $order->user?->name ?: 'cher utilisateur').',</p>'.
                '<p>Votre paiement SENI-CNF EDU a été confirmé.</p>'.
                '<p><strong>Commande :</strong> '.e($order->reference).'<br>'.
                '<strong>Total :</strong> '.number_format($order->total, 0, ',', ' ').' '.e($order->currency).'</p>'.
                '<p>Vous pouvez consulter et imprimer votre facture ici : <a href="'.e($invoiceUrl).'">'.e($invoiceUrl).'</a></p>',
                function ($message) use ($order): void {
                    $message->to($order->billing_email)
                        ->subject('Facture '.$order->reference.' - SENI-CNF EDU');
                }
            );

            if ($this->mailerOnlySimulatesDelivery()) {
                $order->update([
                    'invoice_email_error' => 'Le mailer local est configuré en mode simulation.',
                ]);

                return false;
            }

            $order->update([
                'invoice_sent_at' => now(),
                'invoice_email_error' => null,
            ]);

            return true;
        } catch (Throwable $e) {
            $order->update([
                'invoice_email_error' => Str::limit($e->getMessage(), 1000),
            ]);
            report($e);

            return false;
        }
    }

    private function mailerOnlySimulatesDelivery(): bool
    {
        return ! app()->environment('testing')
            && in_array((string) config('mail.default'), ['array', 'log'], true);
    }
}
