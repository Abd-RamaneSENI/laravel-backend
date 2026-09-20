<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class PaymentController extends Controller
{
    public function initiateSelected(Request $request, Order $order, PaymentService $payments): RedirectResponse
    {
        $provider = (string) $request->input('provider');

        return $this->startPayment($request, $order, $provider, $payments);
    }

    public function initiate(Request $request, Order $order, string $provider, PaymentService $payments): RedirectResponse
    {
        return $this->startPayment($request, $order, $provider, $payments);
    }

    private function startPayment(Request $request, Order $order, string $provider, PaymentService $payments): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        try {
            $data = $request->validate([
                'provider' => ['nullable', Rule::in(config('payments.enabled'))],
                'billing_name' => ['required', 'string', 'max:255'],
                'billing_email' => ['required', 'email', 'max:255'],
                'payer_phone' => [in_array($provider, ['mtn', 'moov'], true) ? 'required' : 'nullable', 'string', 'min:8', 'max:32', 'regex:/^[0-9+ ]+$/'],
            ]);

            $data['payer_phone'] = isset($data['payer_phone']) ? preg_replace('/\s+/', '', $data['payer_phone']) : null;
            $order->update([
                'billing_name' => $data['billing_name'],
                'billing_email' => $data['billing_email'],
                'billing_address' => null,
            ]);
            $payment = $payments->initiate($order, $provider, $data);

            if ($payment->status === 'succeeded') {
                if ($order->purpose === 'loan_borrowing_fee') {
                    return redirect()
                        ->route('orders.show', $order)
                        ->with('success', 'Paiement confirmé. Votre document emprunté est disponible, et votre facture est accessible depuis cette commande.');
                }

                if ($order->purpose === 'book_purchase') {
                    return redirect()
                        ->route('orders.show', ['order' => $order, 'download_book' => 1])
                        ->with('success', 'Paiement confirmé. Votre livre numérique est disponible au téléchargement, et votre facture est accessible depuis cette commande.');
                }

                return redirect()
                    ->route('library.index', ['download_order' => $order->id])
                    ->with('success', 'Paiement confirmé. Votre ressource est dans votre bibliothèque, le téléchargement démarre automatiquement et la facture reste disponible dans votre commande.');
            }

            if ($payment->checkout_url) {
                return redirect()->away($payment->checkout_url);
            }

            return back()->with('success', "Paiement envoyé au provider. Le serveur confirmera automatiquement après validation réelle. Référence: {$payment->internal_reference}");
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['payment' => $e instanceof RuntimeException ? $e->getMessage() : 'Le paiement ne peut pas être initié avec ce provider.']);
        }
    }
}
