<?php

use App\Models\Payment;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('payments:verify-pending {--limit=50}', function (PaymentService $payments) {
    $limit = max(1, min(200, (int) $this->option('limit')));
    $pendingPayments = Payment::query()
        ->whereIn('status', ['initiated', 'pending'])
        ->whereIn('provider', ['mtn', 'moov'])
        ->oldest()
        ->limit($limit)
        ->get();

    $confirmed = 0;
    $failed = 0;

    foreach ($pendingPayments as $payment) {
        try {
            $updated = $payments->verifyPendingPayment($payment);
            $confirmed += $updated->status === 'succeeded' ? 1 : 0;
            $failed += $updated->status === 'failed' ? 1 : 0;
        } catch (Throwable $e) {
            report($e);
            $this->warn("Paiement {$payment->internal_reference} non vérifié: {$e->getMessage()}");
        }
    }

    $this->info("Paiements vérifiés: {$pendingPayments->count()}, confirmés: {$confirmed}, échoués: {$failed}.");
})->purpose('Vérifie côté serveur les paiements MTN/Moov en attente.');

Schedule::command('payments:verify-pending --limit=100')->everyMinute()->withoutOverlapping();

Artisan::command('invoices:retry {--limit=50}', function (PaymentService $payments) {
    $limit = max(1, min(200, (int) $this->option('limit')));
    $orders = Order::query()
        ->whereIn('status', ['paid', 'fulfilled'])
        ->whereNotNull('billing_email')
        ->whereNull('invoice_sent_at')
        ->where(function ($query): void {
            $query->whereNull('invoice_email_last_attempt_at')
                ->orWhere('invoice_email_last_attempt_at', '<', now()->subMinutes(5));
        })
        ->where('invoice_email_attempts', '<', 20)
        ->oldest('id')
        ->limit($limit)
        ->get();

    $sent = 0;
    $failed = 0;

    foreach ($orders as $order) {
        if ($payments->sendInvoiceEmail($order, true)) {
            $sent++;
        } else {
            $failed++;
        }
    }

    $this->info("Factures retentées: {$orders->count()}, envoyées: {$sent}, en échec: {$failed}.");
})->purpose('Retente automatiquement l’envoi des factures non remises.');

Schedule::command('invoices:retry --limit=100')->everyFiveMinutes()->withoutOverlapping();
