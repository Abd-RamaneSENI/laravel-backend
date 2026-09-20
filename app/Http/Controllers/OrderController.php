<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return view('orders.index', ['orders' => $request->user()->orders()->latest()->paginate(15)]);
    }

    public function show(Request $request, Order $order, PaymentService $payments)
    {
        abort_unless($order->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        if ($order->user_id === $request->user()->id && in_array($order->status, ['pending', 'awaiting_payment'], true)) {
            try {
                $order = $payments->refreshOrder($order);
            } catch (Throwable $e) {
                report($e);
            }
        }
        $order->load('items.resource', 'payments', 'loan.book', 'book', 'user');
        $autoDownloadBook = request()->boolean('download_book')
            && $order->purpose === 'book_purchase'
            && $order->status === 'fulfilled'
            && $order->book !== null;

        return view('orders.show', compact('order', 'autoDownloadBook'));
    }

    public function invoice(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        abort_unless(in_array($order->status, ['paid', 'fulfilled'], true), 404);
        $order->load('items.resource', 'payments', 'loan.book', 'book', 'user');

        return view('orders.invoice', compact('order'));
    }

    public function openInvoiceFromResendLink(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        abort_unless(in_array($order->status, ['paid', 'fulfilled'], true), 404);

        return redirect()
            ->route('orders.invoice', $order)
            ->with('warning', "La facture s'envoie avec le bouton prévu sur la page. Vous pouvez aussi l'ouvrir et l'imprimer ici.");
    }

    public function resendInvoice(Request $request, Order $order, PaymentService $payments): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        abort_unless(in_array($order->status, ['paid', 'fulfilled'], true), 404);

        $sent = $payments->sendInvoiceEmail($order->loadMissing('user'), true);

        if ($sent) {
            return redirect()
                ->route('orders.invoice', $order)
                ->with('success', 'La facture a été envoyée à '.$order->billing_email.'.');
        }

        $message = in_array((string) config('mail.default'), ['array', 'log'], true)
            ? "Le serveur est en mode e-mail log/test. La facture est consultable et imprimable ici, mais aucun e-mail réel ne partira tant que SMTP n'est pas configuré."
            : (Str::contains((string) $order->fresh()->invoice_email_error, ['socket', 'smtp.gmail.com', '10013'])
                ? "La connexion SMTP est bloquée par le serveur Windows. Autorisez PHP/Apache à sortir vers smtp.gmail.com sur le port 587, puis utilisez le bouton de renvoi."
                : "L'envoi de la facture n'a pas pu être confirmé. Vérifiez la configuration SMTP puis réessayez.");

        return redirect()
            ->route('orders.invoice', $order)
            ->with('warning', $message);
    }
}
