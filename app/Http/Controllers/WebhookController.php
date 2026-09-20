<?php

namespace App\Http\Controllers;

use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class WebhookController extends Controller
{
    public function store(Request $request, string $provider, PaymentService $payments): JsonResponse
    {
        try {
            $event = $payments->handleWebhook($provider, $request->getContent(), $request->all(), $request->header('X-Webhook-Signature'));

            return response()->json(['accepted' => true, 'status' => $event->status]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['accepted' => false], 400);
        }
    }
}
